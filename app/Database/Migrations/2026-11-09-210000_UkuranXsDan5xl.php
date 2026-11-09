<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Ukuran XS dan 5XL di daftar ukuran atasan.
 *
 * Master ukuran tidak lagi hanya mencerminkan ukuran yang pernah dijual — ia
 * jadi pilihan di form stok dan dasar urutan di daftar transaksi, jadi dua
 * ukuran baru ini ditabur lewat migrasi supaya lingkungan mana pun punya
 * daftar yang sama. Kode punya kunci unik, jadi INSERT IGNORE aman dijalankan
 * ulang dan tidak menimpa baris yang sudah ada.
 *
 * Urutan memakai celah di sekitar daftar lama (s..xxxl = 1..6): XS 0 supaya
 * paling depan, 5XL 7 sesudah XXXL.
 */
class UkuranXsDan5xl extends Migration
{
    /** @var list<array{0: string, 1: string, 2: int}> kode, label, urutan */
    private const BARU = [
        ['xs', 'XS', 0],
        ['5xl', '5XL', 7],
    ];

    public function up()
    {
        if (! $this->db->tableExists($this->t('ukuran'))) {
            return;
        }

        foreach (self::BARU as [$kode, $label, $urutan]) {
            $this->db->query(
                'INSERT IGNORE INTO `' . $this->t('ukuran') . '` (kode, label, kelompok, urutan) VALUES (?, ?, ?, ?)',
                [$kode, $label, 'atasan', $urutan]
            );
        }
    }

    public function down()
    {
        if (! $this->db->tableExists($this->t('ukuran'))) {
            return;
        }

        $kode = array_column(self::BARU, 0);
        $ini  = implode(',', array_fill(0, count($kode), '?'));

        $this->db->query('DELETE FROM `' . $this->t('ukuran') . '` WHERE kode IN (' . $ini . ')', $kode);
    }

    private function t(string $nama): string
    {
        /** @var \CodeIgniter\Database\BaseConnection $db */
        $db = $this->db;

        return $db->prefixTable($nama);
    }
}
