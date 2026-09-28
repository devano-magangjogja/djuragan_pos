<?php

namespace App\Database\Migrations;

class AddPelanggan extends \CodeIgniter\Database\Migration
{
    public function up()
    {
        $this->forge->addField([
            'id_pelanggan' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'nama_pelanggan' => [
                'type'       => 'VARCHAR',
                'constraint' => 60,
            ],
            'hp' => [
                'type'       => 'VARCHAR', // ["0812345789","0812345789"]
                'constraint' => 50,
                'null'       => true,
                'default'    => null,
            ],
            'cod' => [
                'type'       => 'ENUM',
                'constraint' => ['0', '1'],
                'default'    => '0',
            ],
            'kecamatan' => [
                'type'       => 'INT',
                'constraint' => 5,
                'unsigned'   => true,
                'null'       => true,
                'default'    => null,
            ],
            'kabupaten' => [
                'type'       => 'INT',
                'constraint' => 5,
                'unsigned'   => true,
                'null'       => true,
                'default'    => null,
            ],
            'provinsi' => [
                'type'       => 'INT',
                'constraint' => 5,
                'unsigned'   => true,
                'null'       => true,
                'default'    => null,
            ],
            'alamat' => [
                'type'    => 'TEXT',
                'null'    => true,
                'default' => null,
            ],
            'kodepos' => [
                'type'       => 'INT',
                'constraint' => 5,
                'null'       => true,
                'unsigned'   => true,
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
            'deleted_at' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
                'null'       => true,
                'default'    => null,
            ],
        ]);

        $this->forge->addKey('id_pelanggan', true);
        $this->forge->createTable('pelanggan', true);
    }

    public function down()
    {
        $this->forge->dropTable('pelanggan');
    }
}
