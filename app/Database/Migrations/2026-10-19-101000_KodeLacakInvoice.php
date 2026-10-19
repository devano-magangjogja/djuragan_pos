<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Kode lacak per orderan: pelengkap nomor nota untuk endpoint API publik.
 *
 * Nomor nota (seri) itu mudah ditebak — urut dan tercetak di mana-mana. Kalau
 * status orderan dibuka ke pihak luar hanya modal nomor, orang bisa menggeser
 * angkanya dan membaca orderan tetangganya. Dengan kode acak, pemanggil harus
 * punya DUA hal yang hanya diketahui penjual dan pembelinya.
 *
 * Kolomnya NULL dan diisi acak oleh migrasi ini untuk baris lama; orderan baru
 * mendapat kodenya saat ditulis (lihat KunciApiModel::kodeBaru()). Tidak ada
 * unique index: kodenya selalu dibandingkan bersama nomor notanya, jadi tabrakan
 * antar nota tidak membuat siapa pun bisa membuka nota orang lain.
 */
class KodeLacakInvoice extends Migration
{
    public function up()
    {
        if (! $this->punyaKolom('kode_lacak')) {
            $this->db->query('ALTER TABLE ' . $this->t('invoice')
                . ' ADD COLUMN kode_lacak CHAR(9) NULL AFTER seri');
        }

        // MySQL menghitung sekali per baris, jauh lebih ringan daripada 15 ribu
        // UPDATE dari PHP; formatnya 4KXJ-9M2A supaya enak dibaca lewat WA
        $this->db->query("UPDATE " . $this->t('invoice') . " SET kode_lacak = CONCAT("
            . "UPPER(SUBSTRING(MD5(CONCAT(id_invoice, RAND())), 1, 4)), '-',"
            . "UPPER(SUBSTRING(MD5(CONCAT(id_invoice, RAND())), 5, 4)))"
            . ' WHERE kode_lacak IS NULL');
    }

    public function down()
    {
        if ($this->punyaKolom('kode_lacak')) {
            $this->db->query('ALTER TABLE ' . $this->t('invoice') . ' DROP COLUMN kode_lacak');
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
            [$this->t('invoice'), $kolom])->getNumRows() > 0;
    }
}
