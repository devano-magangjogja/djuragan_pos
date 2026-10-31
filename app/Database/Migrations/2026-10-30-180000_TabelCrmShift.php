<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Jam kerja CS, dipakai mesin operan otomatis.
 *
 * Satu baris = satu jendela kerja satu orang pada satu tanggal. Tabel ini sengaja
 * kecil: yang dibutuhkan sistem tugas hanyalah tahu "jam berapa kerjaan orang ini
 * harus diserahkan ke antrean", bukan absensi, lembur, atau penggajian.
 *
 * selesai < mulai artinya shift malam yang melewati tengah malam — batas akhirnya
 * dihitung sehari berikutnya, jadi shift 22:00–06:00 tidak dianggap selesai jam 6
 * pagi di tanggal yang sama.
 */
class TabelCrmShift extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id_shift' => [
                'type'           => 'INT',
                'constraint'     => 10,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'user_id' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
            ],
            'juragan_id' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
                'null'       => true,
            ],
            'tanggal' => [
                'type' => 'DATE',
            ],
            'mulai' => [
                'type'       => 'TIME',
            ],
            'selesai' => [
                'type'       => 'TIME',
            ],
            'catatan' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],
            'dibuat_oleh' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
                'null'       => true,
            ],
            'created_at' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
                'null'       => true,
            ],
            'updated_at' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
                'null'       => true,
            ],
        ]);
        $this->forge->addPrimaryKey('id_shift');
        // satu orang satu jendela per tanggal: kunci ini yang membuat layar
        // "Jam shift" boleh menimpa barisnya sendiri tanpa membuat duplikat
        $this->forge->addUniqueKey(['user_id', 'tanggal']);
        $this->forge->addKey(['tanggal', 'selesai']);
        $this->forge->createTable('crm_shift', true);
    }

    public function down()
    {
        $this->forge->dropTable('crm_shift', true);
    }
}
