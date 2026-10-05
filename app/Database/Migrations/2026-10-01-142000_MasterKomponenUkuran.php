<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Komponen ukuran per jenis produk: "Lingkar Dada = 100 cm" jadi satu baris,
 * bukan teks bebas di dalam JSON.
 *
 * jenis_id NULL berarti komponen berlaku untuk semua produk. Tabel lama
 * order_ukuran (S/M/L/XL dan angka 28-34) sengaja tidak dipakai ulang: kode
 * varchar(6) dan label varchar(10) tidak muat "Lingkar pinggang", dan isinya
 * dibaca dropdown stok lewat StokModel::ukuran() — menambah baris di situ akan
 * muncul sebagai pilihan ukuran jadi.
 *
 * Baris jenis_id NULL tidak boleh dobel; keunikannya dijaga di aplikasi karena
 * indeks unik MySQL mengabaikan NULL.
 */
class MasterKomponenUkuran extends Migration
{
    /**
     * Template awal: isi kolom keterangan order_dibeli.rincian ukuran_detail
     * selama ini persis daftar seperti ini, hanya tertulis tangan.
     */
    private const TEMPLATE = [
        'Jas'     => ['Lingkar Dada', 'Lingkar Pinggang', 'Lebar Bahu', 'Panjang Badan', 'Panjang Lengan', 'Lingkar Lengan', 'Lingkar Leher'],
        'Blazer'  => ['Lingkar Dada', 'Lingkar Pinggang', 'Lebar Bahu', 'Panjang Badan', 'Panjang Lengan', 'Lingkar Lengan'],
        'Jaket'   => ['Lingkar Dada', 'Lebar Bahu', 'Panjang Badan', 'Panjang Lengan', 'Lingkar Lengan'],
        'Celana'  => ['Lingkar Pinggang', 'Lingkar Pinggul', 'Panjang Celana', 'Lingkar Paha', 'Lingkar Kaki'],
        'Kemeja'  => ['Lingkar Dada', 'Lebar Bahu', 'Panjang Badan', 'Panjang Lengan', 'Lingkar Leher'],
        'Seragam' => ['Lingkar Dada', 'Lingkar Pinggang', 'Lebar Bahu', 'Panjang Badan', 'Panjang Lengan'],
    ];

    public function up()
    {
        $this->forge->addField([
            'id_komponen' => [
                'type'           => 'INT',
                'constraint'     => 10,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'jenis_id' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
                'null'       => true,
            ],
            'nama' => [
                'type'       => 'VARCHAR',
                'constraint' => 40,
            ],
            'satuan' => [
                'type'       => 'VARCHAR',
                'constraint' => 10,
                'default'    => 'cm',
            ],
            'urutan' => [
                'type'       => 'SMALLINT',
                'constraint' => 5,
                'unsigned'   => true,
                'default'    => 0,
            ],
            'wajib' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 0,
            ],
            'aktif' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 1,
            ],
            'created_at' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'updated_at' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id_komponen');
        $this->forge->addKey(['jenis_id', 'urutan']);
        $this->forge->createTable('ukuran_komponen', true);

        if (! $this->punyaConstraint('ukuran_komponen', 'fk_komponen_jenis')) {
            $this->db->query('ALTER TABLE ' . $this->t('ukuran_komponen') . ' ADD CONSTRAINT fk_komponen_jenis'
                . ' FOREIGN KEY (jenis_id) REFERENCES ' . $this->t('jenis_produk')
                . ' (id_jenis) ON DELETE RESTRICT ON UPDATE RESTRICT');
        }

        $waktu = time();

        foreach (self::TEMPLATE as $namaJenis => $daftar) {
            $baris = $this->db->query('SELECT id_jenis FROM ' . $this->t('jenis_produk') . ' WHERE nama = ? LIMIT 1', [$namaJenis])->getFirstRow();

            if ($baris === null) {
                continue;
            }

            foreach ($daftar as $i => $nama) {
                $this->db->query('INSERT INTO ' . $this->t('ukuran_komponen')
                    . ' (jenis_id, nama, satuan, urutan, wajib, aktif, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                    [(int) $baris->id_jenis, $nama, 'cm', ($i + 1) * 10, 1, 1, $waktu, $waktu]);
            }
        }
    }

    public function down()
    {
        if ($this->punyaConstraint('ukuran_komponen', 'fk_komponen_jenis')) {
            $this->db->query('ALTER TABLE ' . $this->t('ukuran_komponen') . ' DROP FOREIGN KEY fk_komponen_jenis');
        }

        $this->forge->dropTable('ukuran_komponen', true);
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
}
