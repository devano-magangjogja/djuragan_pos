<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Toko online yang dipegang sebuah juragan, dan hubungannya dengan CS.
 *
 * Toko ITU sendiri sudah ada di aplikasi ini (order_juragan + akses per akun di
 * order_relasi). Yang belum ada adalah sisi "toko di marketplace mana, URL-nya
 * apa, dan siapa CS-nya". Tabel ini jadi lapisan itu tanpa menduplikasi juragan:
 * setiap baris menunjuk juragan_id + platform, dan kunci unik menjaga satu toko
 * tidak terdaftar dua kali untuk platform yang sama.
 *
 * crm_toko_akses memisahkan dua hal yang sering tertukar: boleh MEMBUKA sebuah
 * toko (relasi, hak akses) dan sudah DIKENAL/BELUM toko itu bagi CS baru
 * (status pengenalan). CS yang sudah pegang tokonya tetap wajib cek rutin —
 * kolom status ini bukan pengganti checklist harian.
 */
class TabelCrmTokoOnline extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id_toko' => [
                'type'           => 'INT',
                'constraint'     => 10,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'juragan_id' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
            ],
            'platform' => [
                'type'       => 'VARCHAR',
                'constraint' => 40,
                'default'    => '',
            ],
            'nama' => [
                'type'       => 'VARCHAR',
                'constraint' => 120,
            ],
            'url' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],
            'aktif' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 1,
            ],
            'catatan' => [
                'type' => 'TEXT',
                'null' => true,
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
        $this->forge->addPrimaryKey('id_toko');
        $this->forge->addUniqueKey(['juragan_id', 'platform']);
        $this->forge->addKey('aktif');
        $this->forge->createTable('crm_toko_online', true);

        $this->forge->addField([
            'id_akses' => [
                'type'           => 'INT',
                'constraint'     => 10,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'toko_id' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
            ],
            'user_id' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
            ],
            'pengenalan' => [
                'type'       => 'ENUM',
                'constraint' => ['belum', 'sedang', 'sudah'],
                'default'    => 'belum',
            ],
            'mulai' => [
                'type' => 'DATE',
                'null' => true,
            ],
            'selesai' => [
                'type' => 'DATE',
                'null' => true,
            ],
            'materi' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'catatan' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'diverifikasi_oleh' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
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
        $this->forge->addPrimaryKey('id_akses');
        $this->forge->addUniqueKey(['toko_id', 'user_id']);
        $this->forge->addKey(['user_id', 'pengenalan']);
        $this->forge->createTable('crm_toko_akses', true);
    }

    public function down()
    {
        $this->forge->dropTable('crm_toko_akses', true);
        $this->forge->dropTable('crm_toko_online', true);
    }
}
