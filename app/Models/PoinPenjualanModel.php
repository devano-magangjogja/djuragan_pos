<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Ledger poin gaji.
 *
 * Poin diberikan saat nota sudah SELESAI dikerjakan (masuk tab Selesai,
 * status_pengiriman = 3) DAN lunas. Lunas saja tidak cukup: orderan boleh sudah
 * dibayar penuh tapi barangnya belum tentu dikerjakan, dan poin gaji adalah
 * imbalan atas pekerjaan yang sampai ke pelanggan. Salah satu syarat lepas,
 * poin dicabut lagi (lihat sinkron()).
 * Perhitungan dilakukan satu kali lewat sinkron(): pemanggilnya tidak perlu
 * tahu apakah notasinya baru selesai atau sedang balik jadi belum lunas.
 *
 * Yang menerima poin adalah pembuat nota (invoice.user_id), bukan orang yang
 * menekan tombol cek pembayaran. Nilainya dipotret saat pemberian (poin_satuan
 * dan qty), sehingga perubahan poin varian di kemudian hari tidak mengubah
 * gaji yang sudah terlanjur dihitung.
 */
class PoinPenjualanModel extends Model
{
    protected $table         = 'poin_penjualan';
    protected $primaryKey    = 'id_poin';
    protected $returnType    = 'array';
    protected $allowedFields = [
        'invoice_id', 'user_id', 'juragan_id', 'stok_id', 'produk_id',
        'kode', 'ukuran', 'qty', 'poin_satuan', 'poin_total', 'created_at',
    ];
    protected $useTimestamps  = false;

    /** Status pembayaran yang dihitung lunas: 5 (lunas + kelebihan) dan 6 (lunas). */
    private const LUNAS = [5, 6];

    /** Nilai status_pengiriman yang berarti semua barang sudah keluar = tab Selesai. */
    private const SELESAI = '3';

    /**
     * Samakan isi ledger dengan keadaan nota saat ini.
     *
     * Dipanggil setiap kali status pembayaran atau status pengiriman sebuah
     * invoice berubah, jadi pemanggil tidak perlu memutuskan sendiri memberi
     * atau mencabut poin.
     */
    public function sinkron(int $invoice_id): void
    {
        $nota = $this->db->query('SELECT user_id, juragan_id, status_pembayaran, status_pengiriman, status_pesanan, deleted_at'
            . ' FROM `' . $this->t('invoice') . '` WHERE id_invoice = ? LIMIT 1', [$invoice_id])
            ->getRow();

        if ($nota === null) {
            $this->cabut($invoice_id);

            return;
        }

        $lunas    = in_array((int) $nota->status_pembayaran, self::LUNAS, true);
        $selesai  = (string) $nota->status_pengiriman === self::SELESAI;
        $tersimpan = $nota->deleted_at === null;
        $dibatalkan = (string) $nota->status_pesanan === '3';

        if (! $lunas || ! $selesai || ! $tersimpan || $dibatalkan || (int) $nota->user_id <= 0) {
            $this->cabut($invoice_id);

            return;
        }

        // sudah pernah diberi: jangan disentuh lagi supaya snapshot poin yang
        // tercatat waktu itu tetap utuh dan tidak ada pemberian ganda
        if ($this->where('invoice_id', $invoice_id)->countAllResults() > 0) {
            return;
        }

        $baris = $this->rayahPoin($invoice_id, (int) $nota->user_id, (int) $nota->juragan_id);

        if ($baris !== []) {
            $this->insertBatch($baris);
        }
    }

    /** Hapus semua baris ledger milik satu nota (pencabutan poin). */
    public function cabut(int $invoice_id): void
    {
        $this->where('invoice_id', $invoice_id)->delete();
    }

    /**
     * Susun baris ledger dari item-item sebuah nota. Poin tiap item dibaca dari
     * master produk (satu nilai per nama barang, berlaku untuk semua ukuran).
     *
     * @return list<array<string, mixed>>
     */
    private function rayahPoin(int $invoice_id, int $user_id, int $juragan_id): array
    {
        $item = $this->db->query('SELECT produk_id, kode, ukuran, qty'
            . ' FROM `' . $this->t('dibeli') . '` WHERE invoice_id = ?', [$invoice_id])
            ->getResultArray();

        if ($item === []) {
            return [];
        }

        $poin  = $this->poinProduk($item);
        $waktu = time();
        $baris = [];

        foreach ($item as $b) {
            $qty = (int) $b['qty'];

            if ($qty <= 0) {
                continue;
            }

            $produkId = $b['produk_id'] !== null ? (int) $b['produk_id'] : 0;
            $kunci    = $produkId > 0 ? 'pid:' . $produkId : 'kode:' . mb_strtolower(trim((string) $b['kode']));
            $satuan   = $poin[$kunci] ?? 0;

            if ($satuan <= 0) {
                continue;
            }

            $baris[] = [
                'invoice_id'  => $invoice_id,
                'user_id'     => $user_id,
                'juragan_id'  => $juragan_id,
                'produk_id'   => $produkId > 0 ? $produkId : null,
                'kode'        => (string) $b['kode'],
                'ukuran'      => $b['ukuran'] !== null ? (string) $b['ukuran'] : null,
                'qty'         => $qty,
                'poin_satuan' => $satuan,
                'poin_total'  => $qty * $satuan,
                'created_at'  => $waktu,
            ];
        }

        return $baris;
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
     * @param array $f  kunci: awal, akhir (Y-m-d), juragan (int), user (int)
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
