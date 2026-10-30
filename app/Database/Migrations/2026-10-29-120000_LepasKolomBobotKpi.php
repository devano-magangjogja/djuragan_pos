<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\Migration;

/**
 * Kolom bobot di tabel target KPI dilepas.
 *
 * Bobot dipakai untuk menimbang beberapa indikator sekaligus. Sejak halaman
 * Target hanya menyisakan GMV (lihat HapusTargetKpiSelainGmv) tidak ada lagi yang
 * ditimbang, dan kode aplikasi sudah berhenti membaca kolom itu di mana-mana.
 *
 * Semua barisnya bernilai 0.00 (tidak ada satu pun bobot yang pernah dipasang),
 * jadi yang hilang hanya struktur, bukan angka. Rollback menambahkan kolomnya
 * kembali dengan definisi asli; nilainya tetap 0.00 seperti semula.
 */
class LepasKolomBobotKpi extends Migration
{
    public function up()
    {
        if ($this->punyaKolom('bobot')) {
            $this->db->query('ALTER TABLE ' . $this->t('kpi_target') . ' DROP COLUMN bobot');
        }
    }

    public function down()
    {
        if (! $this->punyaKolom('bobot')) {
            $this->db->query('ALTER TABLE ' . $this->t('kpi_target')
                . ' ADD COLUMN bobot DECIMAL(5,2) NOT NULL DEFAULT 0 AFTER target_tim');
        }
    }

    private function t(string $nama): string
    {
        $db = $this->db;

        return $db instanceof BaseConnection ? $db->prefixTable($nama) : $nama;
    }

    private function punyaKolom(string $kolom): bool
    {
        return $this->db->query('SELECT 1 FROM information_schema.columns
                WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ? LIMIT 1',
            [$this->t('kpi_target'), $kolom])->getNumRows() > 0;
    }
}
