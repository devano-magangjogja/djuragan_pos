<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Rincian pesanan: kolom JSON untuk inputan terstruktur di form Tulis Orderan
 * (jadwal sewa, jaminan, spesifikasi custom per produk). Data lama tetap NULL
 * sehingga hanya ditampilkan bila memang diisi lewat form baru.
 */
class AddRincianPesanan extends Migration
{
    public function up()
    {
        $kolom = [
            'type'       => 'text',
            'null'       => true,
            'after'      => 'keterangan',
            'comment'    => 'JSON rincian terstruktur; NULL untuk orderan lama',
        ];

        $this->forge->addColumn('invoice', ['rincian' => $kolom]);

        $kolom_item = $kolom;
        $kolom_item['after'] = 'harga';

        $this->forge->addColumn('dibeli', ['rincian' => $kolom_item]);
    }

    public function down()
    {
        $this->forge->dropColumn('invoice', 'rincian');
        $this->forge->dropColumn('dibeli', 'rincian');
    }
}
