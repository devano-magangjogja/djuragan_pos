<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Wewenang "boleh menyunting transaksi" untuk akun CS.
 *
 * Sehari-hari CS hanya boleh membuat orderan, mencatat pembayaran, dan
 * mencatat barang sewaan yang kembali. Layar sunting nota, panel proses
 * (tahap produksi), dan koreksi pembayaran ada di sisi Admin. Kolom ini
 * memberi Superadmin jalan untuk membuka jalur itu per akun CS: yang
 * dipilihnya bisa membuka Tracking Transaksi dan mengubah nota milik
 * tokonya sendiri, persis seperti Admin.
 *
 * Yang TIDAK ikut terbuka: menulis orderan lewat layar admin (CS sudah punya
 * formnya sendiri) dan menghapus/membatalkan nota. Dua-duanya tetap khusus
 * admin & superadmin.
 *
 * Nilainya 0 untuk semua akun lama, jadi perilaku default tidak berubah.
 */
class WewenangSuntingTransaksi extends Migration
{
    public function up()
    {
        if (! $this->punyaKolom('sunting_transaksi')) {
            $this->db->query('ALTER TABLE ' . $this->t('user')
                . ' ADD COLUMN sunting_transaksi TINYINT(1) NOT NULL DEFAULT 0 AFTER lihat_semua_produk');
        }
    }

    public function down()
    {
        if ($this->punyaKolom('sunting_transaksi')) {
            $this->db->query('ALTER TABLE ' . $this->t('user') . ' DROP COLUMN sunting_transaksi');
        }
    }

    private function t(string $nama): string
    {
        return $this->db->prefixTable($nama);
    }

    private function punyaKolom(string $kolom): bool
    {
        return $this->db->query('SELECT 1 FROM information_schema.columns
                WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ? LIMIT 1',
            [$this->t('user'), $kolom])->getNumRows() > 0;
    }
}
