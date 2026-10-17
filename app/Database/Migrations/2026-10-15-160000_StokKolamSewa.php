<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Stok dipisah dua kolam: unit yang boleh dijual dan unit yang boleh disewa.
 *
 * Kolom `stok` yang lama tetap ada dan sekarang berarti "unit untuk dijual" —
 * tidak ada data yang dipindah, jadi hitungan gudang lama tetap terbaca. Kolam
 * sewa mulai dari nol dan diisi manual per varian, sama seperti kolom stok.
 *
 * `harga_sewa` ikut dibuat di sini karena menyewakan barang bukan menjualnya:
 * harga yang muncul di form orderan tipe Sewa harus bisa berbeda dari harga jual.
 * Nilainya 0 berarti "belum diatur", dan 0 tidak dipakai sebagai harga — jalur
 * tulis orderan memakainya hanya saat kolamnya sewa.
 */
class StokKolamSewa extends Migration
{
    public function up()
    {
        if (! $this->punyaKolom('stok_sewa')) {
            $this->db->query('ALTER TABLE ' . $this->t('stok')
                . ' ADD COLUMN stok_sewa INT(11) NOT NULL DEFAULT 0 AFTER stok');
        }

        if (! $this->punyaKolom('harga_sewa')) {
            $this->db->query('ALTER TABLE ' . $this->t('stok')
                . ' ADD COLUMN harga_sewa INT(10) UNSIGNED NOT NULL DEFAULT 0 AFTER harga');
        }
    }

    public function down()
    {
        foreach (['stok_sewa', 'harga_sewa'] as $kolom) {
            if ($this->punyaKolom($kolom)) {
                $this->db->query('ALTER TABLE ' . $this->t('stok') . ' DROP COLUMN ' . $kolom);
            }
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
            [$this->t('stok'), $kolom])->getNumRows() > 0;
    }
}
