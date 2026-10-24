<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Level "reseller" dilepas, dan akun baru tidak dilahirkan sebagai "viewer" lagi.
 *
 * Dua level itu tidak dipakai di halaman mana pun: tidak ada hak akses yang
 * menunjuk namanya, jadi keduanya hanya tersisa sebagai pilihan di form.
 * "reseller" belum pernah dipakai satu akun pun (0 baris), sehingga nilainya
 * bisa benar-benar dihapus dari kolom ENUM.
 *
 * "viewer" TIDAK ikut dihapus dari kolom: 239 akun lama masih menyandangnya,
 * dan MySQL tidak bisa membuang satu nilai ENUM tanpa menulis ulang baris yang
 * memakainya — itu justru yang harus dihindari. Yang diubah untuk viewer hanya
 * default kolomnya (dulu 'viewer'), supaya akun yang dibuat tanpa menyebut level
 * tidak diam-diam jadi viewer.
 *
 * Nilai ENUM dipetakan berdasarkan teks, bukan urutan, jadi baris lama tetap
 * bernilai sama setelah kolom dibangun ulang.
 */
class LepasLevelReseller extends Migration
{
    private const BARU = "'superadmin','admin','cs','viewer'";
    private const LAMA = "'superadmin','admin','cs','viewer','reseller'";

    public function up()
    {
        $this->ubahKe(self::BARU, 'cs');
    }

    public function down()
    {
        $this->ubahKe(self::LAMA, 'viewer');
    }

    private function ubahKe(string $constraint, string $default): void
    {
        $sekarang = $this->db->query('SELECT COLUMN_TYPE, COLUMN_DEFAULT
                FROM information_schema.columns
                WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ? LIMIT 1',
            [$this->t('user'), 'level'])->getRow();

        if ($sekarang === null) {
            return;
        }

        if (strcasecmp($sekarang->COLUMN_TYPE, 'enum(' . $constraint . ')') === 0
            && (string) $sekarang->COLUMN_DEFAULT === $default) {
            return;
        }

        $this->db->query('ALTER TABLE ' . $this->t('user')
            . ' MODIFY COLUMN level ENUM(' . $constraint . ') NOT NULL DEFAULT ' . "'" . $default . "'");
    }

    private function t(string $nama): string
    {
        return $this->db->prefixTable($nama);
    }
}
