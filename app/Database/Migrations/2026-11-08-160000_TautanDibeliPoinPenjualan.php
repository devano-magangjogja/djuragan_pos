<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Tautan satu baris ledger poin ke satu baris orderan.
 *
 * Poin gaji tadinya diberikan untuk seluruh isi nota sekaligus, jadi ledger cuma
 * perlu tahu notasinya. Aturannya sekarang berbeda per baris barang: item yang
 * ditautkan ke unit stok sudah berpindah tangan saat nota dibayar, sedangkan item
 * tulisan tangan (custom/pembuatan) baru berhak setelah notanya masuk Selesai.
 * Tanpa penanda baris, ledger tidak bisa memisahkan keduanya — dan rekonsiliasi
 * (menambah baris yang baru layak, mencabut yang tidak layak lagi) akan menebak
 * dari kode/ukuran/qty yang bisa sama persis untuk dua baris berbeda.
 *
 * Kolomnya boleh kosong: baris lama hasil aturan lama tetap utuh dan memang tidak
 * pernah ditautkan. Kunci uniknya melindungi dari pemberian ganda; MySQL
 * mengizinkan banyak NULL, jadi baris lama tidak ikut tersenggol.
 */
class TautanDibeliPoinPenjualan extends Migration
{
    public function up()
    {
        if (! $this->punyaKolom()) {
            $this->db->query('ALTER TABLE ' . $this->t('poin_penjualan')
                . ' ADD COLUMN dibeli_id INT(10) UNSIGNED NULL AFTER invoice_id');
        }

        if (! $this->punyaKunci()) {
            $this->db->query('ALTER TABLE ' . $this->t('poin_penjualan')
                . ' ADD UNIQUE KEY uniq_poin_dibeli (invoice_id, dibeli_id)');
        }
    }

    public function down()
    {
        if ($this->punyaKunci()) {
            $this->db->query('ALTER TABLE ' . $this->t('poin_penjualan') . ' DROP INDEX uniq_poin_dibeli');
        }

        if ($this->punyaKolom()) {
            $this->db->query('ALTER TABLE ' . $this->t('poin_penjualan') . ' DROP COLUMN dibeli_id');
        }
    }

    private function punyaKolom(): bool
    {
        return $this->db->query('SELECT 1 FROM information_schema.columns
                WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ? LIMIT 1',
            [$this->t('poin_penjualan'), 'dibeli_id'])->getNumRows() > 0;
    }

    private function punyaKunci(): bool
    {
        return $this->db->query('SELECT 1 FROM information_schema.statistics
                WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ? LIMIT 1',
            [$this->t('poin_penjualan'), 'uniq_poin_dibeli'])->getNumRows() > 0;
    }

    private function t(string $nama): string
    {
        return $this->db->prefixTable($nama);
    }
}
