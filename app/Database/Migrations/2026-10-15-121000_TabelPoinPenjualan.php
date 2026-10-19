<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Ledger poin gaji.
 *
 * Setiap nota yang mencapai status Lunas (5/6) menyumbangkan satu baris per
 * item barang ke tabel ini, atas nama pembuat nota (invoice.user_id). Nilainya
 * dipotret pada saat pemberian (poin_satuan + qty + poin_total) supaya perubahan
 * poin produk di kemudian hari tidak mengubah gaji yang sudah dihitung.
 *
 * Barisnya dihapus saat nota tidak lagi lunas (pembayaran dibatalkan atau nota
 * dihapus) — "cabut otomatis". Karena itu tabel ini selalu mencerminkan poin
 * yang berlaku sekarang, dan laporan akumulasi tinggal menjumlahnya.
 */
class TabelPoinPenjualan extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id_poin' => [
                'type'           => 'INT',
                'constraint'     => 10,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'invoice_id' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
            ],
            'user_id' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
            ],
            'juragan_id' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
                'null'       => true,
            ],
            'stok_id' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
                'null'       => true,
            ],
            'produk_id' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
                'null'       => true,
            ],
            'kode' => [
                'type'       => 'VARCHAR',
                'constraint' => 60,
                'null'       => true,
            ],
            'ukuran' => [
                'type'       => 'VARCHAR',
                'constraint' => 6,
                'null'       => true,
            ],
            'qty' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
                'default'    => 0,
            ],
            'poin_satuan' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
                'default'    => 0,
            ],
            'poin_total' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
                'default'    => 0,
            ],
            'created_at' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id_poin');
        $this->forge->addKey('invoice_id');
        $this->forge->addKey('user_id');
        $this->forge->addKey('juragan_id');
        $this->forge->addKey('created_at');
        $this->forge->createTable('poin_penjualan', true);
    }

    public function down()
    {
        $this->forge->dropTable('poin_penjualan', true);
    }
}
