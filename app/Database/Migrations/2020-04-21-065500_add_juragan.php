<?php

namespace App\Database\Migrations;

class AddJuragan extends \CodeIgniter\Database\Migration
{
    public function up()
    {
        $this->forge->addField([
            'id_juragan' => [
                'type'           => 'INT',
                'constraint'     => 5,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'juragan' => [
                'type'       => 'VARCHAR',
                'constraint' => 40,
            ],
            'nama_juragan' => [
                'type'       => 'VARCHAR',
                'constraint' => 60,
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
        $this->forge->addKey('id_juragan', true);
        $this->forge->createTable('juragan', true);
        $this->forge->addUniqueKey(['juragan']);
    }

    public function down()
    {
        $this->forge->dropTable('juragan');
    }
}
