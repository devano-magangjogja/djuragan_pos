<?php

namespace App\Database\Migrations;

class AddBeli extends \CodeIgniter\Database\Migration
{
    public function up()
    {
        $this->forge->addField([
            'id_beli' => [
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
            'stok_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
                'default'    => null,
            ],
            'kode' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
            ],
            'ukuran' => [
                'type'       => 'VARCHAR',
                'constraint' => 6,
                'null'       => true,
            ],
            'qty' => [
                'type'       => 'INT',
                'constraint' => 3,
                'unsigned'   => true,
            ],
            'harga' => [
                'type'       => 'INT',
                'constraint' => 7,
                'unsigned'   => true,
            ],
        ]);

        $this->forge->addKey('id_beli', true);
        $this->forge->createTable('dibeli', true);
    }

    public function down()
    {
        $this->forge->dropTable('dibeli');
    }
}
