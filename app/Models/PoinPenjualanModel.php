<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Ledger poin gaji.
 *
 * Poin diberikan saat nota sudah LUNAS dan barangnya sudah berpindah tangan.
 * Yang terakhir ini punya dua wajah, jadi dilihat per baris barang:
 *   - baris yang ditautkan ke unit stok sudah keluar dari rak begitu nota dibayar;
 *     pembeli membawanya pulang sendiri, tidak pernah ada kurir atau resi, jadi
 *     nota seperti ini tidak boleh menunggu tab Selesai selamanya;
 *   - baris tulisan tangan (custom/pembuatan) baru dikerjakan setelah barangnya
 *     jadi, jadi tetap menunggu nota masuk tab Selesai (status_pengiriman = 3).
 * Lunas saja tidak cukup untuk yang kedua: orderan boleh sudah dibayar penuh tapi
 * barangnya belum tentu dikerjakan, dan poin gaji adalah imbalan atas pekerjaan
 * yang sampai ke pelanggan. Salah satu syarat lepas, poin baris itu dicabut lagi
 * (lihat sinkron()).
 *
 * Nota sewa punya satu syarat lagi: barangnya harus sudah dikonfirmasi kembali.
 * Selama tombol "Sudah kembali" di nota belum ditekan, nota itu masih tercatat
 * sebagai penyewaan berjalan dan pembuatnya belum berhak atas poinnya — sama
 * seperti tab Selesai dan stok kolam sewa, ketiganya membaca penanda yang satu.
 *
 * Yang menerima poin adalah pembuat nota (invoice.user_id), bukan orang yang
 * menekan tombol cek pembayaran. Nilainya dipotret saat pemberian (poin_satuan
 * dan qty), sehingga perubahan poin varian di kemudian hari tidak mengubah
 * gaji yang sudah terlanjur dihitung — karena itu baris yang sudah tercatat tidak
 * pernah dihitung ulang, hanya ditambah atau dicabut.
 */
class PoinPenjualanModel extends Model
{
    protected $table         = 'poin_penjualan';
    protected $primaryKey    = 'id_poin';
    protected $returnType    = 'array';
    protected $allowedFields = [
        'invoice_id', 'dibeli_id', 'user_id', 'juragan_id', 'stok_id', 'produk_id',
        'kode', 'ukuran', 'qty', 'poin_satuan', 'poin_total', 'created_at',
    ];
    protected $useTimestamps  = false;

    /** Nilai status_pengiriman yang berarti semua barang sudah keluar = tab Selesai. */
    private const SELESAI = '3';

    /**
     * Samakan isi ledger dengan keadaan nota saat ini.
     *
     * Dipanggil setiap kali status pembayaran atau status pengiriman sebuah
     * invoice berubah, jadi pemanggil tidak perlu memutuskan sendiri memberi
     * atau mencabut poin. Per baris barang: yang baru layak ditambah, yang
     * sudah tidak layak dicabut, yang sudah tercatat dibiarkan apa adanya.
     */
    public function sinkron(int $invoice_id): void
    {
        $nota = $this->db->query('SELECT user_id, juragan_id, status_pembayaran, status_pengiriman, status_pesanan, deleted_at, rincian, sewa_kembali_at'
            . ' FROM `' . $this->t('invoice') . '` WHERE id_invoice = ? LIMIT 1', [$invoice_id])
            ->getRow();

        if ($nota === null) {
            $this->cabut($invoice_id);

            return;
        }

        $lunas      = InvoiceModel::sudahLunas($nota);
        $selesai    = (string) $nota->status_pengiriman === self::SELESAI;
        $tersimpan  = $nota->deleted_at === null;
        $dibatalkan = (string) $nota->status_pesanan === '3';
        $pulang     = ! InvoiceModel::adalahSewa($nota->rincian ?? null) || (int) ($nota->sewa_kembali_at ?? 0) > 0;

        // syarat setingkat nota; "selesai" tidak lagi ikut di sini karena baris
        // berstok boleh dihitung lebih dulu
        if (! $lunas || ! $tersimpan || ! $pulang || $dibatalkan || (int) $nota->user_id <= 0) {
            $this->cabut($invoice_id);

            return;
        }

        // baris warisan aturan lama (dibeli_id masih kosong) tidak bisa dicocokkan
        // ke baris orderan mana pun, jadi ledger nota itu dibiarkan utuh
        if ($this->where('invoice_id', $invoice_id)->where('dibeli_id', null)->countAllResults() > 0) {
            return;
        }

        $tercatat = $this->tercatat($invoice_id);
        $item     = $this->barisNota($invoice_id);
        $poin     = $this->poinProduk($item);
        $waktu    = time();

        $baru  = [];
        $hapus = [];

        // daftar baris orderan yang masih ada hari ini
        $masih_ada = array_fill_keys(array_map(static fn (array $b): int => (int) $b['id_beli'], $item), true);

        foreach ($item as $b) {
            $id_beli = (int) $b['id_beli'];
            $qty     = (int) $b['qty'];

            // barang belum berpindah tangan: custom yang notasinya belum Selesai,
            // atau qty nol karena barisnya sudah tidak dihitung lagi
            $berserah = $qty > 0 && ($selesai || $b['stok_id'] !== null);

            if (! $berserah) {
                if (isset($tercatat[$id_beli])) {
                    $hapus[] = $id_beli;
                }

                continue;
            }

            // sudah tercatat: potret nilainya dibiarkan, walau poin produknya
            // berubah atau menjadi nol di kemudian hari
            if (isset($tercatat[$id_beli])) {
                continue;
            }

            $produkId = $b['produk_id'] !== null ? (int) $b['produk_id'] : 0;
            $kunci    = $produkId > 0 ? 'pid:' . $produkId : 'kode:' . mb_strtolower(trim((string) $b['kode']));
            $satuan   = $poin[$kunci] ?? 0;

            if ($satuan <= 0) {
                continue;
            }

            $baru[] = [
                'invoice_id'  => $invoice_id,
                'dibeli_id'   => $id_beli,
                'user_id'     => (int) $nota->user_id,
                'juragan_id'  => (int) $nota->juragan_id,
                'stok_id'     => $b['stok_id'] !== null ? (int) $b['stok_id'] : null,
                'produk_id'   => $produkId > 0 ? $produkId : null,
                'kode'        => (string) $b['kode'],
                'ukuran'      => $b['ukuran'] !== null ? (string) $b['ukuran'] : null,
                'qty'         => $qty,
                'poin_satuan' => $satuan,
                'poin_total'  => $qty * $satuan,
                'created_at'  => $waktu,
            ];
        }

        // baris orderannya sendiri sudah hilang (nota disunting) — poinnya ikut
        foreach ($tercatat as $id_beli => $_) {
            if (! isset($masih_ada[$id_beli])) {
                $hapus[] = $id_beli;
            }
        }

        if ($baru !== []) {
            $this->insertBatch($baru);
        }

        $hapus = array_values(array_unique($hapus));

        if ($hapus !== []) {
            $this->where('invoice_id', $invoice_id)->whereIn('dibeli_id', $hapus)->delete();
        }
    }

    /** Hapus semua baris ledger milik satu nota (pencabutan poin). */
    public function cabut(int $invoice_id): void
    {
        $this->where('invoice_id', $invoice_id)->delete();
    }

    /**
     * Baris orderan milik satu nota, termasuk yang hari ini tidak berpoin:
     * penautan stoknya dipakai untuk memutuskan barang sudah berpindah tangan atau
     * belum, dan id_beli dipakai sebagai kunci rekonsiliasi dengan ledger.
     *
     * @return list<array<string, mixed>>
     */
    private function barisNota(int $invoice_id): array
    {
        return $this->db->query('SELECT id_beli, produk_id, stok_id, kode, ukuran, qty'
            . ' FROM `' . $this->t('dibeli') . '` WHERE invoice_id = ?', [$invoice_id])
            ->getResultArray();
    }

    /** Peta id_beli => true untuk baris ledger nota ini yang sudah tercatat. */
    private function tercatat(int $invoice_id): array
    {
        $baris = $this->db->query('SELECT dibeli_id FROM `' . $this->t('poin_penjualan')
            . '` WHERE invoice_id = ? AND dibeli_id IS NOT NULL', [$invoice_id])->getResultArray();

        return array_fill_keys(array_map(static fn (array $b): int => (int) $b['dibeli_id'], $baris), true);
    }

    /**
     * Nilai poin per produk (master). Item dengan produk_id dibaca lewat id,
     * item lama tanpa produk_id dicocokkan lewat kode.
     *
     * @param list<array<string, mixed>> $item
     *
     * @return array<string, int>
     */
    private function poinProduk(array $item): array
    {
        $ids = array_values(array_unique(array_filter(array_map(
            static fn (array $b): int => (int) ($b['produk_id'] ?? 0),
            $item
        ))));

        $kodes = [];

        foreach ($item as $b) {
            if (! empty($b['produk_id'])) {
                continue;
            }

            $kode = mb_strtolower(trim((string) $b['kode']));

            if ($kode !== '') {
                $kodes[$kode] = true;
            }
        }

        $peta = [];

        if ($ids !== []) {
            $rows = $this->db->query('SELECT id_produk, poin FROM `' . $this->t('produk')
                . '` WHERE id_produk IN (' . implode(',', $ids) . ')')->getResultArray();

            foreach ($rows as $r) {
                $peta['pid:' . (int) $r['id_produk']] = (int) $r['poin'];
            }
        }

        if ($kodes !== []) {
            $bind = array_keys($kodes);
            $stmt = implode(',', array_fill(0, count($bind), '?'));

            $rows = $this->db->query('SELECT kode, poin FROM `' . $this->t('produk')
                . '` WHERE LOWER(kode) IN (' . $stmt . ')', $bind)->getResultArray();

            foreach ($rows as $r) {
                $peta['kode:' . mb_strtolower(trim((string) $r['kode']))] = (int) $r['poin'];
            }
        }

        return $peta;
    }

    // ------------------------------------------------------------------
    // Laporan akumulasi poin
    // ------------------------------------------------------------------

    /**
     * Bangun klausa WHERE + bind dari filter laporan.
     *
     * @param array $f  kunci: awal, akhir (Y-m-d), juragan (int), user (int), cari (nama pegawai)
     */
    private function saring(array $f, array $juragan_ids): array
    {
        $where = ['1=1'];
        $bind  = [];

        if (! empty($f['awal'])) {
            $where[] = 'p.created_at >= ?';
            $bind[]  = strtotime($f['awal'] . ' 00:00:00');
        }

        if (! empty($f['akhir'])) {
            $where[] = 'p.created_at <= ?';
            $bind[]  = strtotime($f['akhir'] . ' 23:59:59');
        }

        $juragan = (int) ($f['juragan'] ?? 0);

        if ($juragan > 0) {
            $where[] = 'p.juragan_id = ?';
            $bind[]  = $juragan;
        } elseif ($juragan_ids !== []) {
            // pembatasan toko tetap berlaku walau laporan dibuka Admin/CS berlevel
            $where[] = 'p.juragan_id IN (' . implode(',', array_map('intval', $juragan_ids)) . ')';
        }

        $user = (int) ($f['user'] ?? 0);

        if ($user > 0) {
            $where[] = 'p.user_id = ?';
            $bind[]  = $user;
        }

        $cari = trim((string) ($f['cari'] ?? ''));

        if ($cari !== '') {
            // nama pegawai dicari lewat sub-query, bukan lewat JOIN: clause ini
            // dipakai juga oleh query yang tidak menggandeng tabel user
            // (ringkas & jumlah rincian)
            $where[] = 'p.user_id IN (SELECT `id` FROM `' . $this->t('user') . '` WHERE `name` LIKE ?)';
            $bind[]  = '%' . $cari . '%';
        }

        return [implode(' AND ', $where), $bind];
    }

    /**
     * Akumulasi poin per pengguna (dipakai untuk hitung gaji).
     *
     * @return list<array<string, mixed>>
     */
    public function akumulasi(array $f, array $juragan_ids = []): array
    {
        [$where, $bind] = $this->saring($f, $juragan_ids);

        return $this->db->query('SELECT p.user_id, COALESCE(u.name, p.user_id) AS nama,'
            . ' COUNT(DISTINCT p.invoice_id) AS nota, COALESCE(SUM(p.qty), 0) AS unit,'
            . ' COALESCE(SUM(p.poin_total), 0) AS poin'
            . ' FROM `' . $this->t('poin_penjualan') . '` p'
            . ' LEFT JOIN `' . $this->t('user') . '` u ON u.id = p.user_id'
            . ' WHERE ' . $where . ' GROUP BY p.user_id, u.name ORDER BY poin DESC, nama ASC', $bind)
            ->getResultArray();
    }

    /** Ringkasan total satu set filter: poin, unit, nota, dan jumlah pegawai. */
    public function ringkas(array $f, array $juragan_ids = []): array
    {
        [$where, $bind] = $this->saring($f, $juragan_ids);

        $baris = $this->db->query('SELECT COALESCE(SUM(p.poin_total), 0) AS poin,'
            . ' COALESCE(SUM(p.qty), 0) AS unit,'
            . ' COUNT(DISTINCT p.invoice_id) AS nota,'
            . ' COUNT(DISTINCT p.user_id) AS pegawai'
            . ' FROM `' . $this->t('poin_penjualan') . '` p WHERE ' . $where, $bind)
            ->getFirstRow();

        return array_map('intval', (array) $baris);
    }

    /**
     * Rincian per pegawai per produk, untuk panel detail di tab Rekap.
     *
     * Satu baris ledger dipotret saat pemberiannya, jadi nilai per satuan ikut
     * dibulatkan sebagai kunci: pegawai yang produknya berpoin 5 lalu berubah jadi
     * 7 akan punya dua baris, dan itu memang dua angk berbeda dalam gaji.
     *
     * @return array<int, list<array<string, mixed>>> user_id => daftar produk
     */
    public function perPegawai(array $f, array $juragan_ids = []): array
    {
        [$where, $bind] = $this->saring($f, $juragan_ids);

        $baris = $this->db->query('SELECT p.user_id, p.kode, p.ukuran, p.poin_satuan,'
            . ' COALESCE(SUM(p.qty), 0) AS unit,'
            . ' COUNT(DISTINCT p.invoice_id) AS nota,'
            . ' COALESCE(SUM(p.poin_total), 0) AS poin'
            . ' FROM `' . $this->t('poin_penjualan') . '` p WHERE ' . $where
            . ' GROUP BY p.user_id, p.kode, p.ukuran, p.poin_satuan'
            . ' ORDER BY p.user_id ASC, poin DESC, unit DESC, kode ASC', $bind)->getResultArray();

        $petakan = [];

        foreach ($baris as $b) {
            $petakan[(int) $b['user_id']][] = $b;
        }

        return $petakan;
    }

    /**
     * Rincian per item penjualan (drill-down), urut dari pemberian terbaru.
     *
     * @return list<array<string, mixed>>
     */
    public function rincian(array $f, array $juragan_ids = [], int $limit = 50, int $offset = 0): array
    {
        [$where, $bind] = $this->saring($f, $juragan_ids);

        return $this->db->query('SELECT p.id_poin, p.kode, p.ukuran, p.qty, p.poin_satuan, p.poin_total,'
            . ' p.created_at, p.invoice_id, p.juragan_id, p.user_id,'
            . ' COALESCE(u.name, p.user_id) AS nama, o.seri,'
            . ' j.nama_juragan'
            . ' FROM `' . $this->t('poin_penjualan') . '` p'
            . ' LEFT JOIN `' . $this->t('user') . '` u ON u.id = p.user_id'
            . ' LEFT JOIN `' . $this->t('invoice') . '` o ON o.id_invoice = p.invoice_id'
            . ' LEFT JOIN `' . $this->t('juragan') . '` j ON j.id_juragan = p.juragan_id'
            . ' WHERE ' . $where . ' ORDER BY p.created_at DESC, p.id_poin DESC LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset, $bind)
            ->getResultArray();
    }

    /** Jumlah baris rincian untuk pager. */
    public function rincianJumlah(array $f, array $juragan_ids = []): int
    {
        [$where, $bind] = $this->saring($f, $juragan_ids);

        return (int) $this->db->query('SELECT COUNT(*) AS n FROM `' . $this->t('poin_penjualan')
            . '` p WHERE ' . $where, $bind)->getFirstRow()->n;
    }

    /** Daftar pegawai yang pernah mendapat poin, untuk filter dropdown. */
    public function pegawai(array $juragan_ids = []): array
    {
        [$where, $bind] = $this->saring([], $juragan_ids);

        return $this->db->query('SELECT DISTINCT p.user_id, COALESCE(u.name, p.user_id) AS nama'
            . ' FROM `' . $this->t('poin_penjualan') . '` p'
            . ' LEFT JOIN `' . $this->t('user') . '` u ON u.id = p.user_id'
            . ' WHERE ' . $where . ' ORDER BY nama ASC', $bind)
            ->getResultArray();
    }

    private function t(string $nama): string
    {
        return $this->db->prefixTable($nama);
    }
}
