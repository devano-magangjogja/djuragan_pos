<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Foto produk: satu baris untuk satu berkas, nempel ke satu varian stok.
 *
 * Katalog barang selama ini cuma nama dan angka, padahal penjahit dan pelanggan
 * sama-sama butuh lihat bentuknya. Fotonya disimpan per varian karena baris
 * stok-lah yang punya identitas di tabel ini; kartu katalog menampilkan gabungan
 * foto semua variannya, jadi satu foto cukup diunggah sekali saja.
 *
 * Barang lama tidak pernah punya baris di tabel ini dan itu normal: kartunya
 * menampilkan inisial nama kalau fotonya memang belum ada.
 *
 * Berkasnya ditulis di writable/uploads/produk (di luar public) supaya hanya
 * bisa dibuka lewat route terpagar, sama seperti foto orderan. Yang dicatat di
 * sini jalur relatifnya saja.
 */
class TabelFotoProduk extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id_foto' => [
                'type'           => 'INT',
                'constraint'     => 10,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            // varian tempat foto ini diunggah; kartunya menampilkan gabungan
            // semua varian yang nempel padanya
            'stok_id' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
            ],
            // relatif terhadap writable/uploads/, contoh: produk/12/a1b2....jpg
            'berkas' => [
                'type'       => 'VARCHAR',
                'constraint' => 190,
            ],
            // nama dari ponsel pengunggah, murni untuk tampilan
            'nama_asli' => [
                'type'       => 'VARCHAR',
                'constraint' => 150,
                'null'       => true,
            ],
            'tipe' => [
                'type'       => 'VARCHAR',
                'constraint' => 10,
            ],
            'byte' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
            ],
            // dipakai kartu katalog untuk menahan kotak gambar sebelum fotonya
            // ikut terunduh, jadi daftar barang tidak meloncat-loncat
            'lebar' => [
                'type'       => 'SMALLINT',
                'constraint' => 5,
                'unsigned'   => true,
                'null'       => true,
            ],
            'tinggi' => [
                'type'       => 'SMALLINT',
                'constraint' => 5,
                'unsigned'   => true,
                'null'       => true,
            ],
            'user_id' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
                'null'       => true,
            ],
            'created_at' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id_foto');
        $this->forge->addKey('stok_id');
        $this->forge->addKey('user_id');
        $this->forge->createTable('produk_foto', true);

        $rujukan = [
            // varian dihapus permanen = fotonya ikut mati, tidak boleh ada
            // berkas yatim yang masih bisa dibuka
            ['fk_pfoto_stok', 'stok_id', 'stok', 'id_stok', 'CASCADE'],
            // akun pengunggah dihapus permanen: fotanya tetap tinggal, hanya
            // nama pengunggahnya yang jadi tidak diketahui
            ['fk_pfoto_user', 'user_id', 'user', 'id', 'SET NULL'],
        ];

        foreach ($rujukan as [$nama, $kolom, $rujukanTabel, $kolomAcuan, $saatHapus]) {
            if ($this->punyaConstraint('produk_foto', $nama)) {
                continue;
            }

            // Constraint tidak bisa dipasang selama masih ada baris yang merujuk
            // varian/akun yang sudah tidak ada. Baru dipakai pada database bersih,
            // tapi ditulis tetap seperti migrasi foto orderan supaya bisa dijalankan
            // ulang tanpa galat.
            if ($this->yatim($kolom, $rujukanTabel, $kolomAcuan) > 0) {
                continue;
            }

            $this->db->query('ALTER TABLE ' . $this->t('produk_foto') . " ADD CONSTRAINT {$nama}"
                . " FOREIGN KEY ({$kolom}) REFERENCES " . $this->t($rujukanTabel) . " ({$kolomAcuan})"
                . " ON DELETE {$saatHapus} ON UPDATE RESTRICT");
        }
    }

    public function down()
    {
        foreach (['fk_pfoto_user', 'fk_pfoto_stok'] as $nama) {
            if ($this->punyaConstraint('produk_foto', $nama)) {
                $this->db->query('ALTER TABLE ' . $this->t('produk_foto') . ' DROP FOREIGN KEY ' . $nama);
            }
        }

        $this->forge->dropTable('produk_foto', true);
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

    /** Jumlah baris foto yang merujuk kunci yang sudah tidak ada di tabel acuan. */
    private function yatim(string $kolom, string $rujukanTabel, string $kolomAcuan): int
    {
        $baris = $this->db->query('SELECT COUNT(*) AS jml FROM ' . $this->t('produk_foto') . ' f'
            . ' LEFT JOIN ' . $this->t($rujukanTabel) . ' r ON r.' . $kolomAcuan . ' = f.' . $kolom
            . ' WHERE f.' . $kolom . ' IS NOT NULL AND r.' . $kolomAcuan . ' IS NULL')->getRow();

        return (int) ($baris->jml ?? 0);
    }
}
