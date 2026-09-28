<?php

namespace App\Database\Migrations;

class AddInvoiceStatus extends \CodeIgniter\Database\Migration
{
    public function up()
    {
        $this->forge->addField([
            'id_status' => [
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
            /*
             * 1: data pesanan lengkap atau tidak
             * 2: bahan produk ada atau tidak
             * 3: masuk sablon atau belum
             * 4: masuk bordir atau belum
             * 5: masuk penjahit atau belum
             * 6: masuk QC atau belum
             * 7: dipacking atau belum
             *
             */
            'status' => [
                'type'       => 'ENUM',
                'constraint' => ['1', '2', '3', '4', '5', '6', '7'],
                'null'       => true,
                'default'    => null,
            ],
            'tanggal_masuk' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
                'null'       => true,
                'default'    => null,
            ],
            'tanggal_selesai' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
                'null'       => true,
                'default'    => null,
            ],
            'keterangan_masuk' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => true,
                'default'    => null,
            ],
            'keterangan_selesai' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => true,
                'default'    => null,
            ],
        ]);

        $this->forge->addKey('id_status', true);
        $this->forge->createTable('invoice_status', true);
    }

    public function down()
    {
        $this->forge->dropTable('invoice_status');
    }
}
