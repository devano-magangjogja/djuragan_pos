<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Menabur kembali isi baku dropdown Pencatatan COD.
 *
 * Pilihan master (keperluan, status, nama CS) dulunya hanya ditulis oleh
 * migrasi pembuat tabelnya, sehingga lingkungan yang tabelnya datang dari
 * impor kerangka — bukan dari menjalankan migrasi itu — punya dropdown yang
 * kosong. Keperluan dan CS masih bisa pulih sendiri karena pilihannya dibuat
 * saat catatan disimpan; status transaksi tidak, karena menambah status
 * khusus hak Superadmin. Akibatnya tab dan dropdown status menghilang tanpa
 * pernah ada yang menghapusnya.
 *
 * INSERT IGNORE atas kunci (jenis, kunci) yang unik membuat migrasi ini aman
 * dijalankan ulang dan tidak menimpa label yang sudah lebih dulu dipakai
 * orang. Sumber daftarnya sama dengan isi baku migrasi modul COD, ditambah
 * dua status yang menyusul dibuat kemudian.
 */
class SemaiPilihanCod extends Migration
{
    /**
     * @return array<string, list<string>>
     */
    private function baku(): array
    {
        return [
            'keperluan' => [
                'Sewa', 'Ambil Sewa', 'Keep Sewa', 'Kembalikan Sewa', 'Tambah Sewa',
                'Beli', 'Tanya-tanya', 'Custom', 'Ambil Pesanan', 'Beli Cover Jas',
                'Revisi Custom', 'Lainnya',
            ],
            'status' => [
                'Sukses', 'Cancel', 'Revisi', 'Masih dipikirkan', 'Barang Kosong',
            ],
            'cs' => [
                'Sakina', 'Salsa', 'Bu Pipit dan Salsa', 'Bu Pipit dan Sakina',
                'Sakina dan Salsa', 'Pak Danny',
            ],
        ];
    }

    public function up()
    {
        if (! $this->db->tableExists($this->t('pencatatan_pilihan'))) {
            return;
        }

        $waktu = time();

        foreach ($this->baku() as $jenis => $daftar) {
            foreach ($daftar as $label) {
                $kunci = self::kunci($label);

                if ($kunci === '') {
                    continue;
                }

                $this->db->query(
                    'INSERT IGNORE INTO `' . $this->t('pencatatan_pilihan') . '` (jenis, label, kunci, created_at) VALUES (?, ?, ?, ?)',
                    [$jenis, $label, $kunci, $waktu]
                );
            }
        }
    }

    /**
     * Batalkan taburan ini. Pilihan yang masih dipakai catatan tidak boleh
     * dilepas — foreign key-nya RESTRICT, jadi catatan itu akan kehilangan
     * labelnya — sehingga hanya baris yang belum dipakai yang dihapus.
     */
    public function down()
    {
        if (! $this->db->tableExists($this->t('pencatatan_pilihan'))
            || ! $this->db->tableExists($this->t('pencatatan_cod'))) {
            return;
        }

        $kunci = [];

        foreach ($this->baku() as $daftar) {
            foreach ($daftar as $label) {
                $kunci[] = self::kunci($label);
            }
        }

        $kunci = array_values(array_unique(array_filter($kunci)));

        if ($kunci === []) {
            return;
        }

        // query() lurus tidak mengembangkan array jadi daftar placeholder
        $ini = implode(',', array_fill(0, count($kunci), '?'));

        $this->db->query(
            'DELETE FROM `' . $this->t('pencatatan_pilihan') . '`'
            . ' WHERE kunci IN (' . $ini . ')'
            . ' AND id NOT IN ('
            . '  SELECT keperluan_id FROM `' . $this->t('pencatatan_cod') . '`'
            . '  UNION SELECT status_id FROM `' . $this->t('pencatatan_cod') . '`'
            . '  UNION SELECT cs_id FROM `' . $this->t('pencatatan_cod') . '`'
            . '  UNION SELECT sumber_id FROM `' . $this->t('pencatatan_cod') . '`'
            . ')',
            $kunci
        );
    }

    /** Kunci pencarian: huruf kecil, spasi berlebih dirapatkan (sama seperti model). */
    private static function kunci(string $label): string
    {
        return trim((string) preg_replace('/\s+/', ' ', mb_strtolower(trim($label))));
    }

    private function t(string $nama): string
    {
        /** @var \CodeIgniter\Database\BaseConnection $db */
        $db = $this->db;

        return $db->prefixTable($nama);
    }
}
