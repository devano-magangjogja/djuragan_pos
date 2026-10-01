<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Alur produksi 12 tahap tanpa memindahkan satu baris pun.
 *
 * Nilai enum lama tetap berarti sama persis; yang berubah cuma labelnya di UI
 * (1 Data -> Pesanan Baru, 2 Bahan -> Persiapan, 5 Jahit -> Penjahitan). Lima
 * tahap baru mendapat nomor 8-12 supaya tidak perlu remapping:
 *   8 Pengukuran, 9 Ukuran Lengkap, 10 Pemotongan, 11 Finishing, 12 Siap Diambil.
 *
 * Urutan tampil (1,8,9,2,10,5,3,4,11,6,7,12) sengaja jadi urusan helper
 * status_orderan(), bukan urutan enum, supaya angka yang sudah tersimpan di
 * 34.233 baris lama tidak pernah perlu ditulis ulang.
 */
class TambahTahapProduksi extends Migration
{
    private const BARU = [8, 9, 10, 11, 12];

    public function up()
    {
        $nilai = [];

        for ($i = 1; $i <= 12; $i++) {
            $nilai[] = "'{$i}'";
        }

        $this->db->query('ALTER TABLE ' . $this->t('invoice_status')
            . ' MODIFY COLUMN status ENUM(' . implode(',', $nilai) . ') NULL DEFAULT NULL');
    }

    public function down()
    {
        $tabel = $this->t('invoice_status');
        $pakai = $this->db->query('SELECT COUNT(*) k FROM `' . $tabel . '` WHERE status IN ('
            . implode(',', self::BARU) . ')')->getFirstRow();

        if ((int) $pakai->k > 0) {
            throw new RuntimeException("Masih ada {$pakai->k} baris memakai tahap 8-12. Pindahkan dulu ke tahap lama sebelum rollback.");
        }

        $this->db->query('ALTER TABLE `' . $tabel . '` MODIFY COLUMN status ENUM(\'1\',\'2\',\'3\',\'4\',\'5\',\'6\',\'7\') NULL DEFAULT NULL');
    }

    private function t(string $nama): string
    {
        return $this->db->prefixTable($nama);
    }
}
