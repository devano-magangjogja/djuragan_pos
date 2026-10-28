<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Target GMV yang dibedakan untuk Tim CS vs per-CS.
 *
 * GMV adalah angka yang bisa dijumlahkan: total satu tim jauh lebih besar daripada
 * kontribusi satu orang. Selama ini kpi_target hanya punya satu kolom `target` untuk
 * gmv_cs, sehingga kartu GMV di Ringkasan memakai satu angka yang sama baik saat
 * melihat keseluruhan tim maupun satu karyawan — bikin perbandingan tidak adil dan
 * membingungkan.
 *
 * Kolom baru target_tim menyimpan target GMV untuk SELURUH tim; kolom target yang
 * sudah ada dipertegas artinya sebagai target per-CS. Keduanya hanya dipakai oleh
 * indikator gmv_cs, jadi indikator lain membiarkannya NULL tanpa berubah perilaku.
 * Nilai awal NULL: target tetap hak manajemen, sistem tidak mengarang angka.
 */
class TargetTimGmvKpi extends Migration
{
    public function up()
    {
        if (! $this->punyaKolom('target_tim')) {
            $this->db->query('ALTER TABLE ' . $this->t('kpi_target')
                . ' ADD COLUMN target_tim DECIMAL(12,2) NULL DEFAULT NULL AFTER target');
        }
    }

    public function down()
    {
        if ($this->punyaKolom('target_tim')) {
            $this->db->query('ALTER TABLE ' . $this->t('kpi_target')
                . ' DROP COLUMN target_tim');
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
            [$this->t('kpi_target'), $kolom])->getNumRows() > 0;
    }
}
