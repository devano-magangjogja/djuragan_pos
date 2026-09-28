<?php

namespace App\Database\Migrations;

class AddBiaya extends \CodeIgniter\Database\Migration
{
    public function up()
    {
        $this->forge->addField([
            'id_biaya' => [
                'type'           => 'INT',
                'constraint'     => 5,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'invoice_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'biaya_id' => [
                'type'       => 'ENUM',
                'constraint' => ['1', '2'], // 1: ongkir, 2: biaya lain
            ],
            'nominal' => [
                'type'       => 'INT',
                'constraint' => 7,
            ],
            'label' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'null'       => true,
                'default'    => null,
            ],
        ]);
        $this->forge->addKey('id_biaya', true);
        $this->forge->createTable('biaya', true);
    }

    public function down()
    {
        $this->forge->dropTable('biaya');
    }
}
