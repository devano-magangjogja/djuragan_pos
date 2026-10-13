<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Models\StokModel;

/**
 * Lookup stok untuk form tulis orderan.
 *
 * Form orderan boleh mencatat barang dengan nama yang diketik bebas, tapi kalau
 * barangnya sebenarnya sudah dipantau stoknya, lebih akurat mengambil varian yang
 * ada: harganya, ukurannya, dan foto produknya ikut terbawa. Endpoint ini hanya
 * membaca, dan hanya untuk toko yang boleh dipakai akun ini menulis orderan.
 */
class Stok extends BaseController
{
    /**
     * GET api/stok/cari?q=<kata>&juragan_id=<id>
     *
     * Jawaban sengaja formasinya sama dengan pencarian pelanggan
     * (query + results) supaya JS di form tidak perlu dua pola baca.
     */
    public function cari()
    {
        $cari    = trim((string) $this->request->getGet('q'));
        $juragan = (int) $this->request->getGet('juragan_id');
        $ids     = $this->juraganIds();

        // orderan ditulis untuk satu toko, jadi tokonya harus dipilih dulu dan
        // harus toko milik akun ini; kalau tidak, jawabannya kosong, bukan semua
        if ($cari === '' || $juragan < 1 || ! in_array($juragan, $ids, true)) {
            return $this->response->setJSON(['query' => $cari, 'results' => []]);
        }

        $hasil = [];

        foreach ((new StokModel())->saran($ids, $juragan, $cari) as $b) {
            $hasil[] = [
                'id_stok'   => (int) $b['id_stok'],
                'juragan_id' => (int) $b['juragan_id'],
                'kode'      => (string) $b['kode'],
                'ukuran'    => (string) ($b['ukuran'] ?? ''),
                'harga'     => (int) $b['harga'],
                'stok'      => (int) $b['stok'],
                'keterangan' => (string) ($b['keterangan'] ?? ''),
            ];
        }

        return $this->response->setJSON(['query' => $cari, 'results' => $hasil]);
    }
}
