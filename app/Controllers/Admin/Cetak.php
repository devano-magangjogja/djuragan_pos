<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Libraries\LaporanEkspor;
use App\Models\InvoiceModel;
use App\Models\LaporanModel;
use CodeIgniter\HTTP\DownloadResponse;

/**
 * Cetak daftar transaksi dari layar Tracking Transaksi.
 *
 * Isinya satu lembar daftar nota (bukan blok ringkasan angka seperti halaman
 * Laporan), karena yang dibutuhkan di layar ini adalah kertas kerja: nota mana
 * yang berjalan, siapa customer-nya, dan berapa sisa dananya. Barisnya tetap
 * dibaca dari LaporanModel, jadi angka lembar cetak tidak bisa berbeda dengan
 * angka laporan untuk periode yang sama.
 *
 * Ada dua cara memakai:
 *  - periode (harian/mingguan/bulanan/tahunan) berdasar satu tanggal acuan, atau
 *  - daftar nota yang sedang dicentang orang — yang terakhir ini mencetak persis
 *    yang dipilih, tanpa dibatasi periode yang sedang terbuka di layar.
 */
class Cetak extends BaseController
{
    /** Pilihan periode popup Cetak: kunci => label. */
    private const PERIODE = [
        'harian'   => 'Harian',
        'mingguan' => 'Mingguan',
        'bulanan'  => 'Bulanan',
        'tahunan'  => 'Tahunan',
    ];

    /**
     * Batas nota terpilih dalam satu berkas.
     *
     * Daftar sepanjang ini sudah bukan kertas cetak lagi, dan URL-nya kepanjangan;
     * yang lebih baik dipakai ekspor laporan berperiode.
     */
    private const BATAS_PILIH = 500;

    /**
     * @return \CodeIgniter\HTTP\DownloadResponse
     */
    public function unduh(string $format = 'pdf')
    {
        helper('fungsi');

        $this->bolehCetak();

        if (! array_key_exists($format, ['pdf' => 1, 'xlsx' => 1])) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $model   = new LaporanModel();
        $ekspor  = new LaporanEkspor();
        $ids     = $this->juraganIds();
        $pilihan = $this->pilihan();
        $periode = $this->periode();

        // nota terpilih mencetak dirinya sendiri; periode hanya membatasi saat
        // orang tidak memilih apa pun
        $f = $pilihan === []
            ? ['awal' => $periode['awal'], 'akhir' => $periode['akhir'], 'juragan' => $this->juragan($ids)]
            : ['juragan' => $this->juragan($ids), 'id' => $pilihan];

        $baris = $model->detail($ids, $f);

        // PDF dan Excel memang dua dokumen yang berbeda: PDF memotret tiap nota
        // sebagai satu kartu detail, Excel tetap tabel daftar yang mudah diolah
        // lagi di spreadsheet. Judulnya mengikuti bentuk isinya.
        $judul = ($format === 'pdf' ? 'Detail Transaksi' : 'Daftar Transaksi')
            . ($pilihan === [] ? ' ' . $periode['label'] : ' Terpilih');

        $keteran          = $this->keteran($periode, $pilihan, $f);
        $keteran['Bentuk'] = $format === 'pdf' ? 'Detail per nota' : 'Tabel daftar';

        if ($format === 'xlsx') {
            $isi = $ekspor->xlsx($judul, [$ekspor->detail($baris, 'Daftar Transaksi', $this->total($baris))], $keteran);
        } else {
            $potong = array_slice($baris, 0, LaporanEkspor::BATAS_PDF);
            $nota   = (new InvoiceModel())->detailLengkap(array_map(
                static fn (array $b): int => (int) $b['id_invoice'],
                $potong
            ));

            $isi = $ekspor->pdfDetail(
                $judul,
                $keteran,
                $nota,
                count($baris) > LaporanEkspor::BATAS_PDF
                    ? 'Berkas ini memuat ' . count($nota) . ' nota terbaru dari ' . count($baris)
                        . ' nota pada rentang ini; unduh Excel untuk daftar lengkapnya.'
                    : ''
            );
        }

        $respons = (new DownloadResponse($this->nama($periode, $pilihan, $format), false))
            ->setContentType($format === 'xlsx'
                ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
                : 'application/pdf');

        // setBinary() tidak mengembalikan $this, jadi jangan dicantai
        $respons->setBinary($isi);

        return $respons;
    }

    /**
     * Daftar nota yang bisa dicentang di dalam popup Cetak.
     *
     * Isinya mengikuti periode + toko yang sedang dipilih di popup, jadi orang
     * memilih dari rentang yang sama dengan yang akan dicetak. Batasnya sama
     * dengan batas detail laporan; kalau kena batas, popup memberi tahu supaya
     * orang mempersempit periodenya, bukan menduga daftarnya sudah lengkap.
     */
    public function daftar()
    {
        $this->bolehCetak();

        $ids     = $this->juraganIds();
        $periode = $this->periode();
        $baris   = (new LaporanModel())->pilihan($ids, [
            'awal'    => $periode['awal'],
            'akhir'   => $periode['akhir'],
            'juragan' => $this->juragan($ids),
        ]);

        return $this->response->setJSON([
            'baris'  => array_map(static fn (array $b): array => [
                'id_invoice'     => (int) $b['id_invoice'],
                'seri'           => (string) $b['seri'],
                'nama_pelanggan' => (string) ($b['nama_pelanggan'] ?? ''),
                'tagihan'        => (int) $b['tagihan'],
            ], $baris),
            'batas'  => LaporanModel::KAPASITAS_DETAIL,
            'jumlah' => count($baris),
            'rentang' => $periode['awal'] . ' s/d ' . $periode['akhir'],
        ]);
    }

    /**
     * Layar daftar transaksinya sendiri boleh dibuka CS; lembar kerja cetak ini
     * khusus admin dan superadmin, sama seperti tombol yang memanggilnya.
     */
    private function bolehCetak(): void
    {
        if (! in_array((string) session()->get('level'), ['admin', 'superadmin'], true)) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }
    }

    /**
     * Rentang periode dari satu tanggal acuan.
     *
     * Tanggal acuan diterima apa adanya dari popup (type=date), jadi batasnya
     * dihitung di sini, bukan di JavaScript: layar dengan zona waktu berbeda
     * tidak boleh menghasilkan kotak kertas yang beda isi.
     *
     * @return array{awal:string, akhir:string, label:string, kunci:string}
     */
    private function periode(): array
    {
        $kunci = (string) $this->request->getGet('periode');

        if (! array_key_exists($kunci, self::PERIODE)) {
            $kunci = 'harian';
        }

        $acuan = (string) $this->request->getGet('tanggal');

        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $acuan, $m) !== 1 || ! checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            $acuan = date('Y-m-d');
        }

        $hari = new \DateTimeImmutable($acuan);

        $rentang = match ($kunci) {
            'mingguan' => [
                $hari->modify('monday this week')->format('Y-m-d'),
                $hari->modify('sunday this week')->format('Y-m-d'),
            ],
            'bulanan' => [$hari->format('Y-m-01'), $hari->format('Y-m-t')],
            'tahunan' => [$hari->format('Y-01-01'), $hari->format('Y-12-31')],
            default   => [$acuan, $acuan],
        };

        return [
            'awal'  => $rentang[0],
            'akhir' => $rentang[1],
            'label' => self::PERIODE[$kunci],
            'kunci' => $kunci,
        ];
    }

    /** Nota yang dicentang, sudah dibuang yang bukan angka dan yang melebihi batas. */
    private function pilihan(): array
    {
        $id = array_values(array_unique(array_filter(array_map('intval', (array) $this->request->getGet('id')))));

        return array_slice($id, 0, self::BATAS_PILIH);
    }

    /** Satu toko boleh dipilih, tapi hanya kalau memang milik akun ini. */
    private function juragan(array $ids): int
    {
        $juragan = (int) $this->request->getGet('juragan');

        return $juragan > 0 && in_array($juragan, $ids, true) ? $juragan : 0;
    }

    /** Baris keterangan di kepala berkas. */
    private function keteran(array $periode, array $pilihan, array $f): array
    {
        $keteran = ['Laporan' => 'Daftar Transaksi'];

        if ($pilihan === []) {
            $keteran['Periode'] = $periode['label'] . ': ' . $periode['awal'] . ' s/d ' . $periode['akhir'];
        } else {
            $keteran['Nota terpilih'] = count($pilihan) . ' nota (di luar batasan periode)';
        }

        $keteran['Toko'] = $f['juragan'] > 0
            ? ($this->juraganPilihan()[$f['juragan']] ?? '-')
            : 'semua toko milik akun ini';

        $keteran['Dicetak'] = date('d/m/Y H:i') . ' oleh ' . (session()->get('name') ?: '-');

        return $keteran;
    }

    /** Baris Total untuk kolom uang; kolom lain dibiarkan kosong. */
    private function total(array $baris): array
    {
        $jumlah = static fn (string $k): int => array_sum(array_column($baris, $k));

        return [
            'Total', null, null, null, null,
            $jumlah('tagihan'), $jumlah('masuk'), $jumlah('sisa'), null, null,
        ];
    }

    /** Nama berkas: hanya karakter aman, karena masuk ke header Content-Disposition. */
    private function nama(array $periode, array $pilihan, string $format): string
    {
        $dasar = $pilihan === []
            ? 'transaksi-' . $periode['kunci'] . '-' . $periode['awal'] . 'sd' . $periode['akhir']
            : 'transaksi-terpilih-' . count($pilihan) . 'nota';

        return preg_replace('/[^A-Za-z0-9_-]/', '', $dasar) . '.' . $format;
    }
}
