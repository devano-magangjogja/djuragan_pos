<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Opsi "Barang Kosong" untuk dropdown Status Transaksi di Pencatatan COD.
 *
 * Dipakai saat customer datang untuk mengambil barang tetapi stoknya sedang
 * habis, jadi kunjungannya tetap tercatat tanpa harus dipaksakan ke status
 * lain yang tidak menggambarkan keadaannya.
 *
 * Berdiri sendiri, bukan ditambahkan ke daftar baku PencatatanCod, karena
 * tabel master pilihan sudah terisi di setiap lingkungan. INSERT IGNORE dan
 * kunci yang sama membuatnya aman dijalankan ulang tanpa menimpa pilihan yang
 * sudah lebih dulu ada.
 */
class StatusCodBarangKosong extends Migration
{
    private const LABEL = 'Barang Kosong';

    public function up()
    {
        $this->db->query(
            'INSERT IGNORE INTO `' . $this->t('pencatatan_pilihan') . '` (jenis, label, kunci, created_at) VALUES (?, ?, ?, ?)',
            ['status', self::LABEL, $this->kunci(self::LABEL), time()]
        );
    }

    public function down()
    {
        $this->db->query(
            'DELETE FROM `' . $this->t('pencatatan_pilihan') . '` WHERE jenis = ? AND kunci = ?',
            ['status', $this->kunci(self::LABEL)]
        );
    }

    /** Kunci pencarian: huruf kecil, spasi berlebih dirapatkan (sama seperti model). */
    private function kunci(string $label): string
    {
        return trim((string) preg_replace('/\s+/', ' ', mb_strtolower(trim($label))));
    }

    /** Migration tidak punya helper ini, jadi prefix tabelnya diminta sendiri. */
    private function t(string $nama): string
    {
        // $this->db hanya dideklarasikan sebagai ConnectionInterface di kelas
        // induk, padahal prefixTable() ada di BaseConnection yang dipakai saat
        // runtime. Anotasi ini meluruskan inference untuk analisis statis.
        /** @var \CodeIgniter\Database\BaseConnection $db */
        $db = $this->db;

        return $db->prefixTable($nama);
    }
}
