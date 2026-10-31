<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Catatan ulasan pelanggan.
 *
 * Tabel ini ada karena satu perbedaan yang tidak boleh disamakan: CS sudah
 * menghubungi pelanggan (tugasnya selesai) BERBEDA dengan ulasan yang benar-benar
 * diberikan pelanggan. Kalau keduanya disimpan di satu kolom, laporan "sudah
 * minta ulasan" dan "sudah dapat ulasan" jadi angka yang sama dan membohongi
 * siapa pun yang membacanya.
 *
 * platform diisi '' untuk permintaan umum (belum tahu akan diunggah ke marketplace
 * mana), jadi satu nota boleh punya beberapa baris ulasan — tapi hanya satu per
 * platform, dijaga kunci unik. Bintang boleh NULL: diminta tapi belum dibalas itu
 * keadaan normal, bukan data rusak.
 */
class TabelCrmUlasan extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id_ulasan' => [
                'type'           => 'INT',
                'constraint'     => 10,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'invoice_id' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
            ],
            'tugas_id' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
                'null'       => true,
            ],
            'platform' => [
                'type'       => 'VARCHAR',
                'constraint' => 40,
                'default'    => '',
            ],
            'status' => [
                'type'       => 'ENUM',
                'constraint' => ['diminta', 'diterima'],
                'default'    => 'diminta',
            ],
            'bintang' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'unsigned'   => true,
                'null'       => true,
            ],
            'komentar' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'diminta_oleh' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
                'null'       => true,
            ],
            'diterima_oleh' => [
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
        $this->forge->addPrimaryKey('id_ulasan');
        $this->forge->addUniqueKey(['invoice_id', 'platform']);
        $this->forge->addKey('status');
        $this->forge->addKey('tugas_id');
        $this->forge->createTable('crm_ulasan', true);
    }

    public function down()
    {
        $this->forge->dropTable('crm_ulasan', true);
    }
}
