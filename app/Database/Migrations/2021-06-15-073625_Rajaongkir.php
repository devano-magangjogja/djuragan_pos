<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class Rajaongkir extends Migration
{
    public function up()
    {
        // PROVINCE
        $this->forge->addField([
            'province_id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'province_name' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
            ],
        ]);

        $this->forge->addKey('province_id', true);
        $this->forge->createTable('provinces', true);

        // CITY
        $this->forge->addField([
            'city_id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'province_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'city_name' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
            ],
            'postal_code' => [
                'type'       => 'CHAR',
                'constraint' => 5,
            ],
        ]);

        $this->forge->addKey('city_id', true);
        $this->forge->createTable('cities', true);

        $this->forge->addField([
            'subdistrict_id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'city_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'subdistrict_name' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
            ],
        ]);

        $this->forge->addKey('subdistrict_id', true);
        $this->forge->createTable('subdistricts', true);

        // run seeder
        $seeder = \Config\Database::seeder();
        $seeder->call('Cities');
        $seeder->call('Subdistricts');
        $seeder->call('Provinces');
    }

    public function down()
    {
        $this->forge->dropTable('provinces');
        $this->forge->dropTable('cities');
        $this->forge->dropTable('subdistricts');
    }
}
