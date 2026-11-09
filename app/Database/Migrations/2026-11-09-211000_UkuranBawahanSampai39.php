<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Ukuran bawahan 35 sampai 39 di master ukuran.
 *
 * Daftar sebelumnya berhenti di 34, jadi celana besar hanya bisa dicatat lewat
 * isian bebas. Urutannya mengikuti pola yang sudah ada di migrasi pembuat
 * tabelnya (100 + angka), sehingga 35..39 langsung mendarat sesudah 34 tanpa
 * menggeser ukuran lama.
 *
 * Kode punya kunci unik, jadi INSERT IGNORE aman dijalankan ulang.
 */
class UkuranBawahanSampai39 extends Migration
{
    private const DARI = 35;
    private const SAMPAI = 39;

    public function up()
    {
        if (! $this->db->tableExists($this->t('ukuran'))) {
            return;
        }

        for ($angka = self::DARI; $angka <= self::SAMPAI; $angka++) {
            $this->db->query(
                'INSERT IGNORE INTO `' . $this->t('ukuran') . '` (kode, label, kelompok, urutan) VALUES (?, ?, ?, ?)',
                [(string) $angka, (string) $angka, 'bawahan', 100 + $angka]
            );
        }
    }

    public function down()
    {
        if (! $this->db->tableExists($this->t('ukuran'))) {
            return;
        }

        $angka = range(self::DARI, self::SAMPAI);
        $ini   = implode(',', array_fill(0, count($angka), '?'));

        $this->db->query('DELETE FROM `' . $this->t('ukuran') . '` WHERE kelompok = ? AND kode IN (' . $ini . ')', array_merge(['bawahan'], $angka));
    }

    private function t(string $nama): string
    {
        /** @var \CodeIgniter\Database\BaseConnection $db */
        $db = $this->db;

        return $db->prefixTable($nama);
    }
}
