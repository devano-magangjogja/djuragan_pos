<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Jejak asal akun untuk mendukung login SSO server Presensi.
 *
 * Auth::index() memverifikasi kredensial ke Presensi lebih dulu, lalu jatuh ke
 * database lokal. User yang lolos SSO namun belum pernah ada di sini dibuat
 * otomatis supaya langsung muncul di daftar pengguna dan bisa ditata superadmin
 * (role, juragan, status). Tiga kolom ini menandai akun mana yang datang dari
 * Presensi dan menautkannya ke id sumber.
 *
 *   - sso_id : id pegawai di Presensi; pemetaan dua-arah saat sinkronisasi.
 *   - nip    : nomor induk pegawai, penolong saat username/email tidak unik.
 *   - sumber : 'lokal' (dibuat manual/di panel) atau 'sso' (provisi otomatis).
 *
 * Semua boleh kosong dan default-nya mempertahankan perilaku lama, jadi akun
 * warisan tidak berubah.
 */
class SsoPresensiPengguna extends Migration
{
    public function up()
    {
        if (! $this->punyaKolom('sso_id')) {
            $this->db->query('ALTER TABLE ' . $this->t('user')
                . ' ADD COLUMN sso_id INT UNSIGNED NULL AFTER level');
        }

        if (! $this->punyaKolom('nip')) {
            $this->db->query('ALTER TABLE ' . $this->t('user')
                . ' ADD COLUMN nip VARCHAR(30) NULL AFTER sso_id');
        }

        if (! $this->punyaKolom('sumber')) {
            $this->db->query('ALTER TABLE ' . $this->t('user')
                . " ADD COLUMN sumber ENUM('lokal','sso') NOT NULL DEFAULT 'lokal' AFTER nip");
        }

        // indeks ringan untuk pencarian cepat saat sinkron (WHERE sso_id = ?)
        if (! $this->punyaIndex('idx_user_sso_id')) {
            $this->db->query('ALTER TABLE ' . $this->t('user') . ' ADD INDEX idx_user_sso_id (sso_id)');
        }
    }

    public function down()
    {
        if ($this->punyaIndex('idx_user_sso_id')) {
            $this->db->query('ALTER TABLE ' . $this->t('user') . ' DROP INDEX idx_user_sso_id');
        }

        foreach (['sumber', 'nip', 'sso_id'] as $kolom) {
            if ($this->punyaKolom($kolom)) {
                $this->db->query('ALTER TABLE ' . $this->t('user') . ' DROP COLUMN ' . $kolom);
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
            [$this->t('user'), $kolom])->getNumRows() > 0;
    }

    private function punyaIndex(string $nama): bool
    {
        return $this->db->query('SELECT 1 FROM information_schema.statistics
                WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ? LIMIT 1',
            [$this->t('user'), $nama])->getNumRows() > 0;
    }
}
