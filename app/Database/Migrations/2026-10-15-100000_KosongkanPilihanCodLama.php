<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Kolom keperluan_id dan status_id di catatan COD jadi boleh kosong.
 *
 * Awalnya keduanya NOT NULL karena setiap catatan baru pasti punya keperluan dan
 * status. Ternyata itu membuat pilihan master tidak pernah bisa dihapus selama
 * masih ada catatan yang PERNAH menunjuknya, termasuk catatan yang sudah
 * dihapus lunak dan tidak pernah tampil lagi: foreign key-nya RESTRICT, jadi
 * MySQL menolak menghapus baris pilihan selama satu baris lama saja masih
 * menyimpan id-nya.
 *
 * With kolom ini boleh kosong, menghapus pilihan sekaligus melepaskan catatan
 * lama yang sudah dibuang (PencatatanCodModel::lepaskan()) tanpa menghilangkan
 * barisnya. Catatan yang masih tampil tetap tidak bisa ditinggalkan: halaman
 * COD menolaknya lebih dulu, dan isian baru selalu mengisi keduanya.
 *
 * Tidak ada data yang berubah nilai — kolomnya hanya jadi boleh NULL.
 */
class KosongkanPilihanCodLama extends Migration
{
    private const KOLOM = ['keperluan_id', 'status_id'];

    public function up()
    {
        foreach (self::KOLOM as $kolom) {
            $this->db->query('ALTER TABLE `' . $this->t() . '` MODIFY `' . $kolom . '` INT UNSIGNED NULL');
        }
    }

    public function down()
    {
        foreach (self::KOLOM as $kolom) {
            // hanya bisa mengembalikan NOT NULL kalau tidak ada catatan yang
            // terlanjur ditinggalkan; kalau ada, biarkan kosong daripada gagal
            $tersisa = $this->db->query(
                'SELECT COUNT(*) AS jumlah FROM `' . $this->t() . '` WHERE `' . $kolom . '` IS NULL'
            )->getRowArray()['jumlah'];

            if ((int) $tersisa > 0) {
                continue;
            }

            $this->db->query('ALTER TABLE `' . $this->t() . '` MODIFY `' . $kolom . '` INT UNSIGNED NOT NULL');
        }
    }

    private function t(): string
    {
        return $this->db->prefixTable('pencatatan_cod');
    }
}
