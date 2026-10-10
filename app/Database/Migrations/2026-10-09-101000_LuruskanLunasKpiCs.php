<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * "Lunas" di view kpi_cs ikut definisi nota valid yang dipakai modul KPI.
 *
 * Kategori pembayaran punya dua nilai lunas: 5 = lunas lebih (uang masuk melebihi
 * tagihan, tetap tidak ada sisa bayar) dan 6 = lunas pas. View lama hanya
 * menghitung 6, jadi jumlah order yang beres pembayaran di bawah laporan
 * sebanyak nota lunas lebih.
 *
 * Hanya kpi_cs yang disentuh. kpi_order_harian dipakai kartu "Lunas" di dasbor
 * transaksi dan angka yang tampil di sana selama ini dibaca sebagai "lunas pas",
 * jadi tidak diubah supaya dasbor lama tetap seperti semula.
 */
class LuruskanLunasKpiCs extends Migration
{
    public function up()
    {
        $this->db->query($this->cs());
    }

    public function down()
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

        $this->db->query(implode("\n", $l));
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
        $l[] = '       SUM(o.status_pembayaran IN ("5", "6")) AS jumlah_lunas';
        $l[] = 'FROM `' . $this->t('invoice') . '` o';
        $l[] = 'LEFT JOIN `' . $this->t('user') . '` u ON u.id = o.user_id';
        $l[] = $this->nilai();
        $l[] = 'WHERE o.deleted_at IS NULL';
        $l[] = 'GROUP BY o.user_id, u.name, u.level, DATE_FORMAT(o.tanggal_pesan, "%Y-%m")';

        return implode("\n", $l);
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

    private function t(string $nama): string
    {
        return $this->db->prefixTable($nama);
    }
}
