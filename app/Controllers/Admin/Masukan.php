<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\FeedbackModel;

/**
 * Kotak masukan Superadmin.
 *
 * CS dan Admin mengirim lewat tombol melayang "Kirim Masukan"; tombol itu sengaja
 * tidak dirender untuk Superadmin (lihat template/feedback) karena merekalah
 * tujuan masuknya, jadi layar ini satu-satunya jalan membacanya. Rutenya berdiri
 * sendiri (bukan anak settings) dan dipagari auth:superadmin.
 *
 * Isinya dibaca saja: tabel feedback tidak menyimpan status apa pun, jadi tidak
 * ada aksi tandai selesai di sini. Penyaringnya kata kunci dan rentang tanggal.
 */
class Masukan extends BaseController
{
    /** Baris per halaman. */
    private const PER_HALAMAN = 15;

    public function index()
    {
        $masukan = new FeedbackModel();

        $cari    = trim((string) ($this->request->getGet('cari') ?? ''));
        $dari    = $this->tanggal('dari');
        $sampai  = $this->tanggal('sampai');

        // tanggal dibalik lebih ramah daripada daftar kosong
        if ($dari !== '' && $sampai !== '' && $dari > $sampai) {
            [$dari, $sampai] = [$sampai, $dari];
        }

        $jumlah = $masukan->jumlah($cari, $dari, $sampai);
        $total  = max(1, (int) ceil($jumlah / self::PER_HALAMAN));

        // nomor halaman di luar daftar dijepit ke halaman terakhir
        $halaman = min(max(1, (int) ($this->request->getGet('halaman') ?? 1)), $total);

        return view('admin/masukan', [
            'title'   => 'Kotak Masukan',
            'daftar'  => $masukan->daftar($cari, $dari, $sampai, self::PER_HALAMAN, ($halaman - 1) * self::PER_HALAMAN),
            'cari'    => $cari,
            'dari'    => $dari,
            'sampai'  => $sampai,
            'jumlah'  => $jumlah,
            'semua'   => $masukan->jumlah(),
            'total'   => $total,
            'halaman' => $halaman,
            'sukses'  => session('sukses'),
            'gagal'   => session('gagal'),
        ]);
    }

    /**
     * Tanggal dari query string; hanya format Y-m-d yang diterima. Kolomnya
     * type=date, jadi nilai lain berarti diketik manual dan akan menempel
     * langsung pada perbandingan SQL di model.
     */
    private function tanggal(string $nama): string
    {
        $nilai = trim((string) ($this->request->getGet($nama) ?? ''));

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $nilai) === 1 ? $nilai : '';
    }
}
