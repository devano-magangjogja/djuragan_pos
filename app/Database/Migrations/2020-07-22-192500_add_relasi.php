<?php

namespace App\Database\Migrations;

class AddRelasi extends \CodeIgniter\Database\Migration
{
    public function up()
    {
        $this->forge->addField([
            'id_relasi' => [
                'type'           => 'INT',
                'constraint'     => 5,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'table' => [
                'type'       => 'INT',
                'constraint' => 5, // 1: juragan-user, 2: juragan-bank
                'unsigned'   => true,
            ],
            'juragan_id' => [
                'type'       => 'INT',
                'constraint' => 5,
                'unsigned'   => true,
            ],
            'val_id' => [
                'type'       => 'INT',
                'constraint' => 5,
                'unsigned'   => true,
            ],
        ]);

        $this->forge->addKey('id_relasi', true);
        $this->forge->createTable('relasi', true);
    }

    public function down()
    {
        $this->forge->dropTable('relasi');
    }
}
