<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Angka-angka halaman Laporan.
 *
 * Semua jenis laporan memakai satu klausul filter (klausul()), jadi dua laporan
 * untuk periode yang sama tidak mungkin memberi jumlah orderan yang berbeda.
 *
 * Angka uang ditulis dua kolom dan keduanya disengaja:
 *  - `masuk`    = hanya pembayaran yang sudah dicek (status 3). Ini angka yang
 *                 sama dengan kartu invoice dan tabel dasbor dari TAHAP 7/10.
 *  - `tercatat` = semua catatan pembayaran, termasuk transfer yang belum dicek
 *                 dan yang dinyatakan tidak ada. Ini cara hitung lama yang dipakai
 *                 view kpi_* dan crm_pelanggan, jadi angka lama masih bisa dibaca.
 */
class LaporanModel extends Model
{
    protected $table      = 'invoice';
    protected $primaryKey = 'id_invoice';

    /** Baris terbanyak yang ditampilkan satu tabel laporan. */
    public const KAPASITAS = 120;

    /** Rentang sepanjang ini masih enak dibaca per hari. */
    private const BATAS_HARIAN = 62;

    /**
     * Orderan masuk per hari (rentang pendek) atau per bulan (rentang panjang).
     *
     * @return array{periode:string, baris:array, ringkas:array}
     */
    public function pesanan(array $juragan_ids, array $f): array
    {
        [$dasar, $bind] = $this->basis($juragan_ids, $f);

        $harian = $this->selisih($f) <= self::BATAS_HARIAN;
        $sumbu  = $harian ? 't.tanggal_pesan' : "DATE_FORMAT(t.tanggal_pesan, '%Y-%m')";

        $baris = $this->db->query('SELECT ' . $sumbu . ' AS periode, COUNT(*) AS orderan,'
            . ' COUNT(DISTINCT t.pemesan_id) AS pelanggan, SUM(t.tagihan) AS tagihan,'
            . ' SUM(t.masuk) AS masuk, SUM(t.tercatat) AS tercatat,'
            . ' SUM(GREATEST(t.tagihan - t.masuk, 0)) AS sisa,'
            . " SUM(t.status_pembayaran IN ('5', '6')) AS lunas,"
            . " SUM(t.status_pengiriman = '3') AS terkirim"
            . ' FROM (' . $dasar . ') t GROUP BY ' . $sumbu
            . ' ORDER BY periode DESC LIMIT ' . self::KAPASITAS, $bind)->getResultArray();

        return [
            'periode' => $harian ? 'hari' : 'bulan',
            'baris'   => $this->bilatkan($baris, ['orderan', 'pelanggan', 'tagihan', 'masuk', 'tercatat', 'sisa', 'lunas', 'terkirim']),
            'ringkas' => $this->ringkas($juragan_ids, $f),
        ];
    }

    /**
     * Nilai orderan dibanding dana yang masuk, per bulan.
     *
     * @return array{baris:array, ringkas:array}
     */
    public function pendapatan(array $juragan_ids, array $f): array
    {
        [$dasar, $bind] = $this->basis($juragan_ids, $f);

        $baris = $this->db->query("SELECT DATE_FORMAT(t.tanggal_pesan, '%Y-%m') AS periode, COUNT(*) AS orderan,"
            . ' SUM(t.tagihan) AS tagihan, SUM(t.masuk) AS masuk, SUM(t.tercatat) AS tercatat,'
            . ' SUM(GREATEST(t.tagihan - t.masuk, 0)) AS sisa,'
            . " SUM(t.status_pembayaran IN ('5', '6')) AS lunas,"
            . " SUM(t.status_pembayaran NOT IN ('5', '6')) AS belum_lunas"
            . ' FROM (' . $dasar . ') t GROUP BY periode ORDER BY periode DESC LIMIT ' . self::KAPASITAS, $bind)->getResultArray();

        return [
            'baris'   => $this->bilatkan($baris, ['orderan', 'tagihan', 'masuk', 'tercatat', 'sisa', 'lunas', 'belum_lunas']),
            'ringkas' => $this->ringkas($juragan_ids, $f),
        ];
    }

    /**
     * Semua catatan pembayaran pada rentang ini, diurut dari transfer terbaru.
     *
     * Tanggal pada laporan ini adalah tanggal transfer, bukan tanggal pesan,
     * supaya pembayaran lama yang baru dicek hari ini tetap muncul.
     *
     * @return array{baris:array, ringkas:array}
     */
    public function pembayaran(array $juragan_ids, array $f): array
    {
        [$where, $bind] = $this->klausul($juragan_ids, $f, 'bayar');

        $baris = $this->db->query('SELECT p.id_pembayaran, p.total_pembayaran, p.status, p.tanggal_pembayaran,'
            . ' p.tanggal_cek, o.seri, o.juragan_id, o.status_pembayaran, j.juragan AS slug, j.nama_juragan,'
            . ' pl.nama_pelanggan, b.nama_bank, b.atas_nama'
            . ' FROM `' . $this->db->prefixTable('pembayaran') . '` p'
            . ' JOIN `' . $this->db->prefixTable('invoice') . '` o ON o.id_invoice = p.invoice_id'
            . ' LEFT JOIN `' . $this->db->prefixTable('juragan') . '` j ON j.id_juragan = o.juragan_id'
            . ' LEFT JOIN `' . $this->db->prefixTable('pelanggan') . '` pl ON pl.id_pelanggan = o.pemesan_id'
            . ' LEFT JOIN `' . $this->db->prefixTable('bank') . '` b ON b.id_bank = p.sumber_dana'
            . ' WHERE ' . $where . ' ORDER BY p.tanggal_pembayaran DESC, p.id_pembayaran DESC LIMIT ' . self::KAPASITAS,
            $bind)->getResultArray();

        $angka = $this->db->query('SELECT COUNT(*) AS baris,'
            . ' COALESCE(SUM(p.total_pembayaran), 0) AS tercatat,'
            . " COALESCE(SUM(CASE WHEN p.status = '3' THEN p.total_pembayaran ELSE 0 END), 0) AS masuk,"
            . " COALESCE(SUM(CASE WHEN p.status = '1' THEN p.total_pembayaran ELSE 0 END), 0) AS belum,"
            . " COALESCE(SUM(CASE WHEN p.status = '2' THEN p.total_pembayaran ELSE 0 END), 0) AS ditolak,"
            . " SUM(p.status = '1') AS menunggu,"
            . " SUM(p.status = '2') AS salah"
            . ' FROM `' . $this->db->prefixTable('pembayaran') . '` p'
            . ' JOIN `' . $this->db->prefixTable('invoice') . '` o ON o.id_invoice = p.invoice_id'
            . ' WHERE ' . $where, $bind)->getFirstRow();

        return [
            'baris'   => $this->bilatkan($baris, ['total_pembayaran', 'status', 'tanggal_pembayaran', 'tanggal_cek', 'juragan_id']),
            'ringkas' => array_map('intval', (array) $angka),
        ];
    }

    /**
     * Orderan yang belum lunas, paling besar lebih dulu, plus umur tunggakan.
     *
     * @return array{baris:array, umur:array, ringkas:array}
     */
    public function piutang(array $juragan_ids, array $f): array
    {
        [$dasar, $bind] = $this->piutangDasar($juragan_ids, $f);

        $baris = $this->db->query('SELECT t.id_invoice, t.seri, t.slug, t.nama_juragan, t.nama_pelanggan,'
            . ' t.tanggal_pesan, t.deadline, t.status_pembayaran, t.tagihan, t.masuk, t.sisa, t.umur'
            . ' FROM (' . $dasar . ') t ORDER BY t.sisa DESC, t.umur DESC LIMIT ' . self::KAPASITAS, $bind)->getResultArray();

        $umur = $this->db->query('SELECT CASE WHEN t.umur <= 30 THEN \'1-30 hari\' WHEN t.umur <= 60 THEN \'31-60 hari\''
            . ' WHEN t.umur <= 90 THEN \'61-90 hari\' ELSE \'lebih dari 90 hari\' END AS keranjang,'
            . ' COUNT(*) AS orderan, SUM(t.sisa) AS sisa'
            . ' FROM (' . $dasar . ') t GROUP BY keranjang ORDER BY MIN(t.umur) DESC', $bind)->getResultArray();

        $angka = $this->db->query('SELECT COUNT(*) AS orderan, COALESCE(SUM(t.sisa), 0) AS sisa,'
            . ' COALESCE(MAX(t.umur), 0) AS tertua FROM (' . $dasar . ') t', $bind)->getFirstRow();

        return [
            'baris'   => $this->bilatkan($baris, ['id_invoice', 'tagihan', 'masuk', 'sisa', 'umur', 'status_pembayaran']),
            'umur'    => $this->bilatkan($umur, ['orderan', 'sisa']),
            'ringkas' => array_map('intval', (array) $angka),
        ];
    }

    /**
     * Orderan berhenti di tahap mana, plus lama dari pesan sampai tahap terakhir.
     *
     * Tahap 0 berarti orderan belum pernah masuk antrean kerja.
     *
     * Lama per tahap sengaja tidak dihitung: pada data lama tanggal_masuk dan
     * tanggal_selesai ditulis bersamaan (34.226 dari 34.233 baris), jadi angkanya
     * selalu nol dan hanya membingungkan. Yang dihitung di sini lama satu orderan
     * dari tanggal pesan sampai pencatatan tahap terakhir.
     *
     * @return array{baris:array, lama:array, ringkas:array}
     */
    public function produksi(array $juragan_ids, array $f): array
    {
        [$dasar, $bind] = $this->basis($juragan_ids, $f);

        $status = $this->db->prefixTable('invoice_status');

        $sebaran = $this->db->query('SELECT IFNULL(s.tahap, 0) AS tahap, COUNT(*) AS orderan,'
            . ' SUM(t.tagihan) AS tagihan, SUM(GREATEST(t.tagihan - t.masuk, 0)) AS sisa'
            . ' FROM (' . $dasar . ') t'
            . ' LEFT JOIN (SELECT invoice_id, MAX(CAST(status AS UNSIGNED)) AS tahap FROM `' . $status . '`'
            . ' WHERE tanggal_masuk IS NOT NULL GROUP BY invoice_id) s ON s.invoice_id = t.id_invoice'
            . ' GROUP BY IFNULL(s.tahap, 0) ORDER BY tahap', $bind)->getResultArray();

        // berapa orderan pernah singgah di tahap ini, dan berapa yang sudah ditutup
        $singgah = $this->db->query('SELECT CAST(k.status AS UNSIGNED) AS tahap, COUNT(*) AS dikerjakan,'
            . ' SUM(k.tanggal_selesai IS NOT NULL) AS beres'
            . ' FROM `' . $status . '` k'
            . ' JOIN (SELECT id_invoice FROM (' . $dasar . ') o) u ON u.id_invoice = k.invoice_id'
            . ' WHERE k.tanggal_masuk IS NOT NULL GROUP BY tahap ORDER BY tahap', $bind)->getResultArray();

        $penuntun = [];

        foreach ($singgah as $s) {
            $penuntun[(int) $s['tahap']] = $s;
        }

        $daftar = [];

        foreach ($sebaran as $s) {
            $no   = (int) $s['tahap'];
            $daftar[] = [
                'tahap'      => $no,
                'orderan'    => (int) $s['orderan'],
                'tagihan'    => (int) $s['tagihan'],
                'sisa'       => (int) $s['sisa'],
                'dikerjakan' => (int) ($penuntun[$no]['dikerjakan'] ?? 0),
                'beres'      => (int) ($penuntun[$no]['beres'] ?? 0),
            ];
        }

        $lama = $this->db->query('SELECT CASE WHEN d.hari <= 7 THEN \'sampai 7 hari\''
            . ' WHEN d.hari <= 14 THEN \'8-14 hari\' WHEN d.hari <= 30 THEN \'15-30 hari\''
            . ' ELSE \'lebih dari 30 hari\' END AS keranjang,'
            . ' COUNT(*) AS orderan, ROUND(AVG(d.hari)) AS rata'
            . ' FROM (SELECT GREATEST(0, DATEDIFF(FROM_UNIXTIME(k.terakhir), t.tanggal_pesan)) AS hari'
            . ' FROM (' . $dasar . ') t'
            . ' JOIN (SELECT invoice_id, MAX(tanggal_selesai) AS terakhir FROM `' . $status . '`'
            . ' GROUP BY invoice_id) k ON k.invoice_id = t.id_invoice) d'
            . ' GROUP BY keranjang ORDER BY MIN(d.hari)', $bind)->getResultArray();

        return [
            'baris'   => $daftar,
            'lama'    => $this->bilatkan($lama, ['orderan', 'rata']),
            'ringkas' => $this->ringkas($juragan_ids, $f),
        ];
    }

    /**
     * Produk dengan penjualan terbesar pada rentang ini.
     *
     * Nama produk dibaca dari order_dibeli.kode karena produk_id lama belum tentu
     * punya jenis (order_produk.id_jenis masih kosong untuk semua baris).
     *
     * @return array{baris:array, ringkas:array}
     */
    public function produk(array $juragan_ids, array $f): array
    {
        [$dasar, $bind] = $this->basis($juragan_ids, $f);

        $baris = $this->db->query('SELECT IFNULL(NULLIF(x.kode, \'\'), \'(tanpa kode)\') AS produk,'
            . ' COUNT(DISTINCT x.invoice_id) AS orderan, SUM(x.qty) AS qty, SUM(x.qty * x.harga) AS nilai'
            . ' FROM `' . $this->db->prefixTable('dibeli') . '` x'
            . ' JOIN (SELECT id_invoice FROM (' . $dasar . ') o) u ON u.id_invoice = x.invoice_id'
            . ' GROUP BY produk ORDER BY nilai DESC LIMIT ' . self::KAPASITAS, $bind)->getResultArray();

        $angka = $this->db->query('SELECT COUNT(*) AS baris, COALESCE(SUM(x.qty), 0) AS qty,'
            . ' COALESCE(SUM(x.qty * x.harga), 0) AS nilai'
            . ' FROM `' . $this->db->prefixTable('dibeli') . '` x'
            . ' JOIN (SELECT id_invoice FROM (' . $dasar . ') o) u ON u.id_invoice = x.invoice_id', $bind)->getFirstRow();

        return [
            'baris'   => $this->bilatkan($baris, ['orderan', 'qty', 'nilai']),
            'ringkas' => array_map('intval', (array) $angka),
        ];
    }

    /**
     * Pelanggan dengan orderan terbesar pada rentang ini.
     *
     * Nama dibaca apa adanya: data lama belum tentu menggabungkan pelanggan yang
     * sama, jadi satu nama bisa muncul lebih dari satu baris.
     *
     * @return array{baris:array, ringkas:array}
     */
    public function pelanggan(array $juragan_ids, array $f): array
    {
        [$dasar, $bind] = $this->basis($juragan_ids, $f);

        $baris = $this->db->query('SELECT t.pemesan_id, IFNULL(NULLIF(t.nama_pelanggan, \'\'), \'(tanpa nama)\') AS pelanggan,'
            . ' COUNT(*) AS orderan, SUM(t.tagihan) AS tagihan, SUM(t.masuk) AS masuk,'
            . ' SUM(GREATEST(t.tagihan - t.masuk, 0)) AS sisa,'
            . " SUM(t.status_pembayaran IN ('5', '6')) AS lunas,"
            . ' MIN(t.tanggal_pesan) AS pertama, MAX(t.tanggal_pesan) AS terakhir'
            . ' FROM (' . $dasar . ') t GROUP BY t.pemesan_id, pelanggan ORDER BY tagihan DESC LIMIT ' . self::KAPASITAS,
            $bind)->getResultArray();

        $angka = $this->db->query('SELECT COUNT(DISTINCT t.pemesan_id) AS pelanggan, COUNT(*) AS orderan,'
            . ' SUM(t.tagihan) AS tagihan, SUM(GREATEST(t.tagihan - t.masuk, 0)) AS sisa'
            . ' FROM (' . $dasar . ') t', $bind)->getFirstRow();

        return [
            'baris'   => $this->bilatkan($baris, ['pemesan_id', 'orderan', 'tagihan', 'masuk', 'sisa', 'lunas']),
            'ringkas' => array_map('intval', (array) $angka),
        ];
    }

    /**
     * Total satu set orderan tersaring: jumlah, nilai, dan dua cara hitung uang.
     *
     * Dipakai kartu ringkasan di atas tiap laporan uang, jadi tidak ada laporan
     * yang menghitung totalnya sendiri-sendiri.
     */
    public function ringkas(array $juragan_ids, array $f): array
    {
        [$dasar, $bind] = $this->basis($juragan_ids, $f);

        $baris = $this->db->query('SELECT COUNT(*) AS orderan,'
            . ' COUNT(DISTINCT t.pemesan_id) AS pelanggan,'
            . ' COALESCE(SUM(t.tagihan), 0) AS tagihan, COALESCE(SUM(t.masuk), 0) AS masuk,'
            . ' COALESCE(SUM(t.tercatat), 0) AS tercatat,'
            . ' COALESCE(SUM(GREATEST(t.tagihan - t.masuk, 0)), 0) AS sisa,'
            . " COALESCE(SUM(t.status_pembayaran IN ('5', '6')), 0) AS lunas,"
            . " COALESCE(SUM(t.status_pengiriman = '3'), 0) AS terkirim"
            . ' FROM (' . $dasar . ') t', $bind)->getFirstRow();

        $angka = array_map('intval', (array) $baris);

        $angka['rata'] = $angka['orderan'] > 0 ? (int) round($angka['tagihan'] / $angka['orderan']) : 0;

        return $angka;
    }

    /**
     * Kode produk yang benar-benar pernah dijual, untuk isian filter produk.
     *
     * Daftar dibaca dari order_dibeli, bukan dari master produk: kode lama ada yang
     * tidak punya master, dan listing master tidak menjamin kodenya muncul di sini.
     * Yang paling sering dijual diambil lebih dulu supaya isian singkat terbaca.
     *
     * @return array<int,string>
     */
    public function daftarKode(array $juragan_ids, int $batas = 60): array
    {
        $ids  = array_values(array_filter(array_map('intval', $juragan_ids)));
        $batas = max(1, $batas);

        $baris = $this->db->query('SELECT x.kode FROM `' . $this->db->prefixTable('dibeli') . '` x'
            . ' JOIN `' . $this->db->prefixTable('invoice') . '` o ON o.id_invoice = x.invoice_id'
            . ' WHERE o.deleted_at IS NULL AND o.juragan_id IN (' . ($ids === [] ? '0' : implode(',', $ids)) . ')'
            . " AND x.kode <> '' GROUP BY x.kode ORDER BY COUNT(*) DESC LIMIT " . $batas)->getResultArray();

        return array_column($baris, 'kode');
    }

    /**
     * Query per-order tersaring: id, tanggal, tenggat, nama, dan tiga angka uang.
     *
     * @return array{0:string,1:array} sql turunan dan bind-nya
     */
    private function basis(array $juragan_ids, array $f, string $sumbu = 'pesan'): array
    {
        [$where, $bind] = $this->klausul($juragan_ids, $f, $sumbu);

        $dibeli    = $this->db->prefixTable('dibeli');
        $biaya     = $this->db->prefixTable('biaya');
        $pembayaran = $this->db->prefixTable('pembayaran');

        $sql = 'SELECT o.id_invoice, o.seri, o.tanggal_pesan, o.deadline, o.juragan_id, o.pemesan_id,'
            . ' o.status_pembayaran, o.status_pengiriman, j.juragan AS slug, j.nama_juragan,'
            . ' pl.nama_pelanggan,'
            . ' COALESCE((SELECT SUM(d.qty * d.harga) FROM `' . $dibeli . '` d'
            . ' WHERE d.invoice_id = o.id_invoice), 0)'
            . ' + COALESCE((SELECT SUM(c.nominal) FROM `' . $biaya . '` c'
            . ' WHERE c.invoice_id = o.id_invoice), 0) AS tagihan,'
            . " COALESCE((SELECT SUM(y.total_pembayaran) FROM `" . $pembayaran . "` y"
            . " WHERE y.invoice_id = o.id_invoice AND y.status = '3'), 0) AS masuk,"
            . " COALESCE((SELECT SUM(y.total_pembayaran) FROM `" . $pembayaran . "` y"
            . ' WHERE y.invoice_id = o.id_invoice), 0) AS tercatat'
            . ' FROM `' . $this->db->prefixTable('invoice') . '` o'
            . ' LEFT JOIN `' . $this->db->prefixTable('juragan') . '` j ON j.id_juragan = o.juragan_id'
            . ' LEFT JOIN `' . $this->db->prefixTable('pelanggan') . '` pl ON pl.id_pelanggan = o.pemesan_id'
            . ' WHERE ' . $where;

        return [$sql, $bind];
    }

    /** Baris piutang: hanya orderan belum lunas yang sisanya masih ada. */
    private function piutangDasar(array $juragan_ids, array $f): array
    {
        [$dasar, $bind] = $this->basis($juragan_ids, $f);

        $sql = 'SELECT t.id_invoice, t.seri, t.slug, t.nama_juragan, t.nama_pelanggan, t.tanggal_pesan,'
            . ' t.deadline, t.status_pembayaran, t.tagihan, t.masuk,'
            . ' GREATEST(t.tagihan - t.masuk, 0) AS sisa,'
            . ' DATEDIFF(CURDATE(), t.tanggal_pesan) AS umur'
            . ' FROM (' . $dasar . ') t'
            . " WHERE t.status_pembayaran NOT IN ('5', '6') AND t.tagihan - t.masuk > 0";

        return [$sql, $bind];
    }

    /**
     * Klausul filter yang sama untuk semua laporan.
     *
     * $sumbu 'pesan' membatasi pada tanggal pesan orderan; 'bayar' membatasi pada
     * tanggal transfer sehingga laporan pembayaran bisa dibaca per uang masuk.
     *
     * @return array{0:string,1:array}
     */
    private function klausul(array $juragan_ids, array $f, string $sumbu = 'pesan'): array
    {
        $ids = array_values(array_filter(array_map('intval', $juragan_ids)));

        $sql  = 'o.deleted_at IS NULL AND o.juragan_id IN (' . ($ids === [] ? '0' : implode(',', $ids)) . ')';
        $bind = [];

        // satu toko boleh dipilih sendiri, tapi hanya kalau memang milik pemakai
        $juragan = (int) ($f['juragan'] ?? 0);

        if ($juragan > 0 && in_array($juragan, $ids, true)) {
            $sql .= ' AND o.juragan_id = ' . $juragan;
        }

        if ($sumbu === 'bayar') {
            $sql .= ' AND FROM_UNIXTIME(p.tanggal_pembayaran, "%Y-%m-%d") BETWEEN :awal: AND :akhir:';
        } else {
            $sql .= ' AND o.tanggal_pesan BETWEEN :awal: AND :akhir:';
        }

        $bind['awal']  = $f['awal'];
        $bind['akhir'] = $f['akhir'];

        $kode = trim((string) ($f['kode'] ?? ''));

        if ($kode !== '') {
            $sql .= ' AND EXISTS (SELECT 1 FROM `' . $this->db->prefixTable('dibeli') . '` x'
                . ' WHERE x.invoice_id = o.id_invoice AND x.kode LIKE :kode:)';
            $bind['kode'] = $this->liar($kode) . '%';
        }

        $tahap = (int) ($f['tahap'] ?? -1);

        if ($tahap >= 0) {
            $status = $this->db->prefixTable('invoice_status');
            $ada    = 'SELECT 1 FROM `' . $status . '` s WHERE s.invoice_id = o.id_invoice'
                . ' AND s.tanggal_masuk IS NOT NULL';

            $sql .= $tahap === 0
                ? ' AND NOT EXISTS (' . $ada . ')'
                : ' AND EXISTS (' . $ada . ' AND s.status = ' . $tahap . ')';
        }

        $kategori = (string) ($f['bayar'] ?? '');

        if ($kategori !== '' && $kategori !== 'semua') {
            helper('fungsi');

            $daftar = array_map('intval', kategori_pembayaran()[$kategori]['status'] ?? []);

            if ($daftar !== []) {
                $sql .= ' AND o.status_pembayaran IN (' . $this->daftar($daftar) . ')';
            }
        }

        $nama = trim((string) ($f['nama'] ?? ''));

        if ($nama !== '') {
            $sql .= ' AND EXISTS (SELECT 1 FROM `' . $this->db->prefixTable('pelanggan') . '` p'
                . ' WHERE p.id_pelanggan = o.pemesan_id AND p.nama_pelanggan LIKE :nama:)';
            $bind['nama'] = '%' . $this->liar($nama) . '%';
        }

        return [$sql, $bind];
    }

    /** Banyak hari dalam rentang tersaring, untuk memutuskan per hari atau per bulan. */
    private function selisih(array $f): int
    {
        $awal  = \DateTimeImmutable::createFromFormat('Y-m-d', $f['awal']) ?: new \DateTimeImmutable('today');
        $akhir = \DateTimeImmutable::createFromFormat('Y-m-d', $f['akhir']) ?: new \DateTimeImmutable('today');

        return max(0, (int) $awal->diff($akhir)->format('%r%a'));
    }

    /** wildcard LIKE dipakai sebagai teks biasa. */
    private function liar(string $cari): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $cari);
    }

    /** Daftar nilai integer jadi potongan IN (...) yang aman. */
    private function daftar(array $nilai): string
    {
        return implode(',', array_map('intval', $nilai));
    }

    private function bilatkan(array $baris, array $kolom): array
    {
        foreach ($baris as $i => $b) {
            foreach ($kolom as $k) {
                if (array_key_exists($k, $b)) {
                    $baris[$i][$k] = (int) $b[$k];
                }
            }
        }

        return $baris;
    }
}
