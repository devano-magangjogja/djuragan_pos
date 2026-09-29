<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Merapikan dua hal yang muncul sesudah master produk & kontak dipakai tulis.
 *
 * order_produk.jumlah_item hasil backfill (jumlah item lama per produk) tidak
 * pernah diperbarui saat orderan baru masuk, jadi angkanya menyesatkan;
 * laporan sebaiknya menghitung sendiri dari order_dibeli.
 *
 * order_pelanggan.hp varchar(50) memotong array JSON nomor telepon kalau CS
 * memasukkan lebih dari tiga nomor (sudah terbukti: 1 baris rusak di tengah
 * string). Dilebarkan ke varchar(255) supaya order_pelanggan_kontak selalu
 * bisa mengikuti isi aslinya.
 */
class RapikanMasterTulis extends Migration
{
    public function up()
    {
        $produk = $this->t('produk');

        if ($this->punyaKolom($produk, 'jumlah_item')) {
            $this->db->query('ALTER TABLE `' . $produk . '` DROP COLUMN `jumlah_item`');
        }

        $this->lebarkanHp('255');
    }

    public function down()
    {
        $produk = $this->t('produk');

        if (! $this->punyaKolom($produk, 'jumlah_item')) {
            $this->db->query('ALTER TABLE `' . $produk . '`
                ADD COLUMN `jumlah_item` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `kunci`');
            $this->db->query('UPDATE `' . $produk . '` p
                SET p.jumlah_item = (SELECT COUNT(*) FROM `' . $this->t('dibeli') . '` d
                    WHERE d.produk_id = p.id_produk)');
        }

        $this->lebarkanHp('50');
    }

    private function lebarkanHp(string $batas): void
    {
        $pelanggan = $this->t('pelanggan');

        $q = $this->db->query('SELECT character_maximum_length AS batas
            FROM information_schema.columns
            WHERE table_schema = DATABASE() AND table_name = ? AND column_name = "hp"', [$pelanggan]);
        $q = $q->getFirstRow();

        if ($q !== null && (int) $q->batas !== (int) $batas) {
            $this->db->query('ALTER TABLE `' . $pelanggan . '` MODIFY `hp` VARCHAR(' . $batas . ') NULL');
        }
    }

    private function t(string $nama): string
    {
        return $this->db->prefixTable($nama);
    }

    private function punyaKolom(string $tabel, string $kolom): bool
    {
        $q = $this->db->query('SELECT 1 FROM information_schema.columns
                WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?', [$tabel, $kolom]);

        return $q->getNumRows() > 0;
    }
}
