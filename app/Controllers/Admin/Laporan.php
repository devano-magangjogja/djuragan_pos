<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Libraries\LaporanEkspor;
use App\Models\LaporanModel;
use CodeIgniter\HTTP\DownloadResponse;

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
     * Berkas ekspor satu laporan: ringkasan yang sama dengan layar, ditambah
     * lampiran daftar nota yang ikut dihitung.
     *
     * Query string-nya sama dengan halaman laporan, jadi tombol unduh cukup
     * memakai filter yang sedang terbaca.
     *
     * @return \CodeIgniter\HTTP\DownloadResponse
     */
    public function unduh(string $jenis, string $format)
    {
        helper('fungsi');

        $daftar = laporan_jenis();

        if (! array_key_exists($jenis, $daftar) || ! in_array($format, ['xlsx', 'pdf'], true)) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $ids    = $this->juraganIds();
        $model  = new LaporanModel();
        $f      = $this->filter($ids);
        $hasil  = $model->{$jenis}($ids, $f);
        $detail = $model->detail($ids, $f);

        $ekspor  = new LaporanEkspor();
        $keteran = $this->keteran($jenis, $f);

        // PDF dipotong karena Dompdf kehabisan memori pada ribuan baris; Excel utuh
        $potong  = count($detail) > LaporanEkspor::BATAS_PDF;
        $catatan = $potong
            ? 'Detail nota pada berkas ini ' . LaporanEkspor::BATAS_PDF . ' baris terbaru saja. Unduh Excel untuk daftar lengkap (' . count($detail) . ' nota).'
            : '';

        $isi = $format === 'xlsx'
            ? $ekspor->xlsx($daftar[$jenis]['label'], $ekspor->blok($jenis, $hasil, $detail), $keteran)
            : $ekspor->pdf(
                $daftar[$jenis]['label'],
                $ekspor->blok($jenis, $hasil, array_slice($detail, 0, LaporanEkspor::BATAS_PDF)),
                $keteran,
                $catatan
            );

        // setBinary() tidak mengembalikan $this, jadi jangan dicantai
        $respons = (new DownloadResponse($ekspor->nama($jenis, $f, $format), false))
            ->setContentType($format === 'xlsx'
                ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
                : 'application/pdf');

        $respons->setBinary($isi);

        return $respons;
    }

    /** Baris keterangan di kepala berkas: periode, sumbu, toko, dan filter lain. */
    private function keteran(string $jenis, array $f): array
    {
        helper('fungsi');

        $pil   = laporan_jenis()[$jenis];
        $grup  = ['mingguan' => 'per minggu', 'bulanan' => 'per bulan', 'tahunan' => 'per tahun'];

        $keteran = [
            'Laporan' => $pil['label'],
            'Periode' => $f['awal'] . ' s/d ' . $f['akhir'],
        ];

        if (! empty($pil['sumbu'])) {
            $keteran['Sumbu'] = ($grup[$f['grup']] ?? '-') . ' (mengikuti rentang tanggal)';
        }

        $keteran['Toko']    = $f['juragan'] > 0 ? ($this->juraganPilihan()[$f['juragan']] ?? '-') : 'semua toko milik akun ini';
        $keteran['Dicetak'] = date('d/m/Y H:i') . ' oleh ' . (session()->get('name') ?: '-');

        if ($f['kode'] !== '') {
            $keteran['Produk'] = $f['kode'];
        }

        if ($f['nama'] !== '') {
            $keteran['Customer'] = $f['nama'];
        }

        if ($f['tahap'] >= 0) {
            $tahap             = tahap_produksi();
            $keteran['Tahap']  = $f['tahap'] > 0 ? ($tahap[$f['tahap']][1] ?? '-') : 'belum mulai';
        }

        if ($f['bayar'] !== '' && $f['bayar'] !== 'semua') {
            $keteran['Status pembayaran'] = kategori_pembayaran()[$f['bayar']]['label'] ?? $f['bayar'];
        }

        return $keteran;
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

        // sumbu waktu tidak dipilih orang: panjang rentang tanggal yang menentukannya
        $grup = LaporanModel::grup($awal, $akhir);

        return [
            'awal'    => $awal,
            'akhir'   => $akhir,
            'grup'    => $grup,
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
