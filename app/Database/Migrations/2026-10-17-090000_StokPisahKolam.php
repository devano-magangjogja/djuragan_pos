<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Satu baris stok kini milik satu kolam saja, ditandai kolom `kolam`.
 *
 * Sebelumnya satu baris (juragan, kode, ukuran) memikul dua kolam sekaligus:
 * stok/harga untuk jual dan stok_sewa/harga_sewa untuk sewa. Akibatnya ukuran
 * yang ditambah dari tab sewa menempel di baris yang sama dengan kartu jual,
 * jadi ikut tampil sebagai varian di katalog jual. Mulai sini varian sewa
 * menjadi baris sendiri: kolom stok/harganya menyimpan angka kolamnya, dan
 * kolom stok_sewa/harga_sewa lama berhenti dibaca (nilainya tetap disimpan
 * sebagai jejak, tidak dihapus).
 *
 * Pemilahan data lama saat migrasi:
 *  - stok jual 0 tapi ada angka sewa → barisnya pindah kolam sewa, angka sewa
 *    dipindah ke kolom stok/harga;
 *  - dua kolam terisi sekaligus → baris lama tetap jual, angka sewanya dipecah
 *    ke baris sewa baru, dan tautan orderan bertipe sewa dialihkan ke baris
 *    baru itu supaya hitungan nota lama tidak berubah.
 */
class StokPisahKolam extends Migration
{
    public function up()
    {
        if (! $this->punyaKolom('kolam')) {
            $this->db->query('ALTER TABLE ' . $this->t('stok')
                . " ADD COLUMN kolam ENUM('jual','sewa') NOT NULL DEFAULT 'jual' AFTER keterangan");
        }

        $stok = $this->t('stok');

        // dua kolam terisi sekaligus: angka sewa dipecah ke baris sendiri
        $dua = $this->db->query('SELECT * FROM `' . $stok . '`'
            . ' WHERE deleted_at IS NULL AND stok > 0 AND stok_sewa > 0')->getResultArray();

        foreach ($dua as $b) {
            $this->db->query(
                'INSERT INTO `' . $stok . '`'
                . ' (juragan_id, produk_id, kode, ukuran, kolam, harga, stok, keterangan, created_at, updated_at)'
                . " VALUES (?, ?, ?, ?, 'sewa', ?, ?, ?, ?, ?)",
                [
                    $b['juragan_id'], $b['produk_id'], $b['kode'], $b['ukuran'],
                    (int) $b['harga_sewa'], (int) $b['stok_sewa'], $b['keterangan'],
                    $b['created_at'], $b['updated_at'],
                ]
            );

            // nota bertipe sewa yang menaut baris ini pindah ke baris sewanya
            $this->db->query(
                'UPDATE `' . $this->t('dibeli') . '` d'
                . ' JOIN `' . $this->t('invoice') . '` o ON o.id_invoice = d.invoice_id'
                . ' SET d.stok_id = ' . (int) $this->db->insertID()
                . ' WHERE d.stok_id = ' . (int) $b['id_stok']
                . " AND COALESCE(JSON_UNQUOTE(JSON_EXTRACT(o.rincian, '$.tipe')), 'pembuatan') = 'sewa'"
            );
        }

        // stok jual 0 tapi ada angka sewa: seluruh barisnya pindah kolam
        $this->db->query('UPDATE `' . $stok . "` SET kolam = 'sewa'"
            . ' WHERE stok = 0 AND (stok_sewa > 0 OR harga_sewa > 0)');

        // angka kolam sewa dipindah ke kolom umum supaya satu nama kolom
        // berlaku untuk kedua kolam; harga lama dipertahankan kalau harga sewa
        // belum pernah diatur
        $this->db->query('UPDATE `' . $stok . '` SET stok = stok_sewa,'
            . ' harga = IF(harga_sewa > 0, harga_sewa, harga)'
            . " WHERE kolam = 'sewa'");
    }

    public function down()
    {
        // angka kolam sewa dikembalikan ke kolom lamanya sebelum kolom penanda
        // dibuang; baris hasil pemecahan tidak digabung ulang, jadi rollback
        // tidak sempurna untuk baris yang tadinya dua kolam terisi
        $this->db->query('UPDATE `' . $this->t('stok') . '` SET stok_sewa = stok,'
            . ' harga_sewa = harga, stok = 0'
            . " WHERE kolam = 'sewa'");

        if ($this->punyaKolom('kolam')) {
            $this->db->query('ALTER TABLE ' . $this->t('stok') . ' DROP COLUMN kolam');
        }
    }

    private function t(string $nama): string
    {
        return $this->db->prefixTable($nama);
    }

    private function punyaKolom(string $kolom): bool
    {
        return $this->db->query('SELECT 1 FROM information_schema.columns
                WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ? LIMIT 1',
            [$this->t('stok'), $kolom])->getNumRows() > 0;
    }
}
