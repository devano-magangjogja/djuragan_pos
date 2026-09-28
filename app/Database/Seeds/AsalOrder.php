<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class AsalOrder extends Seeder
{
    public function run()
    {
        $label = [
            [
                'id'     => '1',
                'label'  => 'Blibli',
                'status' => '1',
            ],
            [
                'id'     => '12',
                'label'  => 'OLX',
                'status' => '1',
            ],
            [
                'id'     => '2',
                'label'  => 'Bukalapak',
                'status' => '1',
            ],
            [
                'id'     => '3',
                'label'  => 'Facebook',
                'status' => '1',
            ],
            [
                'id'     => '4',
                'label'  => 'Instagram',
                'status' => '1',
            ],
            [
                'id'     => '5',
                'label'  => 'Lazada',
                'status' => '1',
            ],
            [
                'id'     => '7',
                'label'  => 'Shopee',
                'status' => '1',
            ],
            [
                'id'     => '11',
                'label'  => 'Tokopedia',
                'status' => '1',
            ],
            [
                'id'     => '9',
                'label'  => 'WhatsApp',
                'status' => '1',
            ],
            [
                'id'     => '10',
                'label'  => 'Zalora',
                'status' => '1',
            ],
            [
                'id'     => '13',
                'label'  => 'Meesho',
                'status' => '1',
            ],
            [
                'id'     => '8',
                'label'  => 'Web/App lain',
                'status' => '1',
            ],
            [
                'id'     => '6',
                'label'  => 'Offline Store/COD',
                'status' => '1',
            ],
        ];

        $this->db->table('asal_order')->insertBatch($label);
    }
}
