<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddAsalOrder extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'SMALLINT',
                'constraint'     => 5,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'label' => [
                'type'       => 'VARCHAR',
                'constraint' => '25',
            ],
            'status' => [
                'type'       => 'ENUM',
                'constraint' => ['0', '1'],
                'comment'    => '0 = tidak aktif, 1 = aktif',
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->createTable('asal_order', true);

        // run seeder
        $seeder = \Config\Database::seeder();
        $seeder->call('AsalOrder');
    }

    public function down()
    {
        $this->forge->dropTable('asal_order', true);
    }
}
