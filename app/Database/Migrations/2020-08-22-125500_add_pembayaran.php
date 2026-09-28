<?php

namespace App\Database\Migrations;

class AddPembayaran extends \CodeIgniter\Database\Migration
{
    public function up()
    {
        $this->forge->addField([
            'id_pembayaran' => [
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
            'sumber_dana' => [ // dana dari tabel bank
                'type'       => 'INT',
                'constraint' => 5,
                'unsigned'   => true,
                'null'       => true,
                'default'    => null,
            ],
            'total_pembayaran' => [
                'type'       => 'INT',
                'constraint' => 7,
                'unsigned'   => true,
            ],
            'status' => [
                'type'       => 'ENUM',
                'constraint' => ['1', '2', '3'], // 1: belum dicek, 2: dana tidak ada, 3: dana ada
                'default'    => '1',
            ],
            'tanggal_pembayaran' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
            ],
            'tanggal_cek' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
                'null'       => true,
                'default'    => null,
            ],
        ]);

        $this->forge->addKey('id_pembayaran', true);
        $this->forge->createTable('pembayaran', true);
    }

    public function down()
    {
        $this->forge->dropTable('pembayaran');
    }
}
