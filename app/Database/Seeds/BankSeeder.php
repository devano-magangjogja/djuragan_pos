<?php

namespace App\Database\Seeds;

class BankSeeder extends \CodeIgniter\Database\Seeder
{
    public function run()
    {
        $time = time();
        $data = [
            [
                'nama_bank'  => 'lainnya',
                'atas_nama'  => 'Lainnya',
                'rekening'   => 'Lainnya',
                'tipe_bank'  => '3',
                'created_at' => $time,
                'updated_at' => $time,

            ],
            [
                'nama_bank'  => 'cod',
                'atas_nama'  => 'C.O.D',
                'rekening'   => 'C.O.D',
                'created_at' => $time,
                'updated_at' => $time,
            ],
        ];

        for ($i = 0; $i < count($data); $i++) {
            // Using Query Builder
            $this->db->table('bank')->insert($data[$i]);
        }
    }
}
