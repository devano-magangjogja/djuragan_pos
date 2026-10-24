<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Penanda notifikasi prioritas pada kotak masuk.
 *
 * Permintaan "tautkan akun ke juragan" yang dikirim akun CS baru (belum punya
 * juragan) harus menonjol di inbox Superadmin: ia menaikkan pekerjaan yang
 * memblokir orang lain, jadi ia ditaruh paling atas dan diberi badge. Kolom ini
 * flag sederhana (0 biasa, 1 prioritas) yang dibaca ulang oleh daftar notifikasi
 * dan panel lonceng lewat urutan ORDER BY, tanpa mengubah notifikasi lama.
 *
 * Default 0 membuat seluruh riwayat lama berperilaku persis seperti sebelumnya.
 */
class PrioritasNotifikasi extends Migration
{
    public function up()
    {
        if (! $this->punyaKolom('prioritas')) {
            $this->db->query('ALTER TABLE ' . $this->t('notifikasi')
                . ' ADD COLUMN prioritas TINYINT(1) NOT NULL DEFAULT 0 AFTER type');
        }
    }

    public function down()
    {
        if ($this->punyaKolom('prioritas')) {
            $this->db->query('ALTER TABLE ' . $this->t('notifikasi') . ' DROP COLUMN prioritas');
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
            [$this->t('notifikasi'), $kolom])->getNumRows() > 0;
    }
}
