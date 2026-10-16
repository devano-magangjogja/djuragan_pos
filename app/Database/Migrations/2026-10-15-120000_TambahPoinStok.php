<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Poin gaji per varian stok.
 *
 * Nilai poin menempel di varian stok (bukan master produk) karena harga jual
 * pun sudah per varian: satu kode bisa punya poin berbeda per ukuran dan per
 * toko. Kolom ini diisi ulang oleh Superadmin lewat halaman Produk; Admin yang
 * menambah barang tidak menyentuhnya, jadi varian baru lahir dengan poin 0
 * sampai Superadmin menentukan angkanya.
 *
 * Poin 0 berarti varian itu tidak menghasilkan poin gaji, sehingga barang lama
 * yang belum ditata tetap aman: penjualnya tidak tiba-tiba dapat poin.
 */
class TambahPoinStok extends Migration
{
    public function up()
    {
        if (! $this->punyaKolom('stok', 'poin')) {
            $this->db->query('ALTER TABLE ' . $this->t('stok')
                . ' ADD COLUMN poin INT(10) UNSIGNED NOT NULL DEFAULT 0 AFTER harga');
        }
    }

    public function down()
    {
        if ($this->punyaKolom('stok', 'poin')) {
            $this->db->query('ALTER TABLE ' . $this->t('stok') . ' DROP COLUMN poin');
        }
    }

    private function t(string $nama): string
    {
        return $this->db->prefixTable($nama);
    }

    private function punyaKolom(string $tabel, string $kolom): bool
    {
        return $this->db->query('SELECT 1 FROM information_schema.columns
                WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ? LIMIT 1',
            [$this->t($tabel), $kolom])->getNumRows() > 0;
    }
}
