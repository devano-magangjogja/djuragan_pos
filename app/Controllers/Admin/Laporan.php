<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\LaporanModel;

/**
 * Laporan untuk admin dan superadmin: pesanan, pendapatan, pembayaran, piutang,
 * produksi, produk, dan customer.
 *
 * Semua jenis laporan dibaca dari satu LaporanModel dengan satu klausul filter,
 * jadi angka dua laporan untuk periode yang sama selalu bisa dibandingkan.
 */
class Laporan extends BaseController
{
    /** Isian teks filter dipotong sampai sepanjang ini. */
    private const PANJANG_TEKS = 40;

    /**
     * Satu halaman laporan. Query string: awal, akhir, juragan, kode, tahap, bayar, nama.
     *
     * @return string
     */
    public function index(string $jenis = 'pesanan')
    {
        helper('fungsi');

        $daftar = laporan_jenis();

        // nama laporan hanya boleh dari daftar helper, sama seperti tab transaksi
        if (! array_key_exists($jenis, $daftar)) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $ids   = $this->juraganIds();
        $model = new LaporanModel();
        $f     = $this->filter($ids);

        $data = [
            'title'    => $daftar[$jenis]['label'],
            'jenis'    => $jenis,
            'pil_jenis' => $daftar,
            'f'        => $f,
            'hasil'    => $model->{$jenis}($ids, $f),
            'juragans' => $this->juraganPilihan(),
            'tahap'    => tahap_produksi(),
            'kategori' => kategori_pembayaran(),
            'kode'     => $model->daftarKode($ids),
        ];

        return view('admin/laporan', $data);
    }

    /**
     * Filter dari query string, sudah dibersihkan.
     *
     * Nilai yang tidak dikenal dibuang dan diganti nilai aman, bukan diteruskan apa
     * adanya: rentang tanggal wajib ada supaya orderan lama tetap ikut terbaca.
     *
     * @return array<string, mixed>
     */
    private function filter(array $ids): array
    {
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
        // juragan yang tidak dimiliki pengguna dianggap tidak ada
        if ($juragan > 0 && ! in_array($juragan, $ids, true)) {
            $juragan = 0;
        }

        $tahap_raw = $this->request->getGet('tahap');
        // -1 artinya tanpa filter; tahap 0 = orderan yang belum masuk antrean kerja,
        // jadi parameter yang tidak dikirim sama sekali tidak boleh jadi 0
        $tahap     = is_numeric($tahap_raw) ? (int) $tahap_raw : -1;

        if ($tahap < 0 || $tahap > count(tahap_produksi())) {
            $tahap = -1;
        }

        $bayar = (string) $this->request->getGet('bayar');

        if ($bayar !== '' && ! array_key_exists($bayar, kategori_pembayaran())) {
            $bayar = '';
        }

        return [
            'awal'    => $awal,
            'akhir'   => $akhir,
            'juragan' => $juragan,
            'kode'    => $this->teks((string) $this->request->getGet('kode')),
            'tahap'   => $tahap,
            'bayar'   => $bayar,
            'nama'    => $this->teks((string) $this->request->getGet('nama')),
        ];
    }

    /** Tanggal YYYY-MM-DD yang benar-benar ada, selainnya string kosong. */
    private function tanggal(string $nilai): string
    {
        if (! preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $nilai, $m)) {
            return '';
        }

        return checkdate((int) $m[2], (int) $m[3], (int) $m[1]) ? $nilai : '';
    }

    private function teks(string $nilai): string
    {
        return mb_substr(trim($nilai), 0, self::PANJANG_TEKS);
    }
}
