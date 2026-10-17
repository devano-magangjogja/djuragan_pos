<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\JuraganModel;
use App\Models\PoinPenjualanModel;

/**
 * Laporan akumulasi poin gaji (khusus Superadmin).
 *
 * Membaca lurus dari ledger poin_penjualan: siapa menjual berapa unit produk
 * berpoin, dan berapa poin yang terkumpul. Angkanya dipakai sebagai dasar
 * perhitungan gaji, jadi laporan ini hanya merangkum, tidak menghitung ulang.
 *
 * Superadmin memegang seluruh toko, jadi pembatasannya memakai tokoBoleh()
 * (daftar lengkap), bukan daftar toko milik akun seperti halaman lain.
 */
class Poin extends BaseController
{
    /**
     * Halaman laporan. Query string: awal, akhir, juragan, user, page.
     *
     * @return string
     */
    public function index()
    {
        $model = new PoinPenjualanModel();
        $ids   = $this->tokoBoleh();

        $awal  = $this->tanggal((string) $this->request->getGet('awal'));
        $akhir = $this->tanggal((string) $this->request->getGet('akhir'));

        if ($awal === '') {
            $awal = date('Y-m-01');
        }

        if ($akhir === '') {
            $akhir = date('Y-m-d');
        }

        if ($awal > $akhir) {
            [$awal, $akhir] = [$akhir, $awal];
        }

        $juragan = (int) $this->request->getGet('juragan');
        if ($juragan > 0 && ! in_array($juragan, $ids, true)) {
            $juragan = 0;
        }

        $user = (int) $this->request->getGet('user');
        $f    = ['awal' => $awal, 'akhir' => $akhir, 'juragan' => $juragan, 'user' => $user];

        $limit  = config('Pager')->perPage;
        $page   = max(1, (int) $this->request->getGet('page'));
        $offset = ($page - 1) * $limit;

        $juragans = [];

        foreach ((new JuraganModel())->orderBy('nama_juragan', 'ASC')->findAll() as $j) {
            $juragans[(int) $j->id_juragan] = $j->nama_juragan;
        }

        $data = [
            'title'     => 'Akumulasi Poin Gaji',
            'f'         => $f,
            'akumulasi' => $model->akumulasi($f, $ids),
            'ringkas'   => $model->ringkas($f, $ids),
            'rincian'   => $model->rincian($f, $ids, $limit, $offset),
            'total'     => $model->rincianJumlah($f, $ids),
            'pegawai'   => $model->pegawai($ids),
            'juragans'  => $juragans,
            'limit'     => $limit,
            'page'      => $page,
            'sukses'    => session('sukses'),
            'gagal'     => session('gagal'),
        ];

        return view('admin/poin/index', $data);
    }

    /** Tanggal YYYY-MM-DD yang benar-benar ada, selainnya string kosong. */
    private function tanggal(string $nilai): string
    {
        if (! preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $nilai, $m)) {
            return '';
        }

        return checkdate((int) $m[2], (int) $m[3], (int) $m[1]) ? $nilai : '';
    }
}
