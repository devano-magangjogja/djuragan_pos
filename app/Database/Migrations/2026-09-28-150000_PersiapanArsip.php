<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Persiapan arsip 2014-2020: index pada tabel warisan yang dipertahankan
 * + view baca-saja dengan nama kolom yang sudah diseragamkan ke skema
 * aktif, supaya CRM/KPI bisa membaca histori tanpa menyentuh tabel aslinya.
 *
 * Tabel sumber (faktur, pesanan_produk, pembayaran, pengiriman,
 * biaya_ongkir, biaya_diskon) tidak diubah isinya sedikit pun.
 */
class PersiapanArsip extends Migration
{
    public function up()
    {
        foreach ($this->daftarIndex() as [$tabel, $nama, $kolom]) {
            if ($this->punyaIndex($tabel, $nama)) {
                continue;
            }

            $this->db->query('CREATE INDEX ' . $nama . ' ON ' . $this->table($tabel) . ' (' . $kolom . ')');
        }

        foreach ($this->daftarView() as $sql) {
            $this->db->query($sql);
        }
    }

    public function down()
    {
        foreach (array_reverse($this->daftarView()) as $sql) {
            $nama = $this->namaView($sql);

            if ($nama !== null) {
                $this->db->query('DROP VIEW IF EXISTS ' . $this->db->escapeIdentifiers($nama));
            }
        }

        foreach (array_reverse($this->daftarIndex()) as [$tabel, $nama, $kolom]) {
            if ($this->punyaIndex($tabel, $nama)) {
                $this->db->query('DROP INDEX ' . $nama . ' ON ' . $this->table($tabel));
            }
        }
    }

    private function daftarIndex(): array
    {
        return [
            ['faktur', 'idx_faktur_seri', '`seri_faktur`'],
            ['faktur', 'idx_faktur_juragan_tanggal', '`juragan_id`, `tanggal_dibuat`'],
            ['faktur', 'idx_faktur_nama', '`nama`'],
            ['pesanan_produk', 'idx_arsip_produk_faktur', '`faktur_id`'],
            ['pesanan_produk', 'idx_arsip_produk_kode', '`kode`'],
            ['pembayaran', 'idx_arsip_bayar_faktur', '`faktur_id`'],
            ['pengiriman', 'idx_arsip_kirim_faktur', '`faktur_id`'],
            ['biaya_ongkir', 'idx_arsip_ongkir_faktur', '`faktur_id`'],
            ['biaya_diskon', 'idx_arsip_diskon_faktur', '`faktur_id`'],
        ];
    }

    private function daftarView(): array
    {
        return [
            // Satu baris per order lama, kolom mengikuti nama di order_invoice.
            'CREATE OR REPLACE VIEW arsit_order AS
             SELECT f.id_faktur AS id_invoice,
                    f.seri_faktur AS seri,
                    DATE(FROM_UNIXTIME(CAST(f.tanggal_dibuat AS UNSIGNED))) AS tanggal_pesan,
                    FROM_UNIXTIME(CAST(f.tanggal_dibuat AS UNSIGNED)) AS dibuat_pada,
                    f.juragan_id, f.pengguna_id AS user_id,
                    f.nama AS nama_pelanggan, f.hp1 AS hp_utama, f.hp2 AS hp_alternatif,
                    f.alamat, f.tipe AS channel, f.keterangan,
                    f.status_transfer AS status_pembayaran,
                    f.status_paket AS status_paket,
                    f.status_kirim AS status_pengiriman,
                    IFNULL(p.qty, 0) AS total_qty,
                    IFNULL(p.nilai, 0) AS nilai_produk,
                    IFNULL(b.dibayar, 0) AS total_dibayar
             FROM faktur f
             LEFT JOIN (SELECT faktur_id, SUM(jumlah) qty, SUM(jumlah * harga) nilai
                        FROM pesanan_produk GROUP BY faktur_id) p ON p.faktur_id = f.id_faktur
             LEFT JOIN (SELECT faktur_id, SUM(jumlah) dibayar
                        FROM pembayaran GROUP BY faktur_id) b ON b.faktur_id = f.id_faktur',

            // Baris produk arsip, sudah ditempel tanggal + juragan agar bisa langsung di-group.
            'CREATE OR REPLACE VIEW arsit_order_item AS
             SELECT s.id_pesanproduk AS id_item,
                    s.faktur_id AS invoice_id,
                    f.seri_faktur AS seri,
                    DATE(FROM_UNIXTIME(CAST(f.tanggal_dibuat AS UNSIGNED))) AS tanggal_pesan,
                    f.juragan_id, s.kode, s.ukuran, s.jumlah AS qty, s.harga,
                    (s.jumlah * s.harga) AS subtotal
             FROM pesanan_produk s
             JOIN faktur f ON f.id_faktur = s.faktur_id',

            'CREATE OR REPLACE VIEW arsit_pembayaran AS
             SELECT b.id_pembayaran, b.faktur_id AS invoice_id, f.seri_faktur AS seri,
                    DATE(FROM_UNIXTIME(CAST(b.tanggal_bayar AS UNSIGNED))) AS tanggal_bayar,
                    b.jumlah AS nominal, b.rekening,
                    CASE WHEN b.tanggal_cek IS NULL OR CHAR_LENGTH(b.tanggal_cek) = 0 THEN NULL
                         ELSE DATE(FROM_UNIXTIME(CAST(b.tanggal_cek AS UNSIGNED))) END AS tanggal_cek
             FROM pembayaran b
             JOIN faktur f ON f.id_faktur = b.faktur_id',

            'CREATE OR REPLACE VIEW arsit_pengiriman AS
             SELECT k.id_pengiriman, k.faktur_id AS invoice_id, f.seri_faktur AS seri,
                    k.kurir, k.resi, k.ongkir,
                    DATE(FROM_UNIXTIME(CAST(k.tanggal_kirim AS UNSIGNED))) AS tanggal_kirim
             FROM pengiriman k
             JOIN faktur f ON f.id_faktur = k.faktur_id',

            // Ongkir dan diskon lama disatukan karena struktur keduanya identik.
            'CREATE OR REPLACE VIEW arsit_biaya AS
             SELECT o.id_ongkir AS id_biaya, o.faktur_id AS invoice_id, \'ongkir\' AS jenis, o.nominal
             FROM biaya_ongkir o
             UNION ALL
             SELECT d.id_diskon AS id_biaya, d.faktur_id AS invoice_id, \'diskon\' AS jenis, d.nominal
             FROM biaya_diskon d',

            // Basis segmentasi pelanggan untuk CRM: siapa.order berapa kali, kapan, berapa nilainya.
            'CREATE OR REPLACE VIEW arsit_pelanggan AS
             SELECT f.nama AS nama_pelanggan,
                    f.hp1 AS hp_utama,
                    COUNT(*) AS jumlah_order,
                    MIN(DATE(FROM_UNIXTIME(CAST(f.tanggal_dibuat AS UNSIGNED)))) AS order_pertama,
                    MAX(DATE(FROM_UNIXTIME(CAST(f.tanggal_dibuat AS UNSIGNED)))) AS order_terakhir,
                    COUNT(DISTINCT f.juragan_id) AS jumlah_juragan,
                    IFNULL(MAX(i.nilai), 0) AS nilai_order_terbesar
             FROM faktur f
             LEFT JOIN (SELECT faktur_id, SUM(jumlah * harga) nilai
                        FROM pesanan_produk GROUP BY faktur_id) i ON i.faktur_id = f.id_faktur
             GROUP BY f.nama, f.hp1',
        ];
    }

    private function namaView(string $sql): ?string
    {
        return preg_match('/CREATE\s+(?:OR\s+REPLACE\s+)?VIEW\s+`?([A-Za-z0-9_]+)`?/i', $sql, $m) === 1
            ? $m[1]
            : null;
    }

    /** Tabel arsip tidak memakai prefix `order_`. */
    private function table(string $tabel): string
    {
        return $this->db->escapeIdentifiers($tabel);
    }

    private function punyaIndex(string $tabel, string $nama): bool
    {
        $q = $this->db->query(
            'SELECT 1 FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ? LIMIT 1',
            [$tabel, $nama]
        );

        return $q->getNumRows() > 0;
    }
}
