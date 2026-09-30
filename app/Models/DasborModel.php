<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Angka-angka untuk halaman Dasbor.
 *
 * Agregat harian dan bulanan dibaca dari view `kpi_order_harian` dan
 * `kpi_order_bulanan` (dibuat waktu perapihan basis data) supaya perhitungan
 * total tidak ditulis ulang di PHP. View ini tidak memakai prefix tabel, jadi
 * semua query di sini ditulis tangan dan nama tabelnya yang diberi prefix.
 */
class DasborModel extends Model
{
    protected $table      = 'invoice';
    protected $primaryKey = 'id_invoice';

    /**
     * Ringkasan transaksi pada satu tanggal pesan.
     *
     * @return array{order:int,pelanggan:int,total:int,dibayar:int,lunas:int,terkirim:int}
     */
    public function satuHari(array $juragan_ids, string $tanggal): array
    {
        $baris = $this->db->query('SELECT COALESCE(SUM(jumlah_order), 0) AS orderan,'
            . ' COALESCE(SUM(jumlah_pelanggan), 0) AS pelanggan,'
            . ' COALESCE(SUM(total), 0) AS total,'
            . ' COALESCE(SUM(dibayar), 0) AS dibayar,'
            . ' COALESCE(SUM(jumlah_lunas), 0) AS lunas,'
            . ' COALESCE(SUM(jumlah_terkirim), 0) AS terkirim'
            . ' FROM kpi_order_harian WHERE tanggal_pesan = :tanggal: AND ' . $this->klausulJuragan($juragan_ids),
            ['tanggal' => $tanggal])->getFirstRow();

        return array_map('intval', (array) $baris);
    }

    /**
     * Deretan bulan terakhir untuk grafik, terurut dari bulan tertua.
     *
     * @return array<int, array{bulan:string,order:int,total:int,dibayar:int,sisa:int}>
     */
    public function trenBulanan(array $juragan_ids, int $jumlah_bulan = 6): array
    {
        $awal = date('Y-m', strtotime('-' . ($jumlah_bulan - 1) . ' months'));

        return $this->db->query('SELECT bulan,'
            . ' SUM(jumlah_order) AS orderan,'
            . ' SUM(total) AS total,'
            . ' SUM(dibayar) AS dibayar,'
            . ' SUM(sisa) AS sisa'
            . ' FROM kpi_order_bulanan WHERE bulan >= :awal: AND ' . $this->klausulJuragan($juragan_ids)
            . ' GROUP BY bulan ORDER BY bulan ASC', ['awal' => $awal])->getResultArray();
    }

    /**
     * Produk dengan nilai penjualan tertinggi dalam beberapa bulan terakhir.
     *
     * @return array<int, array{produk:string,qty:int,nilai:int}>
     */
    public function produkTeratas(array $juragan_ids, int $jumlah_bulan = 3, int $batas = 5): array
    {
        $produk = $this->db->prefixTable('produk');
        $awal   = date('Y-m', strtotime('-' . ($jumlah_bulan - 1) . ' months'));

        return $this->db->query('SELECT MAX(p.kode) AS produk,'
            . ' SUM(k.qty) AS qty, SUM(k.nilai) AS nilai'
            . ' FROM kpi_produk k'
            . ' JOIN `' . $produk . '` p ON p.id_produk = k.id_produk'
            . ' WHERE k.bulan >= :awal: AND ' . $this->klausulJuragan($juragan_ids)
            . ' GROUP BY k.produk_kunci ORDER BY nilai DESC LIMIT ' . max(1, $batas), ['awal' => $awal])->getResultArray();
    }

    /**
     * Transaksi yang menunggu tindakan pemilik toko.
     *
     * @return array{belum_bayar:int,perlu_dicek:int,dicicil:int,belum_proses:int,belum_terkirim:int,transfer:int,nominal_transfer:int}
     */
    public function menungguAksi(array $juragan_ids): array
    {
        $invoice    = $this->db->prefixTable('invoice');
        $pembayaran = $this->db->prefixTable('pembayaran');
        $klausul    = $this->klausulJuragan($juragan_ids);

        $tagihan = $this->db->query('SELECT'
            . ' COALESCE(SUM(status_pembayaran = \'1\'), 0) AS belum_bayar,'
            . ' COALESCE(SUM(status_pembayaran IN (\'2\', \'3\')), 0) AS perlu_dicek,'
            . ' COALESCE(SUM(status_pembayaran = \'4\'), 0) AS dicicil,'
            . ' COALESCE(SUM(status_pesanan = \'1\'), 0) AS belum_proses,'
            . ' COALESCE(SUM(status_pengiriman <> \'3\' AND status_pesanan = \'3\'), 0) AS belum_terkirim'
            . ' FROM `' . $invoice . '` WHERE deleted_at IS NULL AND ' . $klausul)->getFirstRow();

        $transfer = $this->db->query('SELECT COUNT(*) AS transfer,'
            . ' COALESCE(SUM(p.total_pembayaran), 0) AS nominal'
            . ' FROM `' . $pembayaran . '` p'
            . ' JOIN `' . $invoice . '` o ON o.id_invoice = p.invoice_id AND o.deleted_at IS NULL'
            . ' WHERE p.status = \'1\' AND ' . $klausul)->getFirstRow();

        return array_map('intval', array_merge((array) $tagihan, (array) $transfer));
    }

    /**
     * Orderan terbaru, lengkap dengan nilai tagihannya.
     *
     * Nilai dihitung dari pembelian + biaya tambahan lalu dikurangi yang sudah
     * masuk, persis seperti cara view kpi_* menghitung, dan hanya untuk baris
     * yang ditampilkan di sini.
     *
     * @return array<int, array<string, mixed>>
     */
    public function transaksiTerbaru(array $juragan_ids, int $batas = 8): array
    {
        $invoice   = $this->db->prefixTable('invoice');
        $juragan   = $this->db->prefixTable('juragan');
        $pelanggan = $this->db->prefixTable('pelanggan');

        $baris = $this->db->query('SELECT o.id_invoice, o.seri, o.tanggal_pesan, o.status_pesanan,'
            . ' o.status_pembayaran, o.status_pengiriman, o.juragan_id,'
            . ' j.juragan AS slug, j.nama_juragan, pl.nama_pelanggan'
            . ' FROM `' . $invoice . '` o'
            . ' JOIN `' . $juragan . '` j ON j.id_juragan = o.juragan_id'
            . ' LEFT JOIN `' . $pelanggan . '` pl ON pl.id_pelanggan = o.pemesan_id'
            . ' WHERE o.deleted_at IS NULL AND ' . $this->klausulJuragan($juragan_ids)
            . ' ORDER BY o.tanggal_pesan DESC, o.id_invoice DESC LIMIT ' . max(1, $batas))->getResultArray();

        return $baris === [] ? [] : $this->pasangNilai($baris);
    }

    /**
     * Tambahkan kolom total, dibayar, dan sisa pada sekumpulan orderan.
     */
    private function pasangNilai(array $baris): array
    {
        $ids       = array_map('intval', array_column($baris, 'id_invoice'));
        $dibeli    = $this->db->prefixTable('dibeli');
        $biaya     = $this->db->prefixTable('biaya');
        $pembayaran = $this->db->prefixTable('pembayaran');
        $daftar    = implode(',', $ids);

        $barang = $this->petakan('SELECT invoice_id, SUM(qty * harga) AS nilai FROM `' . $dibeli
            . '` WHERE invoice_id IN (' . $daftar . ') GROUP BY invoice_id');

        $tambahan = $this->petakan('SELECT invoice_id, SUM(nominal) AS nilai FROM `' . $biaya
            . '` WHERE invoice_id IN (' . $daftar . ') GROUP BY invoice_id');

        $masuk = $this->petakan('SELECT invoice_id, SUM(total_pembayaran) AS nilai FROM `' . $pembayaran
            . '` WHERE invoice_id IN (' . $daftar . ') GROUP BY invoice_id');

        foreach ($baris as $i => $b) {
            $id    = (int) $b['id_invoice'];
            $total = (int) ($barang[$id] ?? 0) + (int) ($tambahan[$id] ?? 0);

            $baris[$i]['total']  = $total;
            $baris[$i]['dibayar'] = (int) ($masuk[$id] ?? 0);
            $baris[$i]['sisa']   = $total - $baris[$i]['dibayar'];
        }

        return $baris;
    }

    /** Hasil query agregat jadi peta invoice_id => nilai. */
    private function petakan(string $sql): array
    {
        $peta = [];

        foreach ($this->db->query($sql)->getResultArray() as $h) {
            $peta[(int) $h['invoice_id']] = (int) $h['nilai'];
        }

        return $peta;
    }

    /**
     * Batas juragan untuk query tangan. IN () kosong adalah SQL invalid,
     * jadi pakai (0) yang tidak akan pernah cocok.
     */
    private function klausulJuragan(array $juragan_ids): string
    {
        $ids = array_values(array_filter(array_map('intval', $juragan_ids)));

        return 'juragan_id IN (' . ($ids === [] ? '0' : implode(',', $ids)) . ')';
    }
}
