<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Prioritas dan penanda selesai pada kotak masukan.
 *
 * Kotak masukan tadinya hanya gudang baca-saja. Permintaan "tautkan akun CS ke
 * juragan" (dari halaman 404 User\Invoices) harus menonjol: ia pekerjaan yang
 * menunggu, bukan sekadar catatan. Kolom ini memberi Superadmin dua hal:
 *
 *   - prioritas : 1 = penting/menunggu tindakan; tampil dengan titik merah dan
 *                 dihitung pada badge menu Kotak Masukan.
 *   - selesai   : 1 = sudah ditangani; dikeluarkan dari hitungan badge tapi tetap
 *                 tertinggal di daftar sebagai riwayat.
 *
 * Default 0/0 membuat seluruh masukan lama berperilaku persis seperti sebelumnya.
 */
class PrioritasFeedback extends Migration
{
    public function up()
    {
        if (! $this->punyaKolom('prioritas')) {
            $this->db->query('ALTER TABLE ' . $this->t('feedback')
                . ' ADD COLUMN prioritas TINYINT(1) NOT NULL DEFAULT 0 AFTER isi');
        }

        if (! $this->punyaKolom('selesai')) {
            $this->db->query('ALTER TABLE ' . $this->t('feedback')
                . ' ADD COLUMN selesai TINYINT(1) NOT NULL DEFAULT 0 AFTER prioritas');
        }
    }

    public function down()
    {
        foreach (['selesai', 'prioritas'] as $kolom) {
            if ($this->punyaKolom($kolom)) {
                $this->db->query('ALTER TABLE ' . $this->t('feedback') . ' DROP COLUMN ' . $kolom);
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
            [$this->t('feedback'), $kolom])->getNumRows() > 0;
    }
}
