<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Keterangan stok jadi catatan panjang: 120 karakter terlalu cepat habis
 * untuk deskripsi bahan/warna, dan validasinya membuat penyimpanan gagal
 * tanpa solusi bagi pengguna.
 */
class LebarkanKeteranganStok extends Migration
{
    public function up()
    {
        $this->forge->modifyColumn('stok', [
            'keterangan' => [
                'type'       => 'VARCHAR',
                'constraint' => 500,
                'null'       => true,
                'default'    => null,
            ],
        ]);
    }

    public function down()
    {
        $this->forge->modifyColumn('stok', [
            'keterangan' => [
                'type'       => 'VARCHAR',
                'constraint' => 120,
                'null'       => true,
                'default'    => null,
            ],
        ]);
    }
}
