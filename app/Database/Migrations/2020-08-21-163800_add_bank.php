<?php

namespace App\Database\Migrations;

class AddBank extends \CodeIgniter\Database\Migration
{
    public function up()
    {
        $this->forge->addField([
            'id_bank' => [
                'type'           => 'INT',
                'constraint'     => 5,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'nama_bank' => [
                'type'       => 'ENUM',
                'constraint' => ['bca', 'bri', 'bni', 'mandiri', 'edc', 'cod', 'lainnya'],
            ],
            'tipe_bank' => [
                'type'       => 'ENUM',
                'constraint' => ['1', '2', '3'],
                'null'       => true,
                'default'    => null,
                'comment'    => 'null:cod, 1:bank, 2:edc, 3:lainnya',
            ],
            'rekening' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
            ],
            'atas_nama' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
            ],
            'created_at' => [
                'type'       => 'INT',
                'constraint' => '10',
                'unsigned'   => true,
            ],
            'updated_at' => [
                'type'       => 'INT',
                'constraint' => '10',
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

        $this->forge->addKey('id_bank', true);
        $this->forge->createTable('bank', true);

        $seeder = \Config\Database::seeder();
        $seeder->call('BankSeeder');
    }

    public function down()
    {
        $this->forge->dropTable('bank');
    }
}
