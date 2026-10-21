<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\Migration;

/**
 * Menyesuaikan template ukuran dengan lembar ukur penjahit.
 *
 * - Jas memakai daftar ukuran atasan lengkap (dada, perut, pinggul, bahu,
 *   rangkaian lengan, dsb).
 * - Celana dan Rok berbagi satu set ukuran "bawahan". Rok ditambahkan sebagai
 *   jenis busana baru supaya pesanan rok bisa dipilih.
 *
 * Komponen lama TIDAK dihapus: nilai_ukuran menunjuk id_komponen dengan FK
 * RESTRICT, jadi hasil ukur orderan terdahulu akan ikut hilang kalau barisnya
 * dibuang. Komponen lama cukup dinonaktifkan (aktif = 0) — model sudah menyaring
 * aktif = 1 di semua pembacaan template — lalu daftar baru dipasang aktif.
 *
 * "Keterangan" dari daftar bawahan sengaja tidak dibuatkan komponen: kolom
 * komponen angka hanya menerima angka (cm), sedangkan keterangan berupa teks.
 * Sudah ada field teks "Catatan" di panel Spesifikasi Custom untuk itu.
 */
class UbahTemplateUkuranJasBawahan extends Migration
{
    private const TEMPLATE = [
        'Jas'    => [
            'Ukuran (PI)', 'Panjang Jas', 'Dada', 'Lingkar Perut', 'Pinggul', 'Bahu',
            'Panjang Lengan', 'L. Lengan Atas', 'Siku', 'L. Lengan Bawah', 'Ketiak',
        ],
        'Celana' => [
            'Panjang Celana', 'Panjang Rok', 'Pinggang', 'Pinggul', 'Panggul',
            'Paha', 'Lutut', 'Lingkar Bawah', 'Vasak',
        ],
        'Rok'    => [
            'Panjang Celana', 'Panjang Rok', 'Pinggang', 'Pinggul', 'Panggul',
            'Paha', 'Lutut', 'Lingkar Bawah', 'Vasak',
        ],
    ];

    public function up()
    {
        $waktu = time();

        // Rok duduk tepat di bawah Celana pada dropdown jenis.
        $celana = $this->db->query('SELECT urutan FROM ' . $this->t('jenis_produk') . " WHERE nama = 'Celana' LIMIT 1")->getFirstRow();
        $urutan = $celana === null ? 999 : ((int) $celana->urutan + 5);

        $this->db->query('INSERT IGNORE INTO ' . $this->t('jenis_produk')
            . ' (nama, urutan, created_at, updated_at) VALUES (?, ?, ?, ?)',
            ['Rok', $urutan, $waktu, $waktu]);

        foreach (self::TEMPLATE as $namaJenis => $daftar) {
            $jenis = $this->db->query('SELECT id_jenis FROM ' . $this->t('jenis_produk') . ' WHERE nama = ? LIMIT 1', [$namaJenis])->getFirstRow();

            if ($jenis === null) {
                continue;
            }

            $idJenis = (int) $jenis->id_jenis;

            // matikan komponen lama jenis ini (jangan hapus: FK nilai_ukuran RESTRICT)
            $this->db->query('UPDATE ' . $this->t('ukuran_komponen') . ' SET aktif = 0, updated_at = ? WHERE jenis_id = ? AND aktif = 1',
                [$waktu, $idJenis]);

            foreach ($daftar as $i => $nama) {
                $this->db->query('INSERT INTO ' . $this->t('ukuran_komponen')
                    . ' (jenis_id, nama, satuan, urutan, wajib, aktif, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                    [$idJenis, $nama, 'cm', ($i + 1) * 10, 0, 1, $waktu, $waktu]);
            }
        }
    }

    public function down()
    {
        $waktu = time();

        foreach (array_keys(self::TEMPLATE) as $namaJenis) {
            $jenis = $this->db->query('SELECT id_jenis FROM ' . $this->t('jenis_produk') . ' WHERE nama = ? LIMIT 1', [$namaJenis])->getFirstRow();

            if ($jenis === null) {
                continue;
            }

            $idJenis = (int) $jenis->id_jenis;

            // buang komponen baru ini, kembalikan komponen lama yang tadi dimatikan
            $this->db->query('DELETE FROM ' . $this->t('ukuran_komponen') . ' WHERE jenis_id = ? AND aktif = 1', [$idJenis]);
            $this->db->query('UPDATE ' . $this->t('ukuran_komponen') . ' SET aktif = 1, updated_at = ? WHERE jenis_id = ? AND aktif = 0',
                [$waktu, $idJenis]);
        }

        // Rok hanya boleh dilepas kalau belum dipakai produk mana pun
        $this->db->query('DELETE FROM ' . $this->t('jenis_produk') . " WHERE nama = 'Rok' AND id_jenis NOT IN (SELECT id_jenis FROM " . $this->t('produk') . ' WHERE id_jenis IS NOT NULL)');
    }

    private function t(string $nama): string
    {
        $db = $this->db;

        return $db instanceof BaseConnection ? $db->prefixTable($nama) : $nama;
    }
}
