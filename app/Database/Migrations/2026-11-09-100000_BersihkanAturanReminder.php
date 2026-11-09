<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Setelan aturan reminder ulasan dibuang dari crm_setting.
 *
 * Layar aturannya (admin/tugas/pengaturan) sudah dihapus dan angkanya dipatok di
 * TugasModel: permintaan ulasan bintang 5 selalu nyala, tanpa jeda hari. Baris
 * reminder_ulasan_aktif dan reminder_ulasan_hari tidak lagi dibaca siapa pun, dan
 * kalau dibiarkan justru menipu — angkanya kelihatan bisa diubah padahal tidak.
 *
 * Sekalian dibersihkan: reminder_dp_* dan reminder_pelunasan_*, sisa aturan lama
 * yang sudah lebih dulu berhenti dibaca dan tidak punya tempat di layar mana pun.
 * Kunci lain di tabel ini (notifikasi, gateway WhatsApp, pencatatan operan shift)
 * tidak disentuh.
 */
class BersihkanAturanReminder extends Migration
{
    /** @var list<string> */
    private array $kunci = [
        'reminder_ulasan_aktif',
        'reminder_ulasan_hari',
        'reminder_dp_aktif',
        'reminder_dp_jam',
        'reminder_pelunasan_aktif',
        'reminder_pelunasan_hari',
    ];

    public function up()
    {
        if (! $this->db->tableExists($this->t('crm_setting'))) {
            return;
        }

        // query() lurus tidak mengembangkan array jadi daftar placeholder, jadi
        // tanda tanyasnya ditulis satu per satu dan nilainya diikat berurutan
        $ini = implode(',', array_fill(0, count($this->kunci), '?'));

        $this->db->query(
            'DELETE FROM ' . $this->t('crm_setting') . ' WHERE setting_key IN (' . $ini . ')',
            $this->kunci
        );
    }

    /**
     * Kalau benar-benar dibatalkan: kedua kunci aturan ulasan kembali ditulis dengan
     * nilai bawaan yang dulu tersimpan. Kunci warisan (dp/pelunasan) sengaja tidak
     * dihidupkan lagi — tidak ada kode yang membacanya.
     */
    public function down()
    {
        if (! $this->db->tableExists($this->t('crm_setting'))) {
            return;
        }

        $waktu = time();

        foreach (['reminder_ulasan_aktif' => '1', 'reminder_ulasan_hari' => '0'] as $kunci => $nilai) {
            $this->db->query(
                'INSERT INTO ' . $this->t('crm_setting') . ' (setting_key, setting_value, created_at, updated_at) VALUES (?, ?, ?, ?)',
                [$kunci, $nilai, $waktu, $waktu]
            );
        }
    }

    private function t(string $nama): string
    {
        return $this->db->prefixTable($nama);
    }
}
