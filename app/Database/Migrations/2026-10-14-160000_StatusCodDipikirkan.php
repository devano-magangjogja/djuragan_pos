<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Opsi "Masih dipikirkan" untuk dropdown Status Transaksi di Pencatatan COD.
 *
 * Berdiri sendiri, bukan ditambahkan ke daftar baku PencatatanCod, karena
 * tabel master pilihan sudah terisi di setiap lingkungan. INSERT IGNORE dan
 * kunci yang sama membuatnya aman dijalankan ulang tanpa menimpa pilihan yang
 * sudah lebih dulu ada.
 */
class StatusCodDipikirkan extends Migration
{
    private const LABEL = 'Masih dipikirkan';

    public function up()
    {
        $this->db->query(
            'INSERT IGNORE INTO `' . $this->t('pencatatan_pilihan') . '` (jenis, label, kunci, created_at) VALUES (?, ?, ?, ?)',
            ['status', self::LABEL, mb_strtolower(self::LABEL), time()]
        );
    }

    public function down()
    {
        $this->db->query(
            'DELETE FROM `' . $this->t('pencatatan_pilihan') . '` WHERE jenis = ? AND kunci = ?',
            ['status', mb_strtolower(self::LABEL)]
        );
    }

    /** Migration tidak punya helper ini, jadi prefix tabelnya diminta sendiri. */
    private function t(string $nama): string
    {
        return $this->db->prefixTable($nama);
    }
}
