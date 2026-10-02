<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Controllers\Juragan as Jrgn;
use App\Models\DasborModel;
use App\Models\StokModel;

/**
 * Halaman depan admin: ringkasan transaksi, pembayaran yang menunggu, dan stok.
 */
class Dasbor extends BaseController
{
    /** Jumlah bulan yang ditampilkan di grafik tren. */
    private const TREN_BULAN = 6;

    /**
     * @return string
     */
    public function index()
    {
        $ids         = $this->juraganIds();
        $dasborModel = new DasborModel();
        $stokModel   = new StokModel();

        $tren     = $dasborModel->trenBulanan($ids, self::TREN_BULAN);
        $bulanIni = date('Y-m');
        $bulanLalu = date('Y-m', strtotime('-1 month'));

        $ringkas_tren = [
            $bulanIni  => ['orderan' => 0, 'total' => 0, 'dibayar' => 0, 'sisa' => 0],
            $bulanLalu => ['orderan' => 0, 'total' => 0, 'dibayar' => 0, 'sisa' => 0],
        ];

        foreach ($tren as $b) {
            if (isset($ringkas_tren[$b['bulan']])) {
                $ringkas_tren[$b['bulan']] = array_map('intval', $b);
            }
        }

        $user_id    = (int) session()->get('id');
        $juragans   = Jrgn::by_user($user_id)[$user_id]['juragan'] ?? [];

        $data = [
            'title'      => 'Dasbor',
            'hari'       => $dasborModel->satuHari($ids, date('Y-m-d')),
            'tren'       => $tren,
            'bulan'      => $ringkas_tren,
            'bulan_ini'   => $bulanIni,
            'bulan_lalu'  => $bulanLalu,
            'aksi'       => $dasborModel->menungguAksi($ids),
            'saringan'   => $dasborModel->saringan($ids),
            'produksi'   => $dasborModel->ringkasanProduksi($ids),
            'sisa'       => $dasborModel->sisaTagihan($ids),
            'stok'       => $stokModel->ringkas($ids),
            'produk'     => $dasborModel->produkTeratas($ids, 3, 5),
            'transaksi'  => $dasborModel->transaksiTerbaru($ids, 8),
            'jum_juragan' => count($ids),
            'ambangnya'  => StokModel::AMBANG_MENIPIS,
            // daftar transaksi dibuka dari dasbor; akun yang cuma pegang satu toko
            // diarahkan ke tokonya sendiri, yang pegang banyak toko ke 'semua'
            'sasaran'    => count($juragans) === 1 ? (string) reset($juragans)['slug'] : 'semua',
            'nama_juragan' => array_values(array_map('strval', array_column($juragans, 'nama'))),
        ];

        return view('admin/dasbor', $data);
    }
}
