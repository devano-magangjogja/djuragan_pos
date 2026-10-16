<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Poin gaji dipindah ke master produk.
 *
 * Semula poin menempel di varian stok (per ukuran). Arahnya berubah: poin itu
 * milik satu nama barang, bukan per ukuran — Produk A berpoin berapa pun
 * ukurannya memberi poin yang sama. Kolom `poin` quindi dibuat di tabel `produk`
 * (satu nilai per kode) dan nilai varian stok yang lama dititip ke induknya lewat
 * MAX, lalu kolom `stok.poin` dihapus supaya tidak ada dua sumber yang bertentangan.
 *
 * Nilai lama di ledger poin_penjualan tidak disentuh: tiap barisnya sudah menyimpan
 * poin_satuan sebagai potret saat pemberian, jadi riwayat gaji tetap terbaca walau
 * sumber angkanya pindah tabel.
 */
class PoinPerProduk extends Migration
{
    public function up()
    {
        // 1. poin di master produk
        if (! $this->punyaKolom('produk', 'poin')) {
            $this->db->query('ALTER TABLE ' . $this->t('produk')
                . ' ADD COLUMN poin INT(10) UNSIGNED NOT NULL DEFAULT 0');
        }

        // 2. warisi poin varian lama ke induknya sebelum kolom stok dibuang
        if ($this->punyaKolom('stok', 'poin')) {
            $this->db->query('UPDATE `' . $this->t('produk') . '` p'
                . ' JOIN (SELECT produk_id, MAX(poin) AS poin FROM `' . $this->t('stok')
                . '` WHERE produk_id IS NOT NULL GROUP BY produk_id) s ON s.produk_id = p.id_produk'
                . ' SET p.poin = s.poin');

            $this->db->query('ALTER TABLE ' . $this->t('stok') . ' DROP COLUMN poin');
        }
    }

    public function down()
    {
        if (! $this->punyaKolom('stok', 'poin')) {
            $this->db->query('ALTER TABLE ' . $this->t('stok')
                . ' ADD COLUMN poin INT(10) UNSIGNED NOT NULL DEFAULT 0 AFTER harga');
        }

        // kembalikan poin varian dari induknya (semua ukuran satu toko memakai nilai produk)
        $this->db->query('UPDATE `' . $this->t('stok') . '` s'
            . ' JOIN `' . $this->t('produk') . '` p ON p.id_produk = s.produk_id'
            . ' SET s.poin = p.poin');

        if ($this->punyaKolom('produk', 'poin')) {
            $this->db->query('ALTER TABLE ' . $this->t('produk') . ' DROP COLUMN poin');
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
