<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Deadline jadi tanggal sungguhan.
 *
 * Sekarang deadline cuma teks bebas di dalam order_invoice.rincian ("12 september"),
 * hanya 3 baris yang terisi, dan tidak bisa diurutkan atau dibandingkan dengan
 * hari ini. Dasbor minta "hari ini / besok / 3 hari lagi / terlewat", jadi kolom
 * DATE NULL ini yang dipakai query.
 *
 * rincian lama tidak disentuh dan tetap tampil seperti sebelumnya. Order yang
 * belum punya deadline ya NULL, dan UI menampilkan "-", bukan tanggal karangan.
 */
class KolomDeadline extends Migration
{
    public function up()
    {
        if (! $this->punyaKolom('deadline')) {
            $this->db->query('ALTER TABLE ' . $this->t('invoice') . ' ADD COLUMN deadline DATE NULL AFTER rincian');
            $this->db->query('ALTER TABLE ' . $this->t('invoice') . ' ADD INDEX idx_invoice_deadline (deadline)');
        }
    }

    public function down()
    {
        if ($this->punyaKolom('deadline')) {
            $this->db->query('ALTER TABLE ' . $this->t('invoice') . ' DROP INDEX idx_invoice_deadline');
            $this->db->query('ALTER TABLE ' . $this->t('invoice') . ' DROP COLUMN deadline');
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
            [$this->t('invoice'), $kolom])->getNumRows() > 0;
    }
}
