<?php

namespace App\Models;

/**
 * Foto produk: contoh bentuk barang yang dipantau stoknya.
 *
 * Semua cara menyimpan berkas diwarisi dari FotoModel — tipe dibaca dari isi
 * berkas, EXIF dibuang lewat penulisan ulang GD, nama di disk acak, thumbnail
 * dibuat sekali pakai terus. Yang membedakan hanya empat penyetel di bawah,
 * jadi tidak ada dua pipa unggah yang bisa berbeda perlakuannya.
 *
 * Fotonya nempel ke satu baris stok karena variasi ukuran tidak pernah punya
 * identitas sendiri di tabel lain. Kartu katalog tetap menampilkan gabungan
 * foto semua varian sekartu (lihat StokModel::sekartu() dan perKartu()), jadi
 * secara tampilan fotonya tetap milik "produk", bukan milik ukuran L saja.
 *
 * Barang lama tidak punya baris di tabel ini; kartunya menampilkan inisial nama.
 */
class ProdukFotoModel extends FotoModel
{
    protected $table         = 'produk_foto';
    protected $allowedFields = ['stok_id', 'berkas', 'nama_asli', 'tipe', 'byte', 'lebar', 'tinggi', 'user_id'];

    protected static function kolomAcuan(): string
    {
        return 'stok_id';
    }

    protected static function folder(): string
    {
        return 'produk';
    }

    protected static function api(): string
    {
        return 'api/produk-foto/saji';
    }

    protected static function induk(): string
    {
        return 'produk';
    }

    /**
     * Foto tiap kartu produk untuk satu halaman katalog, sekali query.
     *
     * Kuncinya urut seperti katalognya, isinya gabungan semua varian kartu itu
     * dan urut dari foto paling lama — sama seperti urutan di panel foto nota.
     *
     * @param array<int, int[]> $varian_per_kartu daftar id_stok tiap kartu
     *
     * @return array<int, list<array<string, mixed>>>
     */
    public function perKartu(array $varian_per_kartu): array
    {
        $datar = [];

        foreach ($varian_per_kartu as $ids) {
            foreach ($ids as $id) {
                $datar[(int) $id] = (int) $id;
            }
        }

        $peta = $this->banyak($datar);
        $hasil = [];

        foreach ($varian_per_kartu as $i => $ids) {
            $satu = [];

            foreach ($ids as $id) {
                foreach ($peta[(int) $id] ?? [] as $f) {
                    // satu berkas bisa saja tercatat di dua varian; cukup sekali
                    $satu[(int) $f['id_foto']] = $f;
                }
            }

            ksort($satu);
            $hasil[$i] = array_values($satu);
        }

        return $hasil;
    }

    /**
     * Foto satu varian beserta semua varian sekartunya. Dipakai panel kelola
     * foto dan form orderan, jadi fotonya tidak perlu diunggah per ukuran.
     *
     * @param int[] $stok_ids
     *
     * @return list<array<string, mixed>>
     */
    public function untukKartu(array $stok_ids): array
    {
        $satu = [];

        foreach ($this->banyak($stok_ids) as $daftar) {
            foreach ($daftar as $f) {
                $satu[(int) $f['id_foto']] = $f;
            }
        }

        ksort($satu);

        return array_values($satu);
    }
}
