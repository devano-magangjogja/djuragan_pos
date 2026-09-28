<?php

namespace App\Database\Migrations;

class AddLabel extends \CodeIgniter\Database\Migration
{
    public function up()
    {
        $this->forge->addField([
            'id_label' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'invoice_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'source_id' => [
                'type'       => 'INT',
                'constraint' => 5,
                'unsigned'   => true,
            ],
            'label' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'null'       => true,
                'default'    => null,
            ],
        ]);

        $this->forge->addKey('id_label', true);
        $this->forge->createTable('label_invoice', true);
    }

    public function down()
    {
        $this->forge->dropTable('label_invoice');
    }
}
