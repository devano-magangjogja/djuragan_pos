<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Fondasi task management CS: antrean tugas dan riwayat per tugas.
 *
 * crm_tugas adalah satu-satunya tempat tugas operasional CS dicatat — follow-up
 * pelanggan, reminder transaksi (DP/pelunasan/permintaan ulasan), checklist toko,
 * dan pemeriksaan email semuanya bermuara ke sini supaya antrean, penugasan, dan
 * perhitungan keterlambatan hanya punya satu versi.
 *
 * crm_tugas_event adalah append-only. Ia tidak pernah diubah atau dihapus lewat
 * UI, jadi riwayat pemindahan penanggung jawab tetap terbaca walaupun tugas sudah
 * selesai — persis yang diminta untuk audit.
 *
 * Kolom kunci_unik meniadakan reminder ganda untuk satu pemicu yang sama: baris
 * generated diisi misalnya "dp:123" sehingga INSERT kedua untuk nota yang sama
 * ditolak database, bukan dicek dua kali di PHP.
 */
class TabelCrmTugas extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id_tugas' => [
                'type'           => 'INT',
                'constraint'     => 10,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'judul' => [
                'type'       => 'VARCHAR',
                'constraint' => 200,
            ],
            'deskripsi' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'jenis' => [
                'type'       => 'ENUM',
                'constraint' => ['catatan_wa', 'followup_pelanggan', 'reminder_transaksi', 'checklist_toko', 'pemeriksaan_email', 'lain'],
                'default'    => 'lain',
            ],
            'status' => [
                'type'       => 'ENUM',
                'constraint' => ['queued', 'assigned', 'in_progress', 'waiting', 'completed', 'cancelled'],
                'default'    => 'queued',
            ],
            'prioritas' => [
                'type'       => 'ENUM',
                'constraint' => ['rendah', 'normal', 'tinggi', 'mendesak'],
                'default'    => 'normal',
            ],
            'sumber' => [
                'type'       => 'ENUM',
                'constraint' => ['manual', 'sistem'],
                'default'    => 'manual',
            ],
            'kunci_unik' => [
                'type'       => 'VARCHAR',
                'constraint' => 80,
                'null'       => true,
            ],
            'pelanggan_id' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
                'null'       => true,
            ],
            'invoice_id' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
                'null'       => true,
            ],
            'juragan_id' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
                'null'       => true,
            ],
            'dibuat_oleh' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
                'null'       => true,
            ],
            'dikerjakan_oleh' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
                'null'       => true,
            ],
            'dikerjakan_at' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
                'null'       => true,
            ],
            'cara_penugasan' => [
                'type'       => 'ENUM',
                'constraint' => ['ambil', 'ditetapkan', 'operan', 'sistem'],
                'null'       => true,
            ],
            'tenggat' => [
                'type' => 'DATE',
                'null' => true,
            ],
            'susul_at' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
                'null'       => true,
            ],
            'catatan' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'alasan_batal' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],
            'selesai_oleh' => [
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
        $this->forge->addPrimaryKey('id_tugas');
        $this->forge->addKey(['status', 'tenggat']);
        $this->forge->addKey(['dikerjakan_oleh', 'status']);
        $this->forge->addKey(['juragan_id', 'status']);
        $this->forge->addKey('pelanggan_id');
        $this->forge->addKey('invoice_id');
        $this->forge->addUniqueKey('kunci_unik');
        $this->forge->createTable('crm_tugas', true);

        $this->forge->addField([
            'id_event' => [
                'type'           => 'INT',
                'constraint'     => 10,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'tugas_id' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
            ],
            'jenis' => [
                'type'       => 'VARCHAR',
                'constraint' => 30,
            ],
            'dari_user_id' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
                'null'       => true,
            ],
            'ke_user_id' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
                'null'       => true,
            ],
            'status_lama' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'null'       => true,
            ],
            'status_baru' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'null'       => true,
            ],
            'catatan' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'alasan' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],
            'kunci_idempoten' => [
                'type'       => 'VARCHAR',
                'constraint' => 80,
                'null'       => true,
            ],
            'user_id' => [
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
        ]);
        $this->forge->addPrimaryKey('id_event');
        $this->forge->addKey(['tugas_id', 'created_at']);
        $this->forge->addUniqueKey('kunci_idempoten');
        $this->forge->createTable('crm_tugas_event', true);
    }

    public function down()
    {
        $this->forge->dropTable('crm_tugas_event', true);
        $this->forge->dropTable('crm_tugas', true);
    }
}
