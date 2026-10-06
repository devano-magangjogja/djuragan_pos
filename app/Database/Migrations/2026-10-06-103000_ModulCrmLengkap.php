<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class ModulCrmLengkap extends Migration
{
    public function up()
    {
        $prefix = $this->db->getPrefix();

        // 1. Profil & Ukuran & Preferensi Customer CRM
        $this->forge->addField([
            'pelanggan_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'status_crm' => [
                'type'       => 'ENUM',
                'constraint' => ['prospek', 'baru', 'aktif', 'loyal', 'tidak_aktif'],
                'default'    => 'baru',
            ],
            'opt_in_broadcast' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 1,
            ],
            'catatan' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'preferensi_warna' => [
                'type'       => 'VARCHAR',
                'constraint' => 120,
                'null'       => true,
            ],
            'preferensi_bahan' => [
                'type'       => 'VARCHAR',
                'constraint' => 120,
                'null'       => true,
            ],
            'preferensi_model' => [
                'type'       => 'VARCHAR',
                'constraint' => 120,
                'null'       => true,
            ],
            'ukuran_baju_standar' => [
                'type'       => 'VARCHAR',
                'constraint' => 30,
                'null'       => true,
            ],
            'ukuran_celana_standar' => [
                'type'       => 'VARCHAR',
                'constraint' => 30,
                'null'       => true,
            ],
            'ukuran_jas_standar' => [
                'type'       => 'VARCHAR',
                'constraint' => 30,
                'null'       => true,
            ],
            'detail_ukuran' => [
                'type'    => 'TEXT',
                'null'    => true,
                'comment' => 'JSON ukuran terstruktur: lingkar dada, panjang lengan, dll',
            ],
            'created_at' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
                'null'       => true,
            ],
            'updated_at' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
                'null'       => true,
            ],
        ]);
        $this->forge->addPrimaryKey('pelanggan_id');
        $this->forge->createTable('crm_pelanggan_profil', true);

        // 2. Master Tag CRM
        $this->forge->addField([
            'id_tag' => [
                'type'           => 'INT',
                'constraint'     => 10,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'nama_tag' => [
                'type'       => 'VARCHAR',
                'constraint' => 60,
            ],
            'warna' => [
                'type'       => 'VARCHAR',
                'constraint' => 30,
                'default'    => 'primary',
            ],
            'created_at' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
                'null'       => true,
            ],
        ]);
        $this->forge->addPrimaryKey('id_tag');
        $this->forge->addUniqueKey('nama_tag');
        $this->forge->createTable('crm_tag', true);

        // 3. Relasi Tag Pelanggan
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 10,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'pelanggan_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'tag_id' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
            ],
            'created_at' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
                'null'       => true,
            ],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey(['pelanggan_id', 'tag_id']);
        $this->forge->createTable('crm_pelanggan_tag', true);

        // 4. Timeline Aktivitas Pelanggan (Multi-channel)
        $this->forge->addField([
            'id_aktivitas' => [
                'type'           => 'INT',
                'constraint'     => 10,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'pelanggan_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'tipe' => [
                'type'       => 'ENUM',
                'constraint' => [
                    'wa', 'telepon', 'datang', 'fitting', 'pembayaran', 
                    'order', 'pengambilan', 'pengembalian', 'catatan', 
                    'follow_up', 'broadcast', 'sistem'
                ],
                'default' => 'catatan',
            ],
            'judul' => [
                'type'       => 'VARCHAR',
                'constraint' => 200,
            ],
            'deskripsi' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'invoice_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
            ],
            'user_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
            ],
            'created_at' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
            ],
        ]);
        $this->forge->addPrimaryKey('id_aktivitas');
        $this->forge->addKey('pelanggan_id');
        $this->forge->addKey('created_at');
        $this->forge->createTable('crm_aktivitas', true);

        // 5. Follow-up & Reminder
        $this->forge->addField([
            'id_followup' => [
                'type'           => 'INT',
                'constraint'     => 10,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'pelanggan_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'tipe' => [
                'type'       => 'ENUM',
                'constraint' => ['customer', 'tagihan', 'reminder'],
                'default'    => 'customer',
            ],
            'kategori' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'default'    => 'lainnya',
            ],
            'judul' => [
                'type'       => 'VARCHAR',
                'constraint' => 200,
            ],
            'catatan' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'tanggal_jatuh_tempo' => [
                'type' => 'DATE',
                'null' => true,
            ],
            'status' => [
                'type'       => 'ENUM',
                'constraint' => ['menunggu', 'selesai', 'dibatalkan'],
                'default'    => 'menunggu',
            ],
            'invoice_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
            ],
            'user_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
            ],
            'selesai_oleh' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
            ],
            'selesai_at' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
                'null'       => true,
            ],
            'created_at' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
            ],
            'updated_at' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
            ],
        ]);
        $this->forge->addPrimaryKey('id_followup');
        $this->forge->addKey(['pelanggan_id', 'status']);
        $this->forge->addKey('tanggal_jatuh_tempo');
        $this->forge->createTable('crm_followup', true);

        // Seed Tag Awal
        $defaultTags = [
            ['nama_tag' => 'Pelanggan Tetap', 'warna' => 'primary'],
            ['nama_tag' => 'Rental', 'warna' => 'info'],
            ['nama_tag' => 'Custom', 'warna' => 'success'],
            ['nama_tag' => 'VIP', 'warna' => 'warning'],
            ['nama_tag' => 'Calon Customer', 'warna' => 'secondary'],
            ['nama_tag' => 'Pernikahan', 'warna' => 'dark'],
            ['nama_tag' => 'Belum Bayar', 'warna' => 'danger'],
            ['nama_tag' => 'Sering Sewa', 'warna' => 'info'],
        ];
        $time = time();
        foreach ($defaultTags as $dt) {
            $this->db->query("INSERT IGNORE INTO `{$prefix}crm_tag` (nama_tag, warna, created_at) VALUES (?, ?, ?)", [
                $dt['nama_tag'],
                $dt['warna'],
                $time,
            ]);
        }
    }

    public function down()
    {
        $this->forge->dropTable('crm_followup', true);
        $this->forge->dropTable('crm_aktivitas', true);
        $this->forge->dropTable('crm_pelanggan_tag', true);
        $this->forge->dropTable('crm_tag', true);
        $this->forge->dropTable('crm_pelanggan_profil', true);
    }
}
