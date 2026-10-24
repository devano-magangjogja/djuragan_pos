<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Penanda "chat ini sudah diarsipkan" pada percakapan Live Chat.
 *
 * Arsip bukan tempat pembuangan: isi pesannya tetap utuh dan percakapannya
 * masih bisa dibuka, hanya saja keluar dari daftar utama supaya inbox CS tidak
 * penuh oleh obrolan lama. Aksi mengarsipkan khusus admin & superadmin; CS
 * boleh melihat isinya lewat daftar arsip tapi tidak bisa mengubah statusnya.
 *
 * Pesan masuk baru membuka arsipnya sendiri (lihat WhatsAppManager), jadi
 * pelanggan yang membalas tidak pernah menguap dari inbox.
 *
 * Nilainya 0 untuk semua percakapan lama, jadi perilaku default tidak berubah.
 */
class ArsipChatCrm extends Migration
{
    public function up()
    {
        if (! $this->punyaKolom('diarsipkan')) {
            $this->db->query('ALTER TABLE ' . $this->t('crm_chat_percakapan')
                . ' ADD COLUMN diarsipkan TINYINT(1) NOT NULL DEFAULT 0 AFTER unread_admin');
        }

        if (! $this->punyaKolom('waktu_arsip')) {
            $this->db->query('ALTER TABLE ' . $this->t('crm_chat_percakapan')
                . ' ADD COLUMN waktu_arsip INT UNSIGNED NULL DEFAULT NULL AFTER diarsipkan');
        }
    }

    public function down()
    {
        foreach (['waktu_arsip', 'diarsipkan'] as $kolom) {
            if ($this->punyaKolom($kolom)) {
                $this->db->query('ALTER TABLE ' . $this->t('crm_chat_percakapan')
                    . ' DROP COLUMN ' . $kolom);
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
            [$this->t('crm_chat_percakapan'), $kolom])->getNumRows() > 0;
    }
}
