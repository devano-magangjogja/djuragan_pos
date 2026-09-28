<?php

namespace App\Database\Migrations;

class AddPengiriman extends \CodeIgniter\Database\Migration
{
    public function up()
    {
        $this->forge->addField([
            'id_pengiriman' => [
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
            'kurir' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
            ],
            'ongkir' => [
                'type'       => 'INT',
                'constraint' => 5,
                'unsigned'   => true,
            ],
            'resi' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
            ],
            'qty_kirim' => [
                'type'       => 'INT',
                'constraint' => 3,
                'unsigned'   => true,
            ],
            'tanggal_kirim' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
            ],
        ]);

        $this->forge->addKey('id_pengiriman', true);
        $this->forge->createTable('pengiriman', true);
    }

    public function down()
    {
        $this->forge->dropTable('pengiriman');
    }
}
