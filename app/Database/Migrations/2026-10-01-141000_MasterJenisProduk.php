<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Jenis produk pakaian (Jas, Celana, Jaket, ...).
 *
 * Template ukuran per produk butuh pengelompokan ini, sedangkan order_produk
 * baru punya kode/kunci dan tidak tahu produk itu jenis apa. Kolom id_jenis
 * dibuat NULL-able: 1.198 master lama tetap tanpa jenis sampai ada yang
 * mengelompokkannya lewat halaman Produk.
 *
 * Sekaligus melebarkan kode/kunci yang sempit (varchar 20) — nama seperti
 * "Jas Custom Wollen Import" terpotong diam-diam sejak jamin_produk() menulisnya.
 */
class MasterJenisProduk extends Migration
{
    private const JENIS = ['Jas', 'Blazer', 'Jaket', 'Celana', 'Kemeja', 'Seragam'];

    public function up()
    {
        $this->forge->addField([
            'id_jenis' => [
                'type'           => 'INT',
                'constraint'     => 10,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'nama' => [
                'type'       => 'VARCHAR',
                'constraint' => 40,
            ],
            'keterangan' => [
                'type'       => 'VARCHAR',
                'constraint' => 120,
                'null'       => true,
            ],
            'urutan' => [
                'type'       => 'TINYINT',
                'constraint' => 3,
                'unsigned'   => true,
                'default'    => 0,
            ],
            'created_at' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'updated_at' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id_jenis');
        $this->forge->addUniqueKey('nama');
        $this->forge->createTable('jenis_produk', true);

        $waktu = time();

        foreach (self::JENIS as $i => $nama) {
            $this->db->query('INSERT IGNORE INTO ' . $this->t('jenis_produk')
                . ' (nama, urutan, created_at, updated_at) VALUES (?, ?, ?, ?)',
                [$nama, ($i + 1) * 10, $waktu, $waktu]);
        }

        if (! $this->punyaKolom('produk', 'id_jenis')) {
            $this->db->query('ALTER TABLE ' . $this->t('produk')
                . ' ADD COLUMN id_jenis INT(10) UNSIGNED NULL AFTER kunci');
            $this->db->query('ALTER TABLE ' . $this->t('produk') . ' ADD INDEX idx_produk_jenis (id_jenis)');
            $this->db->query('ALTER TABLE ' . $this->t('produk') . ' ADD CONSTRAINT fk_produk_jenis'
                . ' FOREIGN KEY (id_jenis) REFERENCES ' . $this->t('jenis_produk')
                . ' (id_jenis) ON DELETE SET NULL ON UPDATE RESTRICT');
        }

        foreach (['kode', 'kunci'] as $kolom) {
            $lebar = $this->lebarKolom('produk', $kolom);

            if ($lebar !== null && $lebar < 60) {
                $this->db->query('ALTER TABLE ' . $this->t('produk') . " MODIFY COLUMN {$kolom} VARCHAR(60) NOT NULL");
            }
        }
    }

    public function down()
    {
        if ($this->punyaConstraint('produk', 'fk_produk_jenis')) {
            $this->db->query('ALTER TABLE ' . $this->t('produk') . ' DROP FOREIGN KEY fk_produk_jenis');
        }

        if ($this->punyaKolom('produk', 'id_jenis')) {
            $this->db->query('ALTER TABLE ' . $this->t('produk') . ' DROP COLUMN id_jenis');
        }

        $this->forge->dropTable('jenis_produk', true);
    }

    private function t(string $nama): string
    {
        return $this->db->prefixTable($nama);
    }

    private function punyaKolom(string $tabel, string $kolom): bool
    {
        return $this->db->query('SELECT 1 FROM information_schema.columns
                WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ? LIMIT 1',
            [$this->t($tabel), $kolom])->getNumRows() > 0;
    }

    private function punyaConstraint(string $tabel, string $nama): bool
    {
        return $this->db->query('SELECT 1 FROM information_schema.table_constraints
                WHERE table_schema = DATABASE() AND table_name = ? AND constraint_name = ? LIMIT 1',
            [$this->t($tabel), $nama])->getNumRows() > 0;
    }

    private function lebarKolom(string $tabel, string $kolom): ?int
    {
        $baris = $this->db->query('SELECT character_maximum_length n FROM information_schema.columns
                WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ? LIMIT 1',
            [$this->t($tabel), $kolom])->getRow();

        return $baris === null ? null : (int) $baris->n;
    }
}
