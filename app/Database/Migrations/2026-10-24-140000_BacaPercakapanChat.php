<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Penanda "terakhir dibaca" per percakapan Live Chat WA.
 *
 * unread_admin hanyalah bendera 0/1 ("ada yang baru"), tidak bisa menjawab berapa
 * bubble yang belum dibaca dan tidak bisa menunjuk di mana unread dimulai. Kolom
 * baru ini menyimpan id_pesan terakhir yang sudah dilihat admin/CS, sehingga:
 *   - badge daftar = jumlah pesan 'masuk' dengan id_pesan > last_read_id;
 *   - pemisah "N pesan belum dibaca" ditaruh tepat sebelum bubble pertama setelah
 *     pointer itu, persis gaya WhatsApp.
 *
 * Saat migrasi, pointer diisi dengan id pesan terakhir tiap percakapan supaya
 * seluruh riwayat lama dianggap sudah dibaca (tidak tiba-tiba muncul banyak badge).
 */
class BacaPercakapanChat extends Migration
{
    public function up()
    {
        if (! $this->punyaKolom('last_read_id')) {
            $this->db->query('ALTER TABLE ' . $this->t('crm_chat_percakapan')
                . ' ADD COLUMN last_read_id INT UNSIGNED NOT NULL DEFAULT 0 AFTER unread_admin');
        }

        // backfill: anggap semua pesan lama sudah dibaca
        $pesan = $this->t('crm_chat_pesan');
        $perc  = $this->t('crm_chat_percakapan');
        $this->db->query('UPDATE ' . $perc . ' p SET p.last_read_id = COALESCE(('
            . ' SELECT MAX(m.id_pesan) FROM ' . $pesan . ' m WHERE m.percakapan_id = p.id_percakapan'
            . '), 0)');
    }

    public function down()
    {
        if ($this->punyaKolom('last_read_id')) {
            $this->db->query('ALTER TABLE ' . $this->t('crm_chat_percakapan') . ' DROP COLUMN last_read_id');
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
            [$this->t('crm_chat_percakapan'), $kolom])->getNumRows() > 0;
    }
}
