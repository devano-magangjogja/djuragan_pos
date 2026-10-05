<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Master stok per juragan.
 *
 * order_dibeli.stok_id sudah ada sejak migrasi 2020-08-12 tetapi tidak pernah
 * punya tabel rujukan, sehingga stok barang tidak pernah bisa dipantau. Tabel
 * ini mengisi kekosongan itu: satu baris untuk tiap kombinasi juragan + kode
 * produk + ukuran, lengkap dengan harga jual dan jumlah unit yang tersedia.
 *
 * Tidak ada data lama yang disentuh. order_produk (master nama produk) dan
 * order_ukuran (master ukuran) tetap jadi sumber daftar pilihan di halaman
 * Produk; kolom kode/ukuran di sini hanya menyalin nilainya supaya transaksi
 * lama yang bertulisan bebas masih bisa dihitung sebagai barang terjual.
 */
class TabelStokProduk extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id_stok' => [
                'type'           => 'INT',
                'constraint'     => 10,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'juragan_id' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
            ],
            'produk_id' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
                'null'       => true,
            ],
            'kode' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
            ],
            'ukuran' => [
                'type'       => 'VARCHAR',
                'constraint' => 6,
                'null'       => true,
            ],
            'harga' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
                'default'    => 0,
            ],
            'stok' => [
                'type'       => 'INT',
                'constraint' => 10,
                'default'    => 0,
            ],
            'keterangan' => [
                'type'       => 'VARCHAR',
                'constraint' => 120,
                'null'       => true,
            ],
            'created_at' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'updated_at' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'deleted_at' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id_stok');
        $this->forge->addKey('juragan_id');
        $this->forge->addKey('produk_id');
        $this->forge->addKey(['juragan_id', 'kode', 'ukuran']);
        $this->forge->addKey('deleted_at');
        $this->forge->createTable('stok', true);

        if (! $this->punyaConstraint('stok', 'fk_stok_juragan')) {
            $this->db->query('ALTER TABLE ' . $this->table('stok') . ' ADD CONSTRAINT fk_stok_juragan'
                . ' FOREIGN KEY (juragan_id) REFERENCES ' . $this->table('juragan')
                . ' (id_juragan) ON DELETE RESTRICT ON UPDATE RESTRICT');
        }

        if (! $this->punyaConstraint('stok', 'fk_stok_produk')) {
            $this->db->query('ALTER TABLE ' . $this->table('stok') . ' ADD CONSTRAINT fk_stok_produk'
                . ' FOREIGN KEY (produk_id) REFERENCES ' . $this->table('produk')
                . ' (id_produk) ON DELETE SET NULL ON UPDATE RESTRICT');
        }
    }

    public function down()
    {
        foreach (['fk_stok_produk', 'fk_stok_juragan'] as $nama) {
            if ($this->punyaConstraint('stok', $nama)) {
                $this->db->query('ALTER TABLE ' . $this->table('stok') . ' DROP FOREIGN KEY ' . $nama);
            }
        }

        $this->forge->dropTable('stok', true);
    }

    private function table(string $nama): string
    {
        return $this->db->prefixTable($nama);
    }

    private function punyaConstraint(string $tabel, string $nama): bool
    {
        $q = $this->db->query('SELECT 1 FROM information_schema.table_constraints
                WHERE table_schema = DATABASE() AND table_name = ? AND constraint_name = ? LIMIT 1',
            [$this->table($tabel), $nama]);

        return $q->getNumRows() > 0;
    }
}
