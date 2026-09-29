<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Kontak pelanggan & kode pos siap CRM.
 *
 * order_pelanggan.hp menyimpan array JSON di kolom varchar(50), jadi daftar
 * nomor tidak bisa di-query (17.498 pelanggan punya 18.486 nomor, 994
 * pelanggan punya lebih dari satu). Tabel order_pelanggan_kontak memecahnya
 * jadi satu baris per nomor; kolom hp lama dibiarkan utuh sebagai sumber
 * aslinya.
 *
 * kodepos disimpan sebagai int sehingga nol di depan hilang dan nilai typo
 * ikut terbawa (848 baris bernilai 0, 11 baris 4 digit, 3 baris 6 digit).
 * Kolom diubah ke varchar tanpa mengubah digit aslinya; salinan mentah
 * disimpan di kodepos_asli, dan nilai yang terbukti hanya kehilangan nol di
 * depan ditambal dari order_cities.postal_code.
 */
class NormalisasiKontakPelanggan extends Migration
{
    public function up()
    {
        $this->buatTabelKontak();
        $this->isiKontak();
        $this->rapikanKodepos();
    }

    public function down()
    {
        $this->db->query('DROP TABLE IF EXISTS `' . $this->t('pelanggan_kontak') . '`');

        $pelanggan = $this->t('pelanggan');

        if ($this->punyaKolom($pelanggan, 'kodepos')) {
            $this->db->query('ALTER TABLE `' . $pelanggan . '` MODIFY `kodepos` INT UNSIGNED NULL');
        }

        if ($this->punyaKolom($pelanggan, 'kodepos_asli')) {
            $this->db->query('ALTER TABLE `' . $pelanggan . '` DROP COLUMN `kodepos_asli`');
        }
    }

    private function t(string $nama): string
    {
        return $this->db->prefixTable($nama);
    }

    private function buatTabelKontak(): void
    {
        $this->forge->addField([
            'id_kontak'    => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
            'pelanggan_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'nomor'        => ['type' => 'VARCHAR', 'constraint' => 20],
            'urutan'       => ['type' => 'TINYINT', 'constraint' => 3, 'unsigned' => true, 'default' => 1],
            'created_at'   => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
        ]);
        $this->forge->addPrimaryKey('id_kontak');
        $this->forge->addKey('pelanggan_id');
        $this->forge->createTable('pelanggan_kontak', true);

        // Digits-only dipakai untuk pencocokan antar akun; sumber aslinya tetap
        // ditulis apa adanya supaya nomor yang salah ketik tidak "diperbaiki".
        $this->db->query('ALTER TABLE `' . $this->t('pelanggan_kontak') . '`
            ADD COLUMN `nomor_pure` VARCHAR(20)
                AS (REGEXP_REPLACE(`nomor`, "[^0-9]", "")) STORED AFTER `nomor`');
        $this->db->query('CREATE UNIQUE INDEX `uk_kontak_pelanggan_nomor`
            ON `' . $this->t('pelanggan_kontak') . '` (`pelanggan_id`, `nomor`)');
        $this->db->query('CREATE INDEX `idx_kontak_nomor`
            ON `' . $this->t('pelanggan_kontak') . '` (`nomor_pure`)');
        $this->db->query('ALTER TABLE `' . $this->t('pelanggan_kontak') . '`
            ADD CONSTRAINT `fk_kontak_pelanggan` FOREIGN KEY (`pelanggan_id`)
            REFERENCES `' . $this->t('pelanggan') . '` (`id_pelanggan`)
            ON DELETE CASCADE ON UPDATE RESTRICT');
    }

    /**
     * LIMIT raksasa memaksa subquery dimaterialisasi; tanpa itu MySQL
     * menjalankan JSON_TABLE pada baris hp yang rusak (1 baris terpotong di
     * karakter 50) dan query gagal sebelum filter JSON_VALID terpakai.
     */
    private function isiKontak(): void
    {
        $this->db->query('INSERT IGNORE INTO `' . $this->t('pelanggan_kontak') . '`
                (pelanggan_id, nomor, urutan, created_at)
                SELECT s.id_pelanggan, TRIM(jt.nomor), jt.urutan, s.created_at
                FROM (
                    SELECT id_pelanggan, hp, created_at
                    FROM `' . $this->t('pelanggan') . '`
                    WHERE JSON_VALID(hp) AND JSON_TYPE(hp) = "ARRAY"
                    LIMIT 18446744073709551615
                ) s
                JOIN JSON_TABLE(s.hp, "$[*]"
                    COLUMNS(urutan FOR ORDINALITY, nomor VARCHAR(20) PATH "$")) jt
                WHERE TRIM(jt.nomor) <> ""
                ORDER BY s.id_pelanggan, jt.urutan');
    }

    private function rapikanKodepos(): void
    {
        $pelanggan = $this->t('pelanggan');

        if (! $this->punyaKolom($pelanggan, 'kodepos_asli')) {
            $this->db->query('ALTER TABLE `' . $pelanggan . '`
                ADD COLUMN `kodepos_asli` VARCHAR(8) NULL AFTER `kodepos`');
            $this->db->query('UPDATE `' . $pelanggan . '`
                SET kodepos_asli = CAST(kodepos AS CHAR) WHERE kodepos IS NOT NULL');
        }

        $this->db->query('ALTER TABLE `' . $pelanggan . '` MODIFY `kodepos` VARCHAR(8) NULL');

        // Tambal kehilangan nol depan hanya bila kota confirms nilai tersebut.
        $this->db->query('UPDATE `' . $pelanggan . '` p
            JOIN `' . $this->t('cities') . '` c ON c.city_id = p.kabupaten
            SET p.kodepos = c.postal_code
            WHERE p.kodepos IS NOT NULL
              AND CHAR_LENGTH(p.kodepos) < 5
              AND c.postal_code REGEXP "^[1-9][0-9]{4}$"
              AND LPAD(p.kodepos, 5, "0") = c.postal_code');
    }

    private function punyaKolom(string $tabel, string $kolom): bool
    {
        $q = $this->db->query('SELECT 1 FROM information_schema.columns
                WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?', [$tabel, $kolom]);

        return $q->getNumRows() > 0;
    }
}
