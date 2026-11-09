<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use RuntimeException;

/**
 * Memulihkan view arsit_* yang berakhir sebagai tabel kosong hasil impor dump.
 *
 * phpMyAdmin menulis view dua kali: lebih dulu "stand-in structure" berupa
 * CREATE TABLE kosong di kepala file, lalu CREATE OR REPLACE VIEW-nya yang
 * asli di ekor file. Kalau impor terpotong (timeout, file dipotong, error di
 * tengah), yang tertinggal cuma bonekanya: namanya sama, isinya nol. Daftar
 * yang membaca arsip jadi kosong tanpa satu pun error PHP — penyakit yang
 * sama sudah ditangani RepairCrmPelangganViews untuk crm_pelanggan.
 *
 * Definisi view tidak disalin ke sini: diambil dari PersiapanArsip lewat
 * daftarView(), supaya cuma ada satu sumber kebenaran. Tabel arsip memang
 * tidak memakai prefix order_ (lihat catatan di migration itu), jadi tidak
 * ada alias order_arsit_* yang perlu ikut dibuat.
 *
 * Aman untuk view yang sehat: CREATE OR REPLACE hanya membangun ulang objek
 * dengan definisi yang identik. Tabel yang berisi data justru TIDAK dibuang —
 * migrasi berhenti dengan pesan jelas, karena boleh jadi itu arsip asli yang
 * sedang dipakai lingkungan lain.
 */
class PerbaikanViewArsip extends Migration
{
    public function up()
    {
        // Lingkungan tanpa arsip 2014-2020 tidak punya apa pun untuk dipulihkan;
        // tanpa penjagaan ini CREATE VIEW akan mati di tabel sumber yang hilang.
        if (! $this->adaObjek('faktur')) {
            return;
        }

        foreach ($this->arsip()->daftarView() as $sql) {
            $nama = $this->namaView($sql);

            $this->buangBoneka($nama);

            $this->db->query($sql);
        }
    }

    public function down()
    {
        // tidak ada tabel basis yang perlu dikembalikan: view ini memang virtual.
        // Nama yang sedang memegang BASE TABLE dibiarkan: DROP VIEW atas tabel
        // ditolak MySQL (rollback-nya mati di tengah jalan), dan isinya bukan
        // buatan migrasi ini jadi bukan hak kami untuk membuangnya.
        foreach ($this->arsip()->daftarView() as $sql) {
            $nama = $this->namaView($sql);

            if ($this->tipeObjek($nama) === 'VIEW') {
                $this->db->query('DROP VIEW IF EXISTS `' . $nama . '`');
            }
        }
    }

    /**
     * Migration yang membangun view-view ini; definisinya diambil dari sana.
     *
     * Kelas migration tidak bisa dibaca autoloader karena nama filenya membawa
     * prefix waktu (`2026-09-28-150000_PersiapanArsip.php`), jadi tidak cocok
     * dengan nama kelasnya. Filenya dicari di folder Migrations lalu dimuat
     * manual: lebih baik daripada menyalin 70 baris definisi view yang bisa
     * lari berbeda dari aslinya.
     */
    private function arsip(): PersiapanArsip
    {
        if (! class_exists(PersiapanArsip::class)) {
            foreach (glob(APPPATH . 'Database/Migrations/*_PersiapanArsip.php') ?: [] as $berkas) {
                require_once $berkas;
            }
        }

        if (! class_exists(PersiapanArsip::class)) {
            throw new RuntimeException(
                'PersiapanArsip tidak ditemukan di app/Database/Migrations, jadi definisi view arsip tidak bisa dibaca.'
            );
        }

        return new PersiapanArsip();
    }

    /** Nama objek yang dibangun sebuah kueri CREATE ... VIEW. */
    private function namaView(string $sql): string
    {
        if (preg_match('/CREATE\s+(?:OR\s+REPLACE\s+)?VIEW\s+`?([A-Za-z0-9_]+)`?/i', $sql, $m) !== 1) {
            throw new RuntimeException('Nama view tidak terbaca dari definisinya: ' . substr($sql, 0, 60) . '...');
        }

        return $m[1];
    }

    /**
     * Buang boneka tabel kosong warisan dump. VIEW dibiarkan apa adanya
     * (CREATE OR REPLACE di pemanggil yang menggantinya), dan tabel berisi
     * data menghentikan proses supaya tidak ada yang hilang diam-diam.
     */
    private function buangBoneka(string $nama): void
    {
        if ($this->tipeObjek($nama) !== 'BASE TABLE') {
            return;
        }

        $isi = (int) $this->db->query('SELECT COUNT(*) AS jumlah FROM `' . $nama . '`')->getRowArray()['jumlah'];

        if ($isi > 0) {
            throw new RuntimeException(
                '`' . $nama . '` adalah tabel berisi ' . $isi . ' baris, bukan boneka view hasil impor. '
                . 'Pindahkan isinya ke tempat lain dulu, lalu jalankan migrasi ini lagi.'
            );
        }

        $this->db->query('DROP TABLE IF EXISTS `' . $nama . '`');
    }

    /** BASE TABLE | VIEW, atau null kalau namanya belum dipakai. */
    private function tipeObjek(string $nama): ?string
    {
        $row = $this->db->query(
            'SELECT TABLE_TYPE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
            [$nama]
        )->getRowArray();

        return $row === null ? null : (string) $row['TABLE_TYPE'];
    }

    private function adaObjek(string $nama): bool
    {
        return $this->tipeObjek($nama) !== null;
    }
}
