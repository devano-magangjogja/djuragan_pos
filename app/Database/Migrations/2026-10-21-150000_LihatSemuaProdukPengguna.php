<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Penanda "boleh melihat produk semua juragan" untuk akun CS.
 *
 * Pada dasarnya CS hanya boleh membuka katalog dari juragan yang disematkan ke
 * akunnya (relasi table=1). Kolom ini memberi Superadmin jalan untuk melepas
 * batas itu per akun: kalau dinyalakan, daftar produk CS menampilkan katalog
 * dari semua juragan, bukan cuma tokonya sendiri. Hak menulis tetap tidak
 * sentuh — CS memang hanya membaca.
 *
 * Nilainya 0 untuk semua akun lama, jadi perilaku default tidak berubah.
 */
class LihatSemuaProdukPengguna extends Migration
{
    public function up()
    {
        if (! $this->punyaKolom('lihat_semua_produk')) {
            $this->db->query('ALTER TABLE ' . $this->t('user')
                . ' ADD COLUMN lihat_semua_produk TINYINT(1) NOT NULL DEFAULT 0 AFTER status');
        }
    }

    public function down()
    {
        if ($this->punyaKolom('lihat_semua_produk')) {
            $this->db->query('ALTER TABLE ' . $this->t('user') . ' DROP COLUMN lihat_semua_produk');
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
