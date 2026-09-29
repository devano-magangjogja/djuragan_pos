<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use RuntimeException;

/**
 * Membuang 19 tabel warisan tanpa prefix yang tidak dibaca kode aplikasi
 * (dicek grep di app/ + public/: nol referensi). Persetujuan user:
 * "Drop yang kosong + config lama".
 *
 * Semua tabel ini terjangkau DBPrefix `order_` oleh model aktif, jadi yang
 * dibuang hanyalah salinan lama: bank (2 baris) sudah tergantikan order_bank
 * (22 baris, termasuk 'lainnya' dan 'cod'), migrations (13 baris) adalah log
 * lama sedangkan aplikasi memakai order_migrations.
 *
 * TIDAK disentuh: faktur, pesanan_produk, pembayaran, pengiriman,
 * biaya_ongkir, biaya_diskon (arsip order 2014-2020, kini terbaca lewat
 * view arsit_*) dan seluruh tabel order_*.
 *
 * up() menolak jalan kalau dump cadangan tidak ada. down() hanya rebuild
 * struktur kosong; isi aslinya ada di file dump tersebut.
 */
class HapusTabelWarisan extends Migration
{
    private array $tabel = [
        'bank', 'biaya', 'biaya_unik', 'dibeli', 'dj_sessions', 'invoice', 'invoice_status',
        'juragan', 'label_invoice', 'membership', 'migrations', 'notifikasi',
        'pelanggan', 'pengaturan', 'pengguna', 'pengguna_relation', 'relasi',
        'user', 'versions',
    ];

    /** Tabel yang memang harus tetap ada walau tidak dipakai kode. */
    private array $dilarang = [
        'faktur', 'pesanan_produk', 'pembayaran', 'pengiriman', 'biaya_ongkir', 'biaya_diskon',
    ];

    private string $ddl = 'Legacy/struktur_warisan.sql';

    private string $backup = 'D:/Magang/Coding/djuragan/legacy_warisan_2026-09-28.sql';

    public function up()
    {
        if (! is_file($this->backup)) {
            throw new RuntimeException(
                'Dump cadangan tidak ditemukan: ' . $this->backup
                . ' - jalankan mysqldump penuh dulu sebelum migration ini.'
            );
        }

        foreach ($this->tabel as $nama) {
            if (in_array($nama, $this->dilarang, true)) {
                throw new RuntimeException('Tabel arsip tidak boleh dibuang: ' . $nama);
            }

            $this->db->query('DROP TABLE IF EXISTS ' . $this->db->escapeIdentifiers($nama));
        }
    }

    public function down()
    {
        $berkas = APPPATH . 'Database/' . $this->ddl;

        if (! is_file($berkas)) {
            throw new RuntimeException('File DDL struktur warisan tidak ditemukan: ' . $berkas);
        }

        foreach (explode(";\n", (string) file_get_contents($berkas)) as $calon) {
            $sql = trim($calon);

            if ($this->bisaDijalankan($sql)) {
                $this->db->query($sql);
            }
        }
    }

    /** Ambil hanya statement DROP/CREATE; SET dan komentar versi dilewati. */
    private function bisaDijalankan(string $sql): bool
    {
        return stripos($sql, 'DROP TABLE') === 0 || stripos($sql, 'CREATE TABLE') === 0;
    }
}
