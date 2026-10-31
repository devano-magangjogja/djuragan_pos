<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Pustaka SOP + templat tugas untuk antrean CS.
 *
 * Keduanya menjawab satu masalah yang sama: pekerjaan yang bentuknya berulang
 * tidak boleh ditulis ulang tangan setiap hari. `crm_sop` menyimpan aturannya
 * (dibaca CS), `crm_tugas_template` + itemnya menyimpan resep tugasnya (dipakai
 * untuk mengisi antrean sekali klik).
 *
 * Item templat menyimpan `jenis` yang sama dengan `crm_tugas`, supaya templat
 * lama tetap bisa dibaca kalau daftar jenisnya bertambah nanti.
 */
class TabelCrmSop extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id_sop' => [
                'type'           => 'INT',
                'constraint'     => 10,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'judul' => [
                'type'       => 'VARCHAR',
                'constraint' => 200,
            ],
            'isi' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'kategori' => [
                'type'       => 'VARCHAR',
                'constraint' => 40,
                'default'    => 'SOP',
            ],
            'untuk' => [
                'type'       => 'ENUM',
                'constraint' => ['semua', 'cs', 'admin'],
                'default'    => 'semua',
            ],
            'urut' => [
                'type'       => 'SMALLINT',
                'constraint' => 5,
                'unsigned'   => true,
                'default'    => 0,
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
            'diubah_oleh' => [
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
        $this->forge->addPrimaryKey('id_sop');
        $this->forge->addKey(['aktif', 'urut']);
        $this->forge->createTable('crm_sop', true);

        $this->forge->addField([
            'id_template' => [
                'type'           => 'INT',
                'constraint'     => 10,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'nama' => [
                'type'       => 'VARCHAR',
                'constraint' => 120,
            ],
            'keterangan' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
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
        $this->forge->addPrimaryKey('id_template');
        $this->forge->addKey('aktif');
        $this->forge->createTable('crm_tugas_template', true);

        $this->forge->addField([
            'id_item' => [
                'type'           => 'INT',
                'constraint'     => 10,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'template_id' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
            ],
            'judul' => [
                'type'       => 'VARCHAR',
                'constraint' => 200,
            ],
            'deskripsi' => [
                'type'       => 'TEXT',
                'null'       => true,
            ],
            'jenis' => [
                'type'       => 'ENUM',
                'constraint' => ['catatan_wa', 'followup_pelanggan', 'reminder_transaksi', 'checklist_toko', 'pemeriksaan_email', 'lain'],
                'default'    => 'lain',
            ],
            'prioritas' => [
                'type'       => 'ENUM',
                'constraint' => ['rendah', 'normal', 'tinggi', 'mendesak'],
                'default'    => 'normal',
            ],
            'tenggat_hari' => [
                'type'           => 'SMALLINT',
                'constraint'     => 4,
                'signed'         => false,
                'default'        => 1,
            ],
            'urut' => [
                'type'       => 'SMALLINT',
                'constraint' => 5,
                'unsigned'   => true,
                'default'    => 0,
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
        $this->forge->addKey(['template_id', 'urut']);
        $this->forge->createTable('crm_tugas_template_item', true);
    }

    public function down()
    {
        $this->forge->dropTable('crm_tugas_template_item', true);
        $this->forge->dropTable('crm_tugas_template', true);
        $this->forge->dropTable('crm_sop', true);
    }
}
