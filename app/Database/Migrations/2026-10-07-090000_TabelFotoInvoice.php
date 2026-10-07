<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Foto orderan: satu baris untuk satu berkas, nempel ke invoice (bukan ke item).
 *
 * Penjahit sering dikirimi foto contoh/model lewat WA, dan foto itu satu-satunya
 * acuan bentuk. Fotonya satu orderan bisa banyak, jadi tidak muat kalau hanya
 * dititipkan di kolom `rincian`.
 *
 * Order lama tidak pernah punya baris di tabel ini dan itu normal: pembacanya
 * menampilkan panel foto hanya kalau isinya tidak kosong.
 *
 * Berkas disimpan di writable/uploads (di luar public) supaya hanya bisa dibuka
 * lewat route terpagar. Yang dicatat di sini jalur relatifnya saja, jadi folder
 * boleh dipindah tanpa menulisi ulang barisnya.
 */
class TabelFotoInvoice extends Migration
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
            'invoice_id' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
            ],
            // relatif terhadap writable/uploads/, contoh: invoice/12/a1b2....jpg
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
            // dipakai kartu invoice untuk menahan kotak gambar sebelum fotonya
            // ikut terunduh, jadi daftar transaksi tidak meloncat-loncat
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
        $this->forge->addKey('invoice_id');
        $this->forge->addKey('user_id');
        $this->forge->createTable('invoice_foto', true);

        $rujukan = [
            // nota dihapus (soft delete lalu truly delete) = fotonya ikut mati,
            // tidak boleh ada berkas yatim yang masih bisa dibuka
            ['fk_foto_invoice', 'invoice_id', 'invoice', 'id_invoice', 'CASCADE'],
            // akun pengunggah dihapus permanen: fotanya tetap tinggal, hanya nama
            // pengunggahnya yang jadi tidak diketahui
            ['fk_foto_user', 'user_id', 'user', 'id', 'SET NULL'],
        ];

        foreach ($rujukan as [$nama, $kolom, $rujukanTabel, $kolomAcuan, $saatHapus]) {
            if (! $this->punyaConstraint('invoice_foto', $nama)) {
                $this->db->query('ALTER TABLE ' . $this->t('invoice_foto') . " ADD CONSTRAINT {$nama}"
                    . " FOREIGN KEY ({$kolom}) REFERENCES " . $this->t($rujukanTabel) . " ({$kolomAcuan})"
                    . " ON DELETE {$saatHapus} ON UPDATE RESTRICT");
            }
        }
    }

    public function down()
    {
        foreach (['fk_foto_user', 'fk_foto_invoice'] as $nama) {
            if ($this->punyaConstraint('invoice_foto', $nama)) {
                $this->db->query('ALTER TABLE ' . $this->t('invoice_foto') . ' DROP FOREIGN KEY ' . $nama);
            }
        }

        $this->forge->dropTable('invoice_foto', true);
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
