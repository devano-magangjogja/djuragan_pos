<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Checklist berulang: pemeriksaan toko online harian DAN pemeriksaan email.
 *
 * Keduanya sengaja memakai satu mesin (kolom `jenis`) karena bentuk kerjanya sama
 * persis: seseorang membuka daftar periksa pada satu tanggal, menandai tiap butir
 * selesai / belum / tidak relevan (dengan alasan), lalu menutup sesi. Membuat tabel
 * terpisah untuk email akan menghasilkan dua kode yang nyaris identik dan dua
 * laporan yang tidak bisa dibandingkan.
 *
 * `kunci` pada sesi adalah cara database mencegah sesi kembar: satu toko satu
 * checklist per tanggal, satu akun email satu pemeriksaan per tanggal. Kunci itu
 * dibangun dari (jenis, sasaran, tanggal) karena kolom boleh NULL dan MySQL tidak
 * pernah menganggap dua NULL sama.
 */
class TabelCrmChecklist extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id_item' => [
                'type'           => 'INT',
                'constraint'     => 10,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'jenis' => [
                'type'       => 'ENUM',
                'constraint' => ['toko', 'email'],
                'default'    => 'toko',
            ],
            'toko_id' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
                'null'       => true,
            ],
            'judul' => [
                'type'       => 'VARCHAR',
                'constraint' => 200,
            ],
            'hint' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],
            'urut' => [
                'type'       => 'SMALLINT',
                'constraint' => 5,
                'unsigned'   => true,
                'default'    => 0,
            ],
            'wajib' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 1,
            ],
            'aktif' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 1,
            ],
            'dibuat_oleh' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
                'null'       => true,
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
        $this->forge->addPrimaryKey('id_item');
        $this->forge->addKey(['jenis', 'aktif', 'urut']);
        $this->forge->addKey('toko_id');
        $this->forge->createTable('crm_checklist_item', true);

        $this->forge->addField([
            'id_run' => [
                'type'           => 'INT',
                'constraint'     => 10,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'kunci' => [
                'type'       => 'VARCHAR',
                'constraint' => 120,
            ],
            'jenis' => [
                'type'       => 'ENUM',
                'constraint' => ['toko', 'email'],
                'default'    => 'toko',
            ],
            'toko_id' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
                'null'       => true,
            ],
            'akun_email' => [
                'type'       => 'VARCHAR',
                'constraint' => 120,
                'null'       => true,
            ],
            'tanggal' => [
                'type' => 'DATE',
            ],
            'tugas_id' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
                'null'       => true,
            ],
            'status' => [
                'type'       => 'ENUM',
                'constraint' => ['berjalan', 'selesai'],
                'default'    => 'berjalan',
            ],
            'ada_tindakan' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 0,
            ],
            'catatan' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'dikerjakan_oleh' => [
                'type'       => 'INT',
                'constraint' => 10,
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
                'null'       => true,
            ],
            'updated_at' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
                'null'       => true,
            ],
        ]);
        $this->forge->addPrimaryKey('id_run');
        $this->forge->addUniqueKey('kunci');
        $this->forge->addKey(['jenis', 'tanggal']);
        $this->forge->addKey(['toko_id', 'tanggal']);
        $this->forge->addKey('status');
        $this->forge->createTable('crm_checklist_run', true);

        $this->forge->addField([
            'id_hasil' => [
                'type'           => 'INT',
                'constraint'     => 10,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'run_id' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
            ],
            'item_id' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
            ],
            'judul' => [
                'type'       => 'VARCHAR',
                'constraint' => 200,
            ],
            'status' => [
                'type'       => 'ENUM',
                'constraint' => ['belum', 'selesai', 'tidak_relevan'],
                'default'    => 'belum',
            ],
            'alasan' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],
            'catatan' => [
                'type'       => 'VARCHAR',
                'constraint' => 500,
                'null'       => true,
            ],
            'perlu_tindakan' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 0,
            ],
            'diisi_oleh' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
                'null'       => true,
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
        $this->forge->addPrimaryKey('id_hasil');
        $this->forge->addUniqueKey(['run_id', 'item_id']);
        $this->forge->addKey('status');
        $this->forge->createTable('crm_checklist_hasil', true);
    }

    public function down()
    {
        $this->forge->dropTable('crm_checklist_hasil', true);
        $this->forge->dropTable('crm_checklist_run', true);
        $this->forge->dropTable('crm_checklist_item', true);
    }
}
