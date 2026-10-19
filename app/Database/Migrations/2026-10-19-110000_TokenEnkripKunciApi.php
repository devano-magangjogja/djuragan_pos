<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Salinan token kunci API yang ter-enkripsi.
 *
 * Semula hanya hash SHA-256 yang disimpan: token mentah hilang setelah
 * ditampilkan sekali, aman dari bocornya salinan basis data, tapi pemilik
 * kunci juga tidak bisa melihat ulang kuncinya sendiri. Kini salinan mentahnya
 * ikut disimpan dalam bentuk ter-enkripsi (CI4 Encryption, kuncinya di .env),
 * dan layar Kunci API menampilkan salinan itu sebagai teks kabur yang baru
 * terlihat setelah diklik.
 *
 * Kunci lama (#19 dst.) tidak punya salinan ini: token-nya memang tidak pernah
 * ditulis di mana pun, jadi tidak bisa di-backfill. Layarnya memberi tahu
 * pemilik kunci seperti itu untuk membuat kunci baru.
 */
class TokenEnkripKunciApi extends Migration
{
    public function up()
    {
        if (! $this->punyaKolom('token_enkrip')) {
            $this->db->query('ALTER TABLE ' . $this->t('kunci_api')
                . ' ADD COLUMN token_enkrip TEXT NULL AFTER kunci');
        }
    }

    public function down()
    {
        if ($this->punyaKolom('token_enkrip')) {
            $this->db->query('ALTER TABLE ' . $this->t('kunci_api') . ' DROP COLUMN token_enkrip');
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
            [$this->t('kunci_api'), $kolom])->getNumRows() > 0;
    }
}
