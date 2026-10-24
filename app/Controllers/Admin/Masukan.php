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
        $hanya   = (string) $this->request->getGet('prioritas') === '1';

        // tanggal dibalik lebih ramah daripada daftar kosong
        if ($dari !== '' && $sampai !== '' && $dari > $sampai) {
            [$dari, $sampai] = [$sampai, $dari];
        }

        $jumlah = $masukan->jumlah($cari, $dari, $sampai, $hanya);
        $total  = max(1, (int) ceil($jumlah / self::PER_HALAMAN));

        // nomor halaman di luar daftar dijepit ke halaman terakhir
        $halaman = min(max(1, (int) ($this->request->getGet('halaman') ?? 1)), $total);

        return view('admin/masukan', [
            'title'    => 'Kotak Masukan',
            'daftar'   => $masukan->daftar($cari, $dari, $sampai, self::PER_HALAMAN, ($halaman - 1) * self::PER_HALAMAN, $hanya),
            'cari'     => $cari,
            'dari'     => $dari,
            'sampai'   => $sampai,
            'hanya'    => $hanya,
            'jumlah'   => $jumlah,
            'semua'    => $masukan->jumlah(),
            'prioritas' => $masukan->hitungPrioritas(),
            'total'    => $total,
            'halaman'  => $halaman,
            'sukses'   => session('sukses'),
            'gagal'    => session('gagal'),
        ]);
    }

    /**
     * Tandai satu masukan prioritas selesai / buka lagi. Hanya baris prioritas yang
     * boleh diubah (dijaga di model), jadi masukan biasa tidak punya tombol ini dan
     * tidak bisa kebetulan tersentuh.
     */
    public function toggle($id = '')
    {
        $id = substr((string) $id, 0, 6);

        if ($id === '') {
            return redirect()->to('/admin/masukan')->with('gagal', 'Masukan tidak ditemukan.');
        }

        $masukan = new FeedbackModel();
        $baris   = $masukan->find($id);

        if ($baris === null || (int) ($baris->prioritas ?? 0) !== 1) {
            return redirect()->back()->with('gagal', 'Masukan itu bukan permintaan prioritas.');
        }

        $selesai = (int) ($baris->selesai ?? 0) === 1 ? false : true;
        $masukan->tandaiSelesai($id, $selesai);

        return redirect()->back()->with('sukses', $selesai
            ? 'Permintaan ditandai selesai.'
            : 'Permintaan dibuka lagi sebagai prioritas.');
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
