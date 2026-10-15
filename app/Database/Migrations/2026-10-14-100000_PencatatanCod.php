<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Pencatatan COD: catatan kunjungan/transaksi customer yang tidak berhenti
 * sebagai orderan (ambil sewa, tanya-tanya, beli cover jas, dan sejenisnya).
 *
 * Tabel transaksi yang sudah ada tidak bisa dipakai: order_invoice wajib punya
 * nota, pelanggan terdaftar, dan rincian barang, sedangkan catatan COD justru
 * dibuat untuk customer yang belum tentu ketiganya ada. Tabel crm_aktivitas
 * juga tidak cukup karena cuma bisa ditulis (tidak ada jalur sunting/hapus) dan
 * wajib menunjuk pelanggan.
 *
 * Pilihan untuk kolom berkategori (keperluan, status, nama CS, sumber
 * informasi) disimpan di satu tabel master supaya isian baru yang diketik
 * pengguna ikut tersimpan dan muncul lagi di kesempatan berikutnya, sama seperti
 * master produk dan asal_order yang sudah lebih dulu ada.
 */
class PencatatanCod extends Migration
{
    /** Kategori pilihan yang boleh ada di tabel master. */
    private const JENIS = ['keperluan', 'status', 'cs', 'sumber'];

    public function up()
    {
        $this->pilihan();
        $this->catatan();
    }

    public function down()
    {
        // tabel catatan lebih dulu: constraint-nya menahan tabel master
        $this->forge->dropTable('pencatatan_cod', true);
        $this->forge->dropTable('pencatatan_pilihan', true);
    }

    /** Tabel master pilihan + isi bakunya. */
    private function pilihan()
    {
        $this->forge->addField([
            'id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
            // kategori pilihan: keperluan / status / cs / sumber
            'jenis' => [
                'type'       => 'ENUM',
                'constraint' => self::JENIS,
            ],
            // apa yang dilihat pengguna di dropdown
            'label' => [
                'type'       => 'VARCHAR',
                'constraint' => 80,
            ],
            // label yang dirapatkan dan dikecilkan; kunci ini yang dicari sebelum
            // membuat baris baru supaya 'Sakina' dan 'sakina' tidak jadi dua opsi
            'kunci' => [
                'type'       => 'VARCHAR',
                'constraint' => 80,
            ],
            'created_at' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('jenis');
        $this->forge->addUniqueKey(['jenis', 'kunci']);
        $this->forge->createTable('pencatatan_pilihan', true);

        $waktu = time();
        foreach ($this->baku() as $jenis => $daftar) {
            foreach ($daftar as $label) {
                $kunci = $this->kunci((string) $label);

                if ($kunci === '') {
                    continue;
                }

                // INSERT IGNORE karena kunci (jenis, kunci) unik dan migrasi ini
                // harus bisa dijalankan ulang tanpa galat
                $this->db->query(
                    'INSERT IGNORE INTO `' . $this->t('pencatatan_pilihan') . '` (jenis, label, kunci, created_at) VALUES (?, ?, ?, ?)',
                    [$jenis, $label, $kunci, $waktu]
                );
            }
        }
    }

    /** Tabel catatan itu sendiri. */
    private function catatan()
    {
        $this->forge->addField([
            'id_cod' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
            'tanggal' => ['type' => 'DATE'],
            // jam datang; sebagian catatan datang dari rekap harian yang tidak
            // ingat jamnya, jadi kolom ini boleh kosong
            'jam' => ['type' => 'TIME', 'null' => true],
            'nama_customer' => ['type' => 'VARCHAR', 'constraint' => 100],
            // disimpan sebagai teks: nomor telepon dimulai dengan 0 dan 812,
            // bukan angka yang boleh dihitung
            'no_hp' => ['type' => 'VARCHAR', 'constraint' => 13],
            'keperluan_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'status_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'catatan' => ['type' => 'TEXT'],
            'cs_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'sumber_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            // siapa yang mencatat, bukan siapa yang dilayani
            'user_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'created_at' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'updated_at' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'deleted_at' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id_cod');
        // daftar selalu dibaca per tanggal terbaru, dan kartu yang sudah dihapus
        // harus tersaring tanpa memindai seluruh tabel
        $this->forge->addKey(['deleted_at', 'tanggal']);
        $this->forge->addKey('keperluan_id');
        $this->forge->addKey('status_id');
        $this->forge->addKey('user_id');
        $this->forge->createTable('pencatatan_cod', true);

        $rujukan = [
            ['fk_cod_keperluan', 'keperluan_id', 'RESTRICT'],
            ['fk_cod_status', 'status_id', 'RESTRICT'],
            ['fk_cod_cs', 'cs_id', 'RESTRICT'],
            // sumber informasi bersifat opsional: pilihannya boleh dihapus tanpa
            // membuat catatannya ikut terasa rusak
            ['fk_cod_sumber', 'sumber_id', 'SET NULL'],
        ];

        foreach ($rujukan as [$nama, $kolom, $saatHapus]) {
            $this->pasangConstraint($nama, $kolom, 'pencatatan_pilihan', 'id', $saatHapus);
        }

        // akun pencatat dihapus permanen: catatannya tetap terbaca, hanya nama
        // pengisinya yang jadi tidak diketahui
        $this->pasangConstraint('fk_cod_user', 'user_id', 'user', 'id', 'SET NULL');
    }

    private function pasangConstraint(string $nama, string $kolom, string $tabelAcuan, string $kolomAcuan, string $saatHapus): void
    {
        if ($this->punyaConstraint('pencatatan_cod', $nama)) {
            return;
        }

        // Constraint tidak bisa dipasang selama masih ada baris yang menunjuk
        // kunci yang tidak ada di tabel acuan
        $yatim = $this->db->query('SELECT COUNT(*) AS jml FROM ' . $this->t('pencatatan_cod') . ' c'
            . ' LEFT JOIN ' . $this->t($tabelAcuan) . ' r ON r.' . $kolomAcuan . ' = c.' . $kolom
            . ' WHERE c.' . $kolom . ' IS NOT NULL AND r.' . $kolomAcuan . ' IS NULL')->getRow();

        if ((int) ($yatim->jml ?? 0) > 0) {
            return;
        }

        $this->db->query('ALTER TABLE ' . $this->t('pencatatan_cod') . " ADD CONSTRAINT {$nama}"
            . " FOREIGN KEY ({$kolom}) REFERENCES " . $this->t($tabelAcuan) . " ({$kolomAcuan})"
            . " ON DELETE {$saatHapus} ON UPDATE RESTRICT");
    }

    private function punyaConstraint(string $tabel, string $nama): bool
    {
        return $this->db->query('SELECT 1 FROM information_schema.table_constraints
                WHERE table_schema = DATABASE() AND table_name = ? AND constraint_name = ? LIMIT 1',
            [$this->t($tabel), $nama])->getNumRows() > 0;
    }

    private function t(string $nama): string
    {
        return $this->db->prefixTable($nama);
    }

    /** Kunci pencarian: huruf kecil, spasi berlebih dirapatkan. */
    private function kunci(string $label): string
    {
        return trim((string) preg_replace('/\s+/', ' ', mb_strtolower(trim($label))));
    }

    /**
     * Isi baku dropdown. Daftar keperluan, status, dan nama CS datang dari
     * pengguna; sumber informasi sengaja tidak dibakukan karena tidak ada
     * daftarnya sampai ada yang mengetiknya.
     *
     * @return array<string, list<string>>
     */
    private function baku(): array
    {
        return [
            'keperluan' => [
                'Sewa', 'Ambil Sewa', 'Keep Sewa', 'Kembalikan Sewa', 'Tambah Sewa',
                'Beli', 'Tanya-tanya', 'Custom', 'Ambil Pesanan', 'Beli Cover Jas',
                'Revisi Custom', 'Lainnya',
            ],
            'status'    => ['Sukses', 'Cancel', 'Revisi'],
            'cs'        => [
                'Sakina', 'Salsa', 'Bu Pipit dan Salsa', 'Bu Pipit dan Sakina',
                'Sakina dan Salsa', 'Pak Danny',
            ],
        ];
    }
}
