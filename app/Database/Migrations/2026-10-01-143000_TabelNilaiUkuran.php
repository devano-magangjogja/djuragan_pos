<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Nilai ukuran tersimpan: satu baris untuk tiap komponen, per orang atau per item.
 *
 * Order satuan: id_beli terisi, anggota_id NULL.
 * Order rombongan: anggota_id terisi, id_beli boleh ikut terisi kalau tiap orang
 * memakai produk berbeda.
 *
 * Kolom komponen_id dibuat RESTRICT, bukan SET NULL: menghapus definisi ukuran
 * tidak boleh ikut menghapus hasil ukur pelanggan yang sudah dicatat. Untuk
 * berhenti memakainya, tandai ukuran_komponen.aktif = 0.
 *
 * nilai boleh NULL supaya baris "sudah ditanyakan, belum diukur" bisa dibedakan
 * dari baris yang angkanya sudah ada.
 */
class TabelNilaiUkuran extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id_nilai' => [
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
            'id_beli' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
                'null'       => true,
            ],
            'anggota_id' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
                'null'       => true,
            ],
            'komponen_id' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
            ],
            'nilai' => [
                'type'       => 'DECIMAL',
                'constraint' => '7,2',
                'null'       => true,
            ],
            'satuan' => [
                'type'       => 'VARCHAR',
                'constraint' => 10,
                'null'       => true,
            ],
            'catatan' => [
                'type'       => 'VARCHAR',
                'constraint' => 120,
                'null'       => true,
            ],
            'created_at' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'updated_at' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id_nilai');
        $this->forge->addKey('invoice_id');
        $this->forge->addKey('anggota_id');
        $this->forge->addKey('id_beli');
        $this->forge->addKey('komponen_id');
        $this->forge->createTable('nilai_ukuran', true);

        // Satu komponen hanya boleh punya satu baris untuk satu sasaran. Keunikannya
        // tidak bisa lewat indeks unik: id_beli/anggota_id boleh NULL dan MySQL
        // menganggap NULL selalu berbeda. Sama seperti StokModel::kembar(),
        // dicegah di lapisan aplikasi; indeks ini yang bikin pencariannya murah.
        if (! $this->punyaIndex('nilai_ukuran', 'idx_nilai_sasaran')) {
            $this->db->query('ALTER TABLE ' . $this->t('nilai_ukuran')
                . ' ADD INDEX idx_nilai_sasaran (invoice_id, id_beli, anggota_id, komponen_id)');
        }

        $rujukan = [
            ['fk_nilai_invoice', 'invoice_id', 'invoice', 'id_invoice', 'CASCADE'],
            ['fk_nilai_beli', 'id_beli', 'dibeli', 'id_beli', 'CASCADE'],
            ['fk_nilai_anggota', 'anggota_id', 'anggota', 'id_anggota', 'CASCADE'],
            ['fk_nilai_komponen', 'komponen_id', 'ukuran_komponen', 'id_komponen', 'RESTRICT'],
        ];

        foreach ($rujukan as [$nama, $kolom, $rujukanTabel, $kolomAcuan, $saatHapus]) {
            if (! $this->punyaConstraint('nilai_ukuran', $nama)) {
                $this->db->query('ALTER TABLE ' . $this->t('nilai_ukuran') . " ADD CONSTRAINT {$nama}"
                    . " FOREIGN KEY ({$kolom}) REFERENCES " . $this->t($rujukanTabel) . " ({$kolomAcuan})"
                    . " ON DELETE {$saatHapus} ON UPDATE RESTRICT");
            }
        }
    }

    public function down()
    {
        foreach (['fk_nilai_komponen', 'fk_nilai_anggota', 'fk_nilai_beli', 'fk_nilai_invoice'] as $nama) {
            if ($this->punyaConstraint('nilai_ukuran', $nama)) {
                $this->db->query('ALTER TABLE ' . $this->t('nilai_ukuran') . ' DROP FOREIGN KEY ' . $nama);
            }
        }

        $this->forge->dropTable('nilai_ukuran', true);
    }

    private function t(string $nama): string
    {
        return $this->db->prefixTable($nama);
    }

    private function punyaConstraint(string $tabel, string $nama): bool
    {
        return $this->db->query('SELECT 1 FROM information_schema.table_constraints
                WHERE table_schema = DATABASE() AND table_name = ? AND constraint_name = ? LIMIT 1',
            [$this->t($tabel), $nama])->getNumRows() > 0;
    }

    private function punyaIndex(string $tabel, string $nama): bool
    {
        return $this->db->query('SELECT 1 FROM information_schema.statistics
                WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ? LIMIT 1',
            [$this->t($tabel), $nama])->getNumRows() > 0;
    }
}
