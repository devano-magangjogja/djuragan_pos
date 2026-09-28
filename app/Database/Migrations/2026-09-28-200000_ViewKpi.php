<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Sumbers angka untuk dashboard KPI.
 *
 * Nilai sebuah orderan tidak disimpan di satu kolom: total = barang
 * (order_dibeli) + biaya tambahan (order_biaya, ongkir positif dan potongan
 * negatif) - sudah dibayar (order_pembayaran). Fragmen perhitungan itu dibuat
 * sekali di metode nilai() lalu dipakai semua view supaya angkanya pasti sama.
 *
 * Semua view hanya membaca; invoice yang di-soft-delete (423 baris) dikeluarkan.
 */
class ViewKpi extends Migration
{
    public function up()
    {
        $this->db->query($this->harian());
        $this->db->query($this->bulanan());
        $this->db->query($this->produk());
        $this->db->query($this->cs());
        $this->db->query($this->kanal());
    }

    public function down()
    {
        foreach ($this->namaView() as $view) {
            $this->db->query('DROP VIEW IF EXISTS `' . $view . '`');
        }
    }

    /** @return list<string> */
    private function namaView(): array
    {
        return [
            'kpi_order_harian',
            'kpi_order_bulanan',
            'kpi_produk',
            'kpi_cs',
            'kpi_kanal',
        ];
    }

    private function t(string $nama): string
    {
        return $this->db->prefixTable($nama);
    }

    /** Join turunan yang menghitung barang, biaya, dan pembayaran per invoice. */
    private function nilai(string $alias = 'o'): string
    {
        $l = [];
        $l[] = 'LEFT JOIN (SELECT invoice_id, SUM(qty * harga) AS barang, SUM(qty) AS qty';
        $l[] = '           FROM `' . $this->t('dibeli') . '` GROUP BY invoice_id) b';
        $l[] = '      ON b.invoice_id = ' . $alias . '.id_invoice';
        $l[] = 'LEFT JOIN (SELECT invoice_id, SUM(nominal) AS biaya';
        $l[] = '           FROM `' . $this->t('biaya') . '` GROUP BY invoice_id) bc';
        $l[] = '      ON bc.invoice_id = ' . $alias . '.id_invoice';
        $l[] = 'LEFT JOIN (SELECT invoice_id, SUM(total_pembayaran) AS dibayar';
        $l[] = '           FROM `' . $this->t('pembayaran') . '` GROUP BY invoice_id) pm';
        $l[] = '      ON pm.invoice_id = ' . $alias . '.id_invoice';

        return implode("\n", $l);
    }

    private function harian(): string
    {
        $l = [];
        $l[] = 'CREATE OR REPLACE VIEW kpi_order_harian AS';
        $l[] = 'SELECT o.juragan_id, j.juragan, j.nama_juragan, o.tanggal_pesan,';
        $l[] = '       COUNT(*) AS jumlah_order, COUNT(DISTINCT o.pemesan_id) AS jumlah_pelanggan,';
        $l[] = '       COALESCE(SUM(b.qty), 0) AS qty,';
        $l[] = '       COALESCE(SUM(b.barang), 0) AS nilai_barang,';
        $l[] = '       COALESCE(SUM(bc.biaya), 0) AS biaya_tambahan,';
        $l[] = '       COALESCE(SUM(b.barang), 0) + COALESCE(SUM(bc.biaya), 0) AS total,';
        $l[] = '       COALESCE(SUM(pm.dibayar), 0) AS dibayar,';
        $l[] = '       COALESCE(SUM(b.barang), 0) + COALESCE(SUM(bc.biaya), 0) - COALESCE(SUM(pm.dibayar), 0) AS sisa,';
        $l[] = '       SUM(o.status_pembayaran = "6") AS jumlah_lunas,';
        $l[] = '       SUM(o.status_pengiriman = "3") AS jumlah_terkirim';
        $l[] = 'FROM `' . $this->t('invoice') . '` o';
        $l[] = 'JOIN `' . $this->t('juragan') . '` j ON j.id_juragan = o.juragan_id';
        $l[] = $this->nilai();
        $l[] = 'WHERE o.deleted_at IS NULL';
        $l[] = 'GROUP BY o.juragan_id, j.juragan, j.nama_juragan, o.tanggal_pesan';

        return implode("\n", $l);
    }

    private function bulanan(): string
    {
        $l = [];
        $l[] = 'CREATE OR REPLACE VIEW kpi_order_bulanan AS';
        $l[] = 'SELECT o.juragan_id, j.juragan, j.nama_juragan,';
        $l[] = '       DATE_FORMAT(o.tanggal_pesan, "%Y-%m") AS bulan,';
        $l[] = '       COUNT(*) AS jumlah_order,';
        $l[] = '       COUNT(DISTINCT o.pemesan_id) AS pelanggan_aktif,';
        $l[] = '       COUNT(DISTINCT CASE WHEN DATE_FORMAT(cp.order_pertama, "%Y-%m")';
        $l[] = '                               = DATE_FORMAT(o.tanggal_pesan, "%Y-%m")';
        $l[] = '                                THEN o.pemesan_id END) AS pelanggan_baru,';
        $l[] = '       COALESCE(SUM(b.qty), 0) AS qty,';
        $l[] = '       COALESCE(SUM(b.barang), 0) AS nilai_barang,';
        $l[] = '       COALESCE(SUM(bc.biaya), 0) AS biaya_tambahan,';
        $l[] = '       COALESCE(SUM(b.barang), 0) + COALESCE(SUM(bc.biaya), 0) AS total,';
        $l[] = '       COALESCE(SUM(pm.dibayar), 0) AS dibayar,';
        $l[] = '       COALESCE(SUM(b.barang), 0) + COALESCE(SUM(bc.biaya), 0) - COALESCE(SUM(pm.dibayar), 0) AS sisa';
        $l[] = 'FROM `' . $this->t('invoice') . '` o';
        $l[] = 'JOIN `' . $this->t('juragan') . '` j ON j.id_juragan = o.juragan_id';
        $l[] = 'LEFT JOIN crm_pelanggan cp ON cp.id_pelanggan = o.pemesan_id';
        $l[] = $this->nilai();
        $l[] = 'WHERE o.deleted_at IS NULL';
        $l[] = 'GROUP BY o.juragan_id, j.juragan, j.nama_juragan, DATE_FORMAT(o.tanggal_pesan, "%Y-%m")';

        return implode("\n", $l);
    }

    private function produk(): string
    {
        $l = [];
        $l[] = 'CREATE OR REPLACE VIEW kpi_produk AS';
        $l[] = 'SELECT o.juragan_id, DATE_FORMAT(o.tanggal_pesan, "%Y-%m") AS bulan,';
        $l[] = '       p.id_produk, p.kode AS produk, p.kunci AS produk_kunci,';
        $l[] = '       COUNT(DISTINCT d.invoice_id) AS jumlah_order,';
        $l[] = '       SUM(d.qty) AS qty, SUM(d.qty * d.harga) AS nilai';
        $l[] = 'FROM `' . $this->t('dibeli') . '` d';
        $l[] = 'JOIN `' . $this->t('produk') . '` p ON p.id_produk = d.produk_id';
        $l[] = 'JOIN `' . $this->t('invoice') . '` o ON o.id_invoice = d.invoice_id';
        $l[] = 'WHERE o.deleted_at IS NULL';
        $l[] = 'GROUP BY o.juragan_id, DATE_FORMAT(o.tanggal_pesan, "%Y-%m"), p.id_produk, p.kode, p.kunci';

        return implode("\n", $l);
    }

    private function cs(): string
    {
        $l = [];
        $l[] = 'CREATE OR REPLACE VIEW kpi_cs AS';
        $l[] = 'SELECT o.user_id, u.name AS cs, u.level, DATE_FORMAT(o.tanggal_pesan, "%Y-%m") AS bulan,';
        $l[] = '       COUNT(*) AS jumlah_order, COUNT(DISTINCT o.pemesan_id) AS pelanggan_ditangani,';
        $l[] = '       COALESCE(SUM(b.qty), 0) AS qty,';
        $l[] = '       COALESCE(SUM(b.barang), 0) + COALESCE(SUM(bc.biaya), 0) AS total,';
        $l[] = '       COALESCE(SUM(pm.dibayar), 0) AS dibayar,';
        $l[] = '       SUM(o.status_pembayaran = "6") AS jumlah_lunas';
        $l[] = 'FROM `' . $this->t('invoice') . '` o';
        $l[] = 'LEFT JOIN `' . $this->t('user') . '` u ON u.id = o.user_id';
        $l[] = $this->nilai();
        $l[] = 'WHERE o.deleted_at IS NULL';
        $l[] = 'GROUP BY o.user_id, u.name, u.level, DATE_FORMAT(o.tanggal_pesan, "%Y-%m")';

        return implode("\n", $l);
    }

    private function kanal(): string
    {
        $l = [];
        $l[] = 'CREATE OR REPLACE VIEW kpi_kanal AS';
        $l[] = 'SELECT o.juragan_id, DATE_FORMAT(o.tanggal_pesan, "%Y-%m") AS bulan,';
        $l[] = '       COALESCE(a.id, 0) AS asal_id, COALESCE(a.label, "tidak diisi") AS kanal,';
        $l[] = '       COUNT(*) AS jumlah_order, COUNT(DISTINCT o.pemesan_id) AS jumlah_pelanggan,';
        $l[] = '       COALESCE(SUM(b.barang), 0) + COALESCE(SUM(bc.biaya), 0) AS total';
        $l[] = 'FROM `' . $this->t('invoice') . '` o';
        $l[] = 'LEFT JOIN `' . $this->t('label_invoice') . '` li ON li.invoice_id = o.id_invoice';
        $l[] = 'LEFT JOIN `' . $this->t('asal_order') . '` a ON a.id = li.source_id';
        $l[] = $this->nilai();
        $l[] = 'WHERE o.deleted_at IS NULL';
        $l[] = 'GROUP BY o.juragan_id, DATE_FORMAT(o.tanggal_pesan, "%Y-%m"), COALESCE(a.id, 0), COALESCE(a.label, "tidak diisi")';

        return implode("\n", $l);
    }
}
