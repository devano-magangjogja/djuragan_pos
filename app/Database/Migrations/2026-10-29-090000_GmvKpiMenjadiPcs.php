<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * GMV KPI CS dibaca dalam pcs produk katalog, bukan nominal rupiah.
 *
 * Manajemen menilai CS dari berapa unit barang yang berhasil ditutup, bukan dari
 * seberapa besar uang yang masuk. Nilai rupiah tetap ada di halaman Laporan dan
 * di poin 'nilai_lunas', tapi indikator gmv_cs di halaman KPI tidak lagi
 * dibandingkan terhadap target pendapatan: pembilangnya sekarang jumlah pcs dari
 * order_dibeli yang terhubung ke master katalog produk pada nota yang sudah lunas.
 *
 * kpi_target adalah baris konfigurasi yang dibaca model, jadi definisi, satuan,
 * dan nama indikator harus ikut berubah — kalau tidak, halaman Detail dan Target
 * akan menulis "Rp" di angka yang sebenarnya pcs.
 *
 * Target lama (rupiah) dikosongkan, baik `target` per-CS maupun `target_tim`
 * seluruh tim: angka pendapatan tidak ada artinya sebagai pcs, dan sistem tidak
 * boleh mengarang target baru. Setelah ini kartu GMV menulis "target belum diisi"
 * sampai Superadmin memasukkan angka pcs di halaman Target dan Bobot KPI.
 */
class GmvKpiMenjadiPcs extends Migration
{
    /** Isi lama indikator sebelum diubah; dipakai down() supaya teksnya kembali persis. */
    private const LAMA = [
        'nama'     => 'Kontribusi GMV CS',
        'satuan'   => 'rupiah',
        'definisi' => 'Total nilai nota valid yang diatribusikan ke CS pada periode laporan.',
        'aturan'   => 'Nota valid = status pembayaran lunas atau lunas lebih (5/6), tidak dibatalkan, dan tidak dihapus. Nilai ikut ongkir dan potongan biaya tambahan. Nota batal/refund keluar perhitungan.',
    ];

    /** Isi baru: pcs produk katalog, sesuai cara KpiModel::nilaiUnit() menghitung. */
    private const BARU = [
        'nama'     => 'GMV Produk Katalog CS (pcs)',
        'satuan'   => 'pcs',
        'definisi' => 'Jumlah pcs produk katalog yang diatribusikan ke CS pada periode laporan, dihitung dari nota yang sudah lunas.',
        'aturan'   => 'Pcs diambil dari order_dibeli.qty untuk baris yang terhubung ke master katalog produk (produk_id terisi); barang yang tidak tercatat di katalog tidak ikut. Barang jual dan barang sewa sama-sama dihitung satu pcs per unit. Hanya nota lunas atau lunas lebih (status pembayaran 5/6) yang tidak dibatalkan dan tidak dihapus.',
    ];

    public function up()
    {
        $data = self::BARU + [
            // target rupiah lama tidak diterjemahkan jadi pcs: keduanya
            // dikosongkan, Superadmin yang mengisi angka pcs lewat halaman Target
            'target'     => null,
            'target_tim' => null,
            'updated_at' => time(),
        ];

        $this->db->table('kpi_target')->where('indikator', 'gmv_cs')->update($data);
    }

    public function down()
    {
        // nilai target tidak bisa dikembalikan (sudah ditimpa jadi NULL); yang
        // kembali hanya teks definisinya
        $this->db->table('kpi_target')
            ->where('indikator', 'gmv_cs')
            ->update(self::LAMA + [
                'target'     => null,
                'target_tim' => null,
                'updated_at' => time(),
            ]);
    }
}
