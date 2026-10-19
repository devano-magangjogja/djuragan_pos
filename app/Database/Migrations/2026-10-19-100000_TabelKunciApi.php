<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Kunci API untuk pihak luar (mis. website partner yang menampilkan katalog).
 *
 * Yang disimpan BUKAN kunci aslinya melainkan hash SHA-256-nya. Kebocoran basis
 * data dengan begitu tidak menyerahkan kunci yang masih bisa dipakai, dan kunci
 * mentah hanya ditunjukkan sekali ketika pembuatannya — persis pola token di
 * layanan lain, dan alasan kenapa layarnya tidak punya tombol "lihat kunci".
 *
 * `hak` dan `juragan` sengaja CSV pendek, bukan tabel relasi: keduanya cuma
 * dibaca utuh oleh filter (satu baris per request) dan tidak pernah disaring
 * dari sisi lain, jadi tabel tambahan hanya menambah kerja tanpa keuntungan.
 * Daftarnya divalidasi di KunciApiModel, bukan diserahkan ke peramban.
 *
 * `batas_harian` pembatas permintaan per hari kunci; 0 berarti tanpa batas.
 * Hitungannya lewat cache (lihat KunciApiModel::hemat()), jadi tidak ada tabel
 * log yang tumbuh tanpa batas.
 */
class TabelKunciApi extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id_kunci' => [
                'type'           => 'INT',
                'constraint'     => 10,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            // siapa pemanggilnya, ditulis pemilik toko: "Website Toko A"
            'nama' => [
                'type'       => 'VARCHAR',
                'constraint' => 60,
            ],
            // hash sha256 (64 karakter hex) dari token yang diberikan ke pemanggil
            'kunci' => [
                'type'       => 'CHAR',
                'constraint' => 64,
            ],
            // hak akses: katalog,stok,laporan,lacak — dilihat dari HAK di model
            'hak' => [
                'type'       => 'VARCHAR',
                'constraint' => 120,
            ],
            // id_toko yang boleh dibuka kunci ini; kosong = tidak ada (ditolak)
            'juragan' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
            ],
            'batas_harian' => [
                'type'       => 'SMALLINT',
                'constraint' => 5,
                'unsigned'   => true,
            ],
            // 0 = dicabut tapi barisnya tetap ada, jejak auditnya tidak hilang
            'hidup' => [
                'type'     => 'TINYINT',
                'constraint' => 1,
                'default'  => 1,
            ],
            'terakhir_pakai' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
                'null'       => true,
            ],
            'created_at' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'updated_at' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'deleted_at' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id_kunci');
        $this->forge->addUniqueKey('kunci');
        $this->forge->addKey('deleted_at');
        $this->forge->createTable('kunci_api', true);
    }

    public function down()
    {
        $this->forge->dropTable('kunci_api', true);
    }
}
