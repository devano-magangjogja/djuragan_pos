<?php

namespace App\Database\Migrations;

class AddInvoice extends \CodeIgniter\Database\Migration
{
    public function up()
    {
        $this->forge->addField([
            'id_invoice' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'tanggal_pesan' => [
                'type' => 'DATE',
            ],
            'seri' => [
                'type'       => 'VARCHAR',
                'constraint' => 14, // LS3457648767 LS202204224900
            ],
            'juragan_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'pemesan_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'kirimKepada_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'user_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'status_pesanan' => [
                'type'       => 'ENUM',
                'constraint' => ['1', '2', '3'], // 1: pending, 2: diproses, 3: dibatalkan
                'default'    => '1',
            ],
            'status_pembayaran' => [
                'type'       => 'ENUM',
                'constraint' => ['1', '2', '3', '4', '5', '6'],
                // 1: belum bayar, 2: tunggu konfirmasi (belum bayar), 3: tunggu konfirmasi (kredit), 4: kredit, 5: kelebihan, 6: lunas
                'default' => '1',
            ],
            'status_pengiriman' => [
                'type'       => 'ENUM',
                'constraint' => ['1', '2', '3'], // 1: belum dikirim, 2: dikirim sebagian, 3: terkirim semua
                'default'    => '1',
            ],
            'keterangan' => [
                'type'    => 'TEXT',
                'null'    => true,
                'default' => null,
            ],
            'created_at' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
            ],
            'update_at' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
            ],
            'deleted_at' => [
                'type'       => 'INT',
                'constraint' => '10',
                'unsigned'   => true,
                'null'       => true,
                'default'    => null,
            ],
        ]);

        $this->forge->addKey('id_invoice', true);
        $this->forge->createTable('invoice', true);
    }

    public function down()
    {
        $this->forge->dropTable('invoice');
    }
}
