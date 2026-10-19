<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Pengembalian barang sewaan dicatat sebagai peristiwa, bukan tebakan tanggal.
 *
 * Semula "sudah kembali" disimpulkan dari tanggal `rincian.kembali` yang lewat:
 * barang pulih di rak dengan sendirinya di depan mata, nota tetap berada di tab
 * Selesai, dan poin gaji pembuat nota sudah terlanjur dihitung waktu barangnya
 * sebenarnya masih di tangan penyewa. Sekarang ada satu kenyataan yang bisa
 * diklik orang di nota: `sewa_kembali_at` terisi saat petugas menekan
 * "Sudah kembali". Selama kolom itu kosong, nota sewa belum Selesai, unitnya
 * masih mengurangi stok kolam sewa, dan poinnya belum diberikan.
 *
 * Data lama dibackfill dari tanggal `rincian.kembali` yang sudah lewat, supaya
 * angka stok dan poin hari ini tidak bergerak mundur untuk nota yang barangnya
 * memang sudah lama pulang. Nota sewa yang masih mengendap di penyewa kehilangan
 * baris ledgernya di sini: itu persis yang ingin diperbaiki — penyewaan aktif
 * tidak dihitung sebagai pekerjaan selesai sampai barangnya kembali.
 */
class KembaliSewaInvoice extends Migration
{
    public function up()
    {
        if (! $this->punyaKolom('sewa_kembali_at')) {
            $this->db->query('ALTER TABLE ' . $this->t('invoice')
                . ' ADD COLUMN sewa_kembali_at INT(10) UNSIGNED NULL AFTER status_pengiriman');
        }

        if (! $this->punyaKolom('sewa_kembali_oleh')) {
            $this->db->query('ALTER TABLE ' . $this->t('invoice')
                . ' ADD COLUMN sewa_kembali_oleh INT(10) UNSIGNED NULL AFTER sewa_kembali_at');
        }

        // nota lama yang tanggal kembalinya sudah lewat: barangnya sudah dihitung
        // kembali oleh aturan lama, jadi penanda ini ikut ditulis tanggal itu
        $this->db->query('UPDATE `' . $this->t('invoice') . '`'
            . " SET sewa_kembali_at = UNIX_TIMESTAMP(JSON_UNQUOTE(JSON_EXTRACT(rincian, '$.kembali')))"
            . " WHERE sewa_kembali_at IS NULL"
            . " AND JSON_UNQUOTE(JSON_EXTRACT(rincian, '$.tipe')) = 'sewa'"
            . " AND COALESCE(JSON_UNQUOTE(JSON_EXTRACT(rincian, '$.kembali')), '') <> ''"
            . " AND DATE(JSON_UNQUOTE(JSON_EXTRACT(rincian, '$.kembali'))) < CURDATE()");

        // poin yang terlanjur diberikan untuk sewa yang belum kembali dicabut:
        // aturannya sekarang satu baris ledger = satu nota yang barangnya pulang
        $this->db->query('DELETE p FROM `' . $this->t('poin_penjualan') . '` p'
            . ' JOIN `' . $this->t('invoice') . '` o ON o.id_invoice = p.invoice_id'
            . " WHERE JSON_UNQUOTE(JSON_EXTRACT(o.rincian, '$.tipe')) = 'sewa'"
            . ' AND o.sewa_kembali_at IS NULL');
    }

    public function down()
    {
        // baris ledger yang dicabut di atas tidak bisa disusun ulang: poin_potret
        // waktunya sudah hilang, jadi rollback hanya mengembalikan kolomnya
        if ($this->punyaKolom('sewa_kembali_oleh')) {
            $this->db->query('ALTER TABLE ' . $this->t('invoice') . ' DROP COLUMN sewa_kembali_oleh');
        }

        if ($this->punyaKolom('sewa_kembali_at')) {
            $this->db->query('ALTER TABLE ' . $this->t('invoice') . ' DROP COLUMN sewa_kembali_at');
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
