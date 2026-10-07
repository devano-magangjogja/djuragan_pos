<?php

namespace App\Libraries;

use Dompdf\Dompdf;
use Dompdf\Options;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Berkas ekspor laporan.
 *
 * Kolom tiap laporan didefinisikan sekali di blok() dan dipakai bersama oleh
 * Excel dan PDF, jadi angka yang diunduh tidak mungkin berbeda dengan tabel di
 * layar. Nilai uang ditulis sebagai angka supaya masih bisa dijumlah orang yang
 * membuka berkasnya.
 */
class LaporanEkspor
{
    /**
     * Baris detail nota yang ikut dicetak ke PDF.
     *
     * Dompdf membuat satu objek gaya per sel, jadi 2.000 baris x 10 kolom
     * menghabiskan memori jauh di atas batas 512M. Excel tidak punya batas itu,
     * maka lampiran lengkapnya hanya dipotong di jalur PDF.
     */
    public const BATAS_PDF = 300;

    /** Label status pengiriman; helper layar mengembalikan HTML jadi tidak dipakai di sini. */
    private const KIRIM = ['1' => 'Belum dikirim', '2' => 'Sebagian dikirim', '3' => 'Terkirim'];

    /**
     * Blok tabel laporan: ringkasannya beda per jenis, lampiran detail notanya sama.
     *
     * @return list<array{judul: string, kolom: list<array{0:string,1:string,2:string,3:string}>, baris: array, total: ?list}>
     */
    public function blok(string $jenis, array $hasil, array $detail): array
    {
        helper('fungsi');

        $blok = match ($jenis) {
            'pendapatan' => [$this->pendapatan($hasil)],
            'pembayaran' => [$this->pembayaran($hasil)],
            'piutang'    => [
                $this->keranjang('Umur tunggakan', $hasil['umur']),
                $this->piutang($hasil['baris']),
            ],
            'produksi'   => [
                $this->produksi($hasil),
                $this->keranjang('Lama pengerjaan', $hasil['lama'], 'rata'),
            ],
            'produk'     => [$this->produk($hasil)],
            'pelanggan'  => [$this->pelanggan($hasil)],
            default      => [$this->pesanan($hasil)],
        };

        $blok[] = $this->detail($detail);

        // satu baris ringkasan sudah memuat seluruh angkanya, jadi baris Total
        // tidak ditulis dua kali untuk rentang yang cuma menghasilkan satu baris
        foreach ($blok as &$isi) {
            if ($isi['total'] !== null && count($isi['baris']) <= 1) {
                $isi['total'] = null;
            }
        }

        unset($isi);

        return $blok;
    }

    /** Isi berkas .xlsx: judul, keterangan filter, lalu blok tabel. */
    public function xlsx(string $judul, array $blok, array $keteran): string
    {
        $sheet = (new Spreadsheet())->getActiveSheet();
        $sheet->setTitle(preg_replace('/[^A-Za-z0-9 _-]/', '', mb_substr($judul, 0, 28)) ?: 'Laporan');

        $baris = $this->barisTeks($sheet, 1, $judul, true);
        $sheet->getStyle('A' . ($baris - 1))->getFont()->setSize(14);

        $baris = $this->blokLabel($sheet, $baris + 1, $keteran);

        $lebar = 1;

        foreach ($blok as $isi) {
            $baris = $this->blokXlsx($sheet, $baris, $isi);
            $lebar = max($lebar, count($isi['kolom']));
        }

        for ($i = 1; $i <= $lebar; ++$i) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($i))->setAutoSize(true);
        }

        $sementara = tempnam(sys_get_temp_dir(), 'laporan');
        (new Xlsx($sheet->getParent()))->save($sementara);
        $isi = (string) file_get_contents($sementara);
        unlink($sementara);

        return $isi;
    }

    /** Isi berkas PDF dari view cetak yang membaca blok yang sama. */
    public function pdf(string $judul, array $blok, array $keteran, string $catatan = ''): string
    {
        $opsi = new Options();
        $opsi->set('chroot', [FCPATH]);

        $dompdf = new Dompdf($opsi);
        $dompdf->loadHtml(view('admin/laporan_cetak', [
            'judul'   => $judul,
            'blok'    => $blok,
            'keteran' => $keteran,
            'catatan' => $catatan,
        ]));
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        return (string) $dompdf->output();
    }

    /** Nama berkas: hanya karakter aman, karena masuk ke header Content-Disposition. */
    public function nama(string $jenis, array $f, string $format): string
    {
        helper('fungsi');

        $dasar = 'laporan-' . $jenis . '-' . $f['awal'] . 'sd' . $f['akhir'];

        // laporan tanpa sumbu waktu tidak boleh menyebut periode di namanya
        if (! empty(laporan_jenis()[$jenis]['sumbu'])) {
            $dasar .= '-' . ($f['grup'] ?? 'mingguan');
        }

        return preg_replace('/[^A-Za-z0-9_-]/', '', $dasar) . '.' . $format;
    }

    /** Nilai satu sel menurut tipe kolom. */
    public function nilai(string $tipe, $v)
    {
        switch ($tipe) {
            case 'angka':
            case 'rp':
            case 'persen':
                return $v === null ? null : (int) $v;

            case 'hari':
                return (int) $v . ' hari';

            case 'tanggal':
                $stamped = ($v === null || $v === '') ? false : strtotime((string) $v);

                return $stamped === false ? '-' : date('d/m/Y', $stamped);

            case 'cek':
                return label_bayar_cek($v)['teks'];

            case 'bayar':
                return status_pembayaran([], (int) $v, 'label');

            case 'tahap':
                $tahap = tahap_produksi();

                return (int) $v > 0 ? ($tahap[(int) $v][1] ?? '-') : 'Belum mulai';

            case 'kirim':
                return self::KIRIM[(string) $v] ?? 'Belum dikirim';

            default:
                return ($v === null || $v === '') ? '-' : (string) $v;
        }
    }

    /** Teks rupiah untuk baris keterangan dan kartu. */
    public function rupiah($n): string
    {
        return 'Rp ' . number_format((int) $n, 0, ',', '.');
    }

    private function pesanan(array $hasil): array
    {
        return [
            'judul' => 'Ringkasan per ' . $hasil['periode'],
            'kolom' => [
                ['Periode', 'periode', 'teks', 'kiri'],
                ['Orderan', 'orderan', 'angka', 'kanan'],
                ['Customer', 'pelanggan', 'angka', 'kanan'],
                ['Nilai orderan', 'tagihan', 'rp', 'kanan'],
                ['Dana masuk', 'masuk', 'rp', 'kanan'],
                ['Tercatat', 'tercatat', 'rp', 'kanan'],
                ['Sisa', 'sisa', 'rp', 'kanan'],
                ['Lunas', 'lunas', 'angka', 'kanan'],
                ['Terkirim', 'terkirim', 'angka', 'kanan'],
            ],
            'baris' => $hasil['baris'],
            'total' => $this->total($hasil['ringkas'], ['orderan', 'pelanggan', 'tagihan', 'masuk', 'tercatat', 'sisa', 'lunas', 'terkirim']),
        ];
    }

    private function pendapatan(array $hasil): array
    {
        return [
            'judul' => 'Ringkasan per ' . $hasil['periode'],
            'kolom' => [
                ['Periode', 'periode', 'teks', 'kiri'],
                ['Orderan', 'orderan', 'angka', 'kanan'],
                ['Nilai orderan', 'tagihan', 'rp', 'kanan'],
                ['Dana masuk', 'masuk', 'rp', 'kanan'],
                ['Tercatat', 'tercatat', 'rp', 'kanan'],
                ['Sisa', 'sisa', 'rp', 'kanan'],
                ['Terbayar', 'terbayar', 'persen', 'kanan'],
                ['Lunas', 'lunas', 'angka', 'kanan'],
                ['Belum lunas', 'belum_lunas', 'angka', 'kanan'],
            ],
            'baris' => $this->persen($hasil['baris']),
            'total' => $this->total($hasil['ringkas'], ['orderan', 'tagihan', 'masuk', 'tercatat', 'sisa', null, 'lunas']),
        ];
    }

    private function pembayaran(array $hasil): array
    {
        return [
            'judul' => 'Catatan pembayaran',
            'kolom' => [
                ['Transfer', 'tanggal_pembayaran', 'tanggal', 'kiri'],
                ['Faktur', 'seri', 'teks', 'kiri'],
                ['Customer', 'nama_pelanggan', 'teks', 'kiri'],
                ['Juragan', 'nama_juragan', 'teks', 'kiri'],
                ['Nominal', 'total_pembayaran', 'rp', 'kanan'],
                ['Dana', 'nama_bank', 'teks', 'kiri'],
                ['Status cek', 'status', 'cek', 'kiri'],
                ['Dicek', 'tanggal_cek', 'tanggal', 'kiri'],
                ['Status orderan', 'status_pembayaran', 'bayar', 'kiri'],
            ],
            'baris' => $hasil['baris'],
            'total' => null,
        ];
    }

    private function piutang(array $baris): array
    {
        return [
            'judul' => 'Nota belum lunas',
            'kolom' => [
                ['Faktur', 'seri', 'teks', 'kiri'],
                ['Customer', 'nama_pelanggan', 'teks', 'kiri'],
                ['Juragan', 'nama_juragan', 'teks', 'kiri'],
                ['Tanggal pesan', 'tanggal_pesan', 'tanggal', 'kiri'],
                ['Tenggat', 'deadline', 'tanggal', 'kiri'],
                ['Nilai orderan', 'tagihan', 'rp', 'kanan'],
                ['Dana masuk', 'masuk', 'rp', 'kanan'],
                ['Sisa', 'sisa', 'rp', 'kanan'],
                ['Umur', 'umur', 'hari', 'kanan'],
                ['Status orderan', 'status_pembayaran', 'bayar', 'kiri'],
            ],
            'baris' => $baris,
            'total' => null,
        ];
    }

    /** Tabel berkeranjang: umur tunggakan dan lama pengerjaan punya bentuk sama. */
    private function keranjang(string $judul, array $baris, string $kolom_akhir = 'sisa'): array
    {
        return [
            'judul' => $judul,
            'kolom' => [
                [$judul, 'keranjang', 'teks', 'kiri'],
                ['Orderan', 'orderan', 'angka', 'kanan'],
                [$kolom_akhir === 'rata' ? 'Rata-rata' : 'Sisa', $kolom_akhir, $kolom_akhir === 'rata' ? 'hari' : 'rp', 'kanan'],
            ],
            'baris' => $baris,
            'total' => null,
        ];
    }

    private function produksi(array $hasil): array
    {
        return [
            'judul' => 'Orderan per tahap terakhir',
            'kolom' => [
                ['Tahap', 'tahap', 'tahap', 'kiri'],
                ['Berhenti di sini', 'orderan', 'angka', 'kanan'],
                ['Pernah dikerjakan', 'dikerjakan', 'angka', 'kanan'],
                ['Sudah selesai', 'beres', 'angka', 'kanan'],
                ['Nilai orderan', 'tagihan', 'rp', 'kanan'],
                ['Sisa', 'sisa', 'rp', 'kanan'],
            ],
            'baris' => $hasil['baris'],
            'total' => null,
        ];
    }

    private function produk(array $hasil): array
    {
        return [
            'judul' => 'Penjualan per produk',
            'kolom' => [
                ['Produk', 'produk', 'teks', 'kiri'],
                ['Orderan', 'orderan', 'angka', 'kanan'],
                ['Unit', 'qty', 'angka', 'kanan'],
                ['Nilai', 'nilai', 'rp', 'kanan'],
            ],
            'baris' => $hasil['baris'],
            'total' => null,
        ];
    }

    private function pelanggan(array $hasil): array
    {
        return [
            'judul' => 'Penjualan per customer',
            'kolom' => [
                ['Customer', 'pelanggan', 'teks', 'kiri'],
                ['Orderan', 'orderan', 'angka', 'kanan'],
                ['Nilai orderan', 'tagihan', 'rp', 'kanan'],
                ['Dana masuk', 'masuk', 'rp', 'kanan'],
                ['Sisa', 'sisa', 'rp', 'kanan'],
                ['Lunas', 'lunas', 'angka', 'kanan'],
                ['Pertama', 'pertama', 'tanggal', 'kiri'],
                ['Terakhir', 'terakhir', 'tanggal', 'kiri'],
            ],
            'baris' => $hasil['baris'],
            'total' => null,
        ];
    }

    private function detail(array $baris): array
    {
        return [
            'judul' => 'Detail nota',
            'kolom' => [
                ['Faktur', 'seri', 'teks', 'kiri'],
                ['Tanggal pesan', 'tanggal_pesan', 'tanggal', 'kiri'],
                ['Tenggat', 'deadline', 'tanggal', 'kiri'],
                ['Juragan', 'nama_juragan', 'teks', 'kiri'],
                ['Customer', 'nama_pelanggan', 'teks', 'kiri'],
                ['Nilai orderan', 'tagihan', 'rp', 'kanan'],
                ['Dana masuk', 'masuk', 'rp', 'kanan'],
                ['Sisa', 'sisa', 'rp', 'kanan'],
                ['Status pembayaran', 'status_pembayaran', 'bayar', 'kiri'],
                ['Pengiriman', 'status_pengiriman', 'kirim', 'kiri'],
            ],
            'baris' => $baris,
            'total' => null,
        ];
    }

    /** Baris total dari angka ringkasan; entri null berarti kolomnya dikosongkan. */
    private function total(array $ringkas, array $kunci): array
    {
        $baris = ['Total'];

        foreach ($kunci as $k) {
            $baris[] = $k === null ? null : (int) ($ringkas[$k] ?? 0);
        }

        return $baris;
    }

    /** Kolom "Terbayar" bukan kolom SQL: masuk dibagi tagihan, dihitung di sini. */
    private function persen(array $baris): array
    {
        foreach ($baris as $i => $b) {
            $baris[$i]['terbayar'] = (int) $b['tagihan'] > 0
                ? (int) round((int) $b['masuk'] / (int) $b['tagihan'] * 100)
                : null;
        }

        return $baris;
    }

    /** Tulis label tebal di kolom A, mengembalikan baris berikutnya. */
    private function barisTeks($sheet, int $baris, string $teks, bool $tebal = false): int
    {
        $sheet->setCellValue('A' . $baris, $teks);

        if ($tebal) {
            $sheet->getStyle('A' . $baris)->getFont()->setBold(true);
        }

        return $baris + 1;
    }

    /** Pasangan label => nilai (keterangan filter dan kartu ringkasan). */
    private function blokLabel($sheet, int $baris, array $pasangan): int
    {
        foreach ($pasangan as $label => $nilai) {
            $sheet->setCellValue('A' . $baris, (string) $label);
            $sheet->setCellValue('B' . $baris, (string) $nilai);
            $sheet->getStyle('A' . $baris)->getFont()->setBold(true);
            ++$baris;
        }

        return $baris + 1;
    }

    /** Satu blok tabel: judul, kepala, baris data, dan total. @return baris berikutnya */
    private function blokXlsx($sheet, int $baris, array $blok): int
    {
        $lebar = count($blok['kolom']);
        $akhir = Coordinate::stringFromColumnIndex($lebar);

        $baris = $this->barisTeks($sheet, $baris, $blok['judul'], true);

        foreach ($blok['kolom'] as $i => [$label, , , $rata]) {
            $huruf = Coordinate::stringFromColumnIndex($i + 1);
            $gaya  = $sheet->getStyle($huruf . $baris);
            $gaya->getFont()->setBold(true);
            $gaya->getAlignment()->setHorizontal($rata === 'kanan' ? Alignment::HORIZONTAL_RIGHT : Alignment::HORIZONTAL_LEFT);
        }

        $sheet->fromArray(array_column($blok['kolom'], 0), null, 'A' . $baris);

        $pertama = $baris;

        foreach ($blok['baris'] as $isi) {
            $nilai = array_map(fn (array $k) => $this->nilai($k[2], $isi[$k[1]] ?? null), $blok['kolom']);
            $this->barisSel($sheet, ++$baris, $blok, $nilai);
        }

        if ($blok['total'] !== null) {
            $this->barisSel($sheet, ++$baris, $blok, $blok['total'], true);
        }

        $sheet->getStyle('A' . $pertama . ':' . $akhir . $baris)
            ->getBorders()->getInside()->setBorderStyle(Border::BORDER_THIN);

        return $baris + 2;
    }

    /**
     * Satu baris nilai. Kolom teks diberi format teks supaya tanggal dan angka
     * seperti "2026-10" tidak diubah Excel jadi tanggal atau rumus.
     */
    private function barisSel($sheet, int $baris, array $blok, array $nilai, bool $tebal = false): void
    {
        foreach ($blok['kolom'] as $i => [, , $tipe, $rata]) {
            $huruf = Coordinate::stringFromColumnIndex($i + 1);
            $sel   = $huruf . $baris;
            $gaya  = $sheet->getStyle($sel);

            $sheet->setCellValue($sel, $nilai[$i] ?? null);

            $gaya->getAlignment()->setHorizontal($rata === 'kanan' ? Alignment::HORIZONTAL_RIGHT : Alignment::HORIZONTAL_LEFT);

            if ($tipe === 'rp' || $tipe === 'angka') {
                $gaya->getNumberFormat()->setFormatCode('#,##0');
            } elseif ($tipe === 'persen') {
                $gaya->getNumberFormat()->setFormatCode('0"%"');
            } else {
                $gaya->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
            }

            if ($tebal) {
                $gaya->getFont()->setBold(true);
            }
        }
    }
}
