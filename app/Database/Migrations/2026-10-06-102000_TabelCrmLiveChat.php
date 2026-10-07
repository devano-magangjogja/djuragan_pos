<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class TabelCrmLiveChat extends Migration
{
    public function up()
    {
        // 1. Tabel crm_chat_percakapan
        $this->forge->addField([
            'id_percakapan' => [
                'type'           => 'INT',
                'constraint'     => 10,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'nomor_wa' => [
                'type'       => 'VARCHAR',
                'constraint' => 25,
            ],
            'pelanggan_id' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
                'null'       => true,
            ],
            'nama_kontak' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => true,
            ],
            'pesan_terakhir' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'waktu_terakhir' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
                'null'       => true,
            ],
            'arah_terakhir' => [
                'type'       => 'ENUM',
                'constraint' => ['keluar', 'masuk'],
                'default'    => 'keluar',
            ],
            'unread_admin' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 0,
            ],
            'created_at' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
            ],
            'updated_at' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
            ],
        ]);
        $this->forge->addPrimaryKey('id_percakapan');
        $this->forge->addUniqueKey('nomor_wa');
        $this->forge->addKey('waktu_terakhir');
        $this->forge->createTable('crm_chat_percakapan', true);

        // 2. Tabel crm_chat_pesan
        $this->forge->addField([
            'id_pesan' => [
                'type'           => 'INT',
                'constraint'     => 10,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'percakapan_id' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
            ],
            'nomor_wa' => [
                'type'       => 'VARCHAR',
                'constraint' => 25,
            ],
            'arah' => [
                'type'       => 'ENUM',
                'constraint' => ['keluar', 'masuk'],
                'default'    => 'keluar',
            ],
            'tipe' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'default'    => 'text',
            ],
            'isi_pesan' => [
                'type' => 'TEXT',
            ],
            'status' => [
                'type'       => 'ENUM',
                'constraint' => ['terkirim', 'diterima', 'dibaca', 'gagal'],
                'default'    => 'terkirim',
            ],
            'wa_message_id' => [
                'type'       => 'VARCHAR',
                'constraint' => 120,
                'null'       => true,
            ],
            'user_id' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
                'null'       => true,
            ],
            'created_at' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
            ],
        ]);
        $this->forge->addPrimaryKey('id_pesan');
        $this->forge->addKey(['percakapan_id', 'created_at']);
        $this->forge->createTable('crm_chat_pesan', true);
    }

    public function down()
    {
        $this->forge->dropTable('crm_chat_pesan', true);
        $this->forge->dropTable('crm_chat_percakapan', true);
    }
}
