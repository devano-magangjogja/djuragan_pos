<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Dimensi produk & ukuran untuk laporan CRM/KPI.
 *
 * order_dibeli.kode adalah teks bebas yang ditulis CS (1.201 ejaan berbeda,
 * 1.195 bila spasi dan besar-kecil diabaikan) sehingga tidak bisa langsung
 * dipakai sebagai sumbu laporan. order_produk menjadi master yang diisi dari
 * data yang sudah ada; order_dibeli.produk_id hanya ditautkan, nilai kode lama
 * tidak diubah sedikitpun.
 *
 * order_ukuran menampung 14 nilai ukuran yang benar-benar dipakai transaksi,
 * dikelompokkan berdasar pola nilainya (angka = bawahan, teks = atasan,
 * 'custom' = ukuran khusus).
 */
class NormalisasiDimensiProduk extends Migration
{
    /** Ekspresi SQL: huruf kecil, spasi lebih dari satu dirapatkan. */
    private function kunci(string $kolom = 'kode'): string
    {
        return 'REGEXP_REPLACE(TRIM(LOWER(' . $kolom . ')), "[[:space:]]+", " ")';
    }

    public function up()
    {
        $this->buatTabelProduk();
        $this->isiProduk();
        $this->tautkanItem();
        $this->buatTabelUkuran();
    }

    public function down()
    {
        $dibeli = $this->t('dibeli');

        if ($this->punyaConstraint($dibeli, 'fk_dibeli_produk')) {
            $this->db->query('ALTER TABLE `' . $dibeli . '` DROP FOREIGN KEY `fk_dibeli_produk`');
        }

        if ($this->punyaIndex($dibeli, 'idx_dibeli_produk')) {
            $this->db->query('DROP INDEX `idx_dibeli_produk` ON `' . $dibeli . '`');
        }

        if ($this->punyaKolom($dibeli, 'produk_id')) {
            $this->db->query('ALTER TABLE `' . $dibeli . '` DROP COLUMN `produk_id`');
        }

        $this->db->query('DROP TABLE IF EXISTS `' . $this->t('produk') . '`');
        $this->db->query('DROP TABLE IF EXISTS `' . $this->t('ukuran') . '`');
    }

    private function t(string $nama): string
    {
        return $this->db->prefixTable($nama);
    }

    private function buatTabelProduk(): void
    {
        $this->forge->addField([
            'id_produk'   => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
            'kode'        => ['type' => 'VARCHAR', 'constraint' => 20],
            'kunci'       => ['type' => 'VARCHAR', 'constraint' => 20],
            'jumlah_item' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'default' => 0],
            'created_at'  => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'updated_at'  => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
        ]);
        $this->forge->addPrimaryKey('id_produk');
        $this->forge->addUniqueKey('kunci');
        $this->forge->addKey('kode');
        $this->forge->createTable('produk', true);
    }

    /**
     * Satu baris per kunci normalisasi. Ejaan yang disimpan adalah varian yang
     * paling sering dipakai CS supaya tampilannya tetap familier.
     */
    private function isiProduk(): void
    {
        $waktu  = time();
        $kunci  = $this->kunci();
        $dibeli = $this->t('dibeli');

        $this->db->query('INSERT INTO `' . $this->t('produk') . '`
                (kode, kunci, jumlah_item, created_at, updated_at)
                SELECT varian, kunci, jumlah, ' . $waktu . ', ' . $waktu . '
                FROM (
                    SELECT ' . $kunci . ' AS kunci,
                           kode AS varian,
                           COUNT(*) AS jumlah,
                           ROW_NUMBER() OVER (PARTITION BY ' . $kunci . '
                                              ORDER BY COUNT(*) DESC, kode) AS peringkat
                    FROM `' . $dibeli . '`
                    GROUP BY ' . $kunci . ', kode
                ) pilihan
                WHERE peringkat = 1
                ORDER BY jumlah DESC, kunci');
    }

    /**
     * Backfill produk_id. Kolom kode lama tetap utuh, jadi laporan masih bisa
     * memakai ejaan asli bila master produk belum dirapikan.
     */
    private function tautkanItem(): void
    {
        $dibeli = $this->t('dibeli');

        if (! $this->punyaKolom($dibeli, 'produk_id')) {
            $this->db->query('ALTER TABLE `' . $dibeli . '`
                ADD COLUMN `produk_id` INT UNSIGNED NULL AFTER `invoice_id`');
        }

        $this->db->query('UPDATE `' . $dibeli . '` d
                JOIN `' . $this->t('produk') . '` p ON p.kunci = ' . $this->kunci('d.kode') . '
                SET d.produk_id = p.id_produk
                WHERE d.produk_id IS NULL');

        if (! $this->punyaIndex($dibeli, 'idx_dibeli_produk')) {
            $this->db->query('CREATE INDEX `idx_dibeli_produk` ON `' . $dibeli . '` (`produk_id`)');
        }

        if (! $this->punyaConstraint($dibeli, 'fk_dibeli_produk')) {
            $this->db->query('ALTER TABLE `' . $dibeli . '`
                ADD CONSTRAINT `fk_dibeli_produk` FOREIGN KEY (`produk_id`)
                REFERENCES `' . $this->t('produk') . '` (`id_produk`)
                ON DELETE SET NULL ON UPDATE RESTRICT');
        }
    }

    private function buatTabelUkuran(): void
    {
        $this->forge->addField([
            'id_ukuran' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
            'kode'      => ['type' => 'VARCHAR', 'constraint' => 6],
            'label'     => ['type' => 'VARCHAR', 'constraint' => 10],
            'kelompok'  => ['type' => 'VARCHAR', 'constraint' => 10],
            'urutan'    => ['type' => 'TINYINT', 'constraint' => 3, 'unsigned' => true, 'default' => 0],
        ]);
        $this->forge->addPrimaryKey('id_ukuran');
        $this->forge->addUniqueKey('kode');
        $this->forge->addKey('kelompok');
        $this->forge->createTable('ukuran', true);

        $this->db->query('INSERT INTO `' . $this->t('ukuran') . '` (kode, label, kelompok, urutan)
                SELECT ukuran, UPPER(ukuran),
                       CASE
                           WHEN ukuran = "custom"        THEN "custom"
                           WHEN ukuran REGEXP "^[0-9]+$"  THEN "bawahan"
                           ELSE "atasan"
                       END,
                       CASE
                           WHEN ukuran = "custom"                              THEN 900
                           WHEN ukuran REGEXP "^[0-9]+$"                        THEN 100 + CAST(ukuran AS UNSIGNED)
                           ELSE FIND_IN_SET(ukuran, "s,m,l,xl,xxl,xxxl")
                       END
                FROM `' . $this->t('dibeli') . '`
                WHERE ukuran IS NOT NULL AND CHAR_LENGTH(ukuran) > 0
                GROUP BY ukuran');
    }

    private function punyaKolom(string $tabel, string $kolom): bool
    {
        $q = $this->db->query('SELECT 1 FROM information_schema.columns
                WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?', [$tabel, $kolom]);

        return $q->getNumRows() > 0;
    }

    private function punyaIndex(string $tabel, string $nama): bool
    {
        $q = $this->db->query('SELECT 1 FROM information_schema.statistics
                WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ? LIMIT 1', [$tabel, $nama]);

        return $q->getNumRows() > 0;
    }

    private function punyaConstraint(string $tabel, string $nama): bool
    {
        $q = $this->db->query('SELECT 1 FROM information_schema.table_constraints
                WHERE table_schema = DATABASE() AND table_name = ? AND constraint_name = ? LIMIT 1', [$tabel, $nama]);

        return $q->getNumRows() > 0;
    }
}
