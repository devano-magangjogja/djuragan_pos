<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\KpiModel;
use CodeIgniter\Exceptions\PageNotFoundException;

/**
 * Halaman KPI karyawan untuk admin dan superadmin.
 *
 * Yang ditampilkan hanya angka yang benar-benar bisa dihitung dari isi database.
 * Indikator yang pembilangnya belum pernah dicatat tampil dengan keterangan, bukan
 * dengan angka nol: skor palsu lebih berbahaya daripada kolom yang kosong.
 *
 * Saringan (periode, peran, pegawai, toko) diproses di model sebagai bind yang
 * sudah divalidasi di sini, jadi tidak ada satu pun nilai dari URL yang masuk ke
 * SQL apa adanya.
 */
class Kpi extends BaseController
{
    /** Pilihan keranjang waktu grafik. */
    private const PERIODE = ['harian' => 'Harian', 'mingguan' => 'Mingguan', 'bulanan' => 'Bulanan'];

    /** Pilihan peran yang dinilai. */
    private const PERAN = ['semua' => 'Semua karyawan', 'admin' => 'Admin', 'cs' => 'Customer Service'];

    /** Baris per halaman rekap dan detail. */
    private const HALAMAN = [10, 25, 50];

    /**
     * Ringkasan: kartu indikator, grafik tren, perbandingan antarpegawai, rekap.
     *
     * @return string
     */
    public function index()
    {
        $s     = $this->saring();
        $model = new KpiModel();

        $batas   = $this->batas((string) $this->request->getGet('batas'));
        $banding = $this->indikator((string) $this->request->getGet('banding'));

        return view('admin/kpi/index', [
            'title'     => 'KPI Karyawan',
            's'         => $s,
            'kartu'     => $model->kartu($s),
            'target'    => $this->petaTarget($model),
            'tren'      => $model->tren($s),
            'pegawai'   => $model->pegawaiPilihan($s['peran']),
            'juragans'  => $this->juraganPilihan(),
            'periode'   => self::PERIODE,
            'peran'     => self::PERAN,
            'indikator' => KpiModel::INDIKATOR,
            'rekap'     => $model->rekap($s, $batas, $this->halaman() * $batas),
            'batas'     => $batas,
            'halaman'   => $this->halaman(),
            'banding'   => $banding,
            'pembanding' => $model->pembanding($s, $banding),
        ]);
    }

    /**
     * Peringkat karyawan dari poin aktivitas.
     *
     * Hanya menghitung kerja yang meninggalkan jejak beserta pelakunya di
     * database; aturan poinnya ditampilkan lengkap di halaman yang sama.
     *
     * @return string
     */
    public function ranking()
    {
        $s     = $this->saring();
        $model = new KpiModel();

        return view('admin/kpi/ranking', [
            'title'     => 'Ranking Karyawan',
            's'         => $s,
            'peringkat' => $model->peringkat($s),
            'aturan'    => $model->aturanPoin($s),
            'luar'      => KpiModel::TIDAK_TERNILAI,
            'pegawai'   => $model->pegawaiPilihan($s['peran']),
            'juragans'  => $this->juraganPilihan(),
            'periode'   => self::PERIODE,
            'peran'     => self::PERAN,
        ]);
    }

    /**
     * Baris pembentuk satu angka indikator, supaya angkanya bisa diaudit.
     *
     * @return string
     */
    public function detail(string $indikator)
    {
        $s     = $this->saring();
        $model = new KpiModel();

        if (! in_array($indikator, KpiModel::INDIKATOR, true)) {
            throw PageNotFoundException::forPageNotFound();
        }

        $batas   = $this->batas((string) $this->request->getGet('batas'));
        $halaman = $this->halaman();
        $jumlah  = $model->detailJumlah($s, $indikator);

        return view('admin/kpi/detail', [
            'title'     => 'Detail ' . $indikator,
            's'         => $s,
            'indikator' => $indikator,
            'aturan'    => $model->targetSatu($indikator),
            'jumlah'    => $jumlah,
            'baris'     => $model->detail($s, $indikator, $batas, $halaman * $batas),
            'batas'     => $batas,
            'halaman'   => $halaman,
            'pegawai'   => $model->pegawaiPilihan($s['peran']),
            'juragans'  => $this->juraganPilihan(),
            'periode'   => self::PERIODE,
            'peran'     => self::PERAN,
        ]);
    }

    /**
     * Daftar target dan bobot, sekaligus tempat mengubahnya.
     *
     * @return string
     */
    public function target()
    {
        $model = new KpiModel();

        return view('admin/kpi/target', [
            'title'    => 'Target dan Bobot KPI',
            'target'   => $model->targetSemua(),
            'boleh'    => $this->bolehUbah(),
            'kabar'    => (string) session()->getFlashdata('kabar'),
            'pembaru'  => $model->pegawaiPilihan('semua'),
        ]);
    }

    /**
     * Simpan seluruh target sekaligus.
     *
     * Form-nya satu tabel, jadi namanya array per indikator. Kolom yang dipakai
     * orang untuk mengenali indikator (nama, definisi, aturan) tidak ikut ditulis:
     * kalau rumus bisa diubah dari halaman ini, angka bulan lalu tidak bisa
     * dibandingkan lagi.
     *
     * @return \CodeIgniter\HTTP\RedirectResponse
     */
    public function simpanTarget()
    {
        if (! $this->bolehUbah()) {
            return redirect()->to('admin/kpi');
        }

        $model   = new KpiModel();
        $target  = (array) $this->request->getPost('target');
        $bobot   = (array) $this->request->getPost('bobot');
        $aktif   = (array) $this->request->getPost('aktif');
        $known   = array_column($model->targetSemua(), 'indikator');

        $diubah = 0;
        $lewat  = 0;

        foreach ($target as $indikator => $nilai) {
            if (! in_array((string) $indikator, $known, true)) {
                $lewat++;

                continue;
            }

            $angka  = $this->angka($nilai);
            $bobot_angka = $this->angka($bobot[$indikator] ?? null);

            if ($bobot_angka !== null && ($bobot_angka < 0 || $bobot_angka > 100)) {
                $lewat++;

                continue;
            }

            $model->simpanTarget((string) $indikator, [
                'target'  => $angka,
                'bobot'   => $bobot_angka ?? 0,
                'aktif'   => isset($aktif[$indikator]) ? 1 : 0,
            ], pengguna_sesi());

            $diubah++;
        }

        session()->setFlashdata('kabar', $diubah . ' indikator disimpan'
            . ($lewat > 0 ? ', ' . $lewat . ' baris dilewati karena nilainya tidak dikenal di luar daftar.' : '.'));

        return redirect()->to('admin/kpi/target');
    }

    /**
     * Saringan dari query string, sudah dibersihkan.
     *
     * Account dengan level cs tidak punya halaman ini (rutenya auth:admin,superadmin),
     * jadi peran dan pegawai di sini selalu di bawah kendali admin.
     *
     * @return array<string, mixed>
     */
    private function saring(): array
    {
        $awal  = $this->tanggal((string) $this->request->getGet('awal'));
        $akhir = $this->tanggal((string) $this->request->getGet('akhir'));

        if ($awal === '') {
            $awal = date('Y-m-01', strtotime('-5 month'));
        }

        if ($akhir === '') {
            $akhir = date('Y-m-d');
        }

        if ($awal > $akhir) {
            [$awal, $akhir] = [$akhir, $awal];
        }

        $periode = (string) $this->request->getGet('periode');
        $peran   = (string) $this->request->getGet('peran');

        $ids     = $this->juraganIds();
        $juragan = (int) $this->request->getGet('juragan');

        // toko yang tidak dimiliki akun ini dianggap tidak dipilih
        if ($juragan > 0 && in_array($juragan, $ids, true)) {
            $ids = [$juragan];
        } else {
            $juragan = 0;
        }

        $pegawai   = (int) $this->request->getGet('pegawai');
        $dikenal   = array_keys((new KpiModel())->pegawaiPilihan(
            array_key_exists($peran, self::PERAN) ? $peran : 'semua'
        ));

        if ($pegawai > 0 && ! in_array($pegawai, $dikenal, true)) {
            $pegawai = 0;
        }

        return [
            'awal'        => $awal,
            'akhir'       => $akhir,
            'periode'     => array_key_exists($periode, self::PERIODE) ? $periode : 'bulanan',
            'peran'       => array_key_exists($peran, self::PERAN) ? $peran : 'semua',
            'user_id'     => $pegawai > 0 ? $pegawai : null,
            'juragan_id'  => $juragan,
            'juragan_ids' => $ids,
        ];
    }

    /** @return array<string, array<string, mixed>> indikator => baris target */
    private function petaTarget(KpiModel $model): array
    {
        $peta = [];

        foreach ($model->targetSemua() as $t) {
            $peta[$t['indikator']] = $t;
        }

        return $peta;
    }

    /** Indikator yang sedang dipilih untuk grafik perbandingan. */
    private function indikator(string $pilih): string
    {
        return in_array($pilih, KpiModel::INDIKATOR, true) ? $pilih : 'gmv_cs';
    }

    /** Nomor halaman berbasis nol; query string memakai nomor berbasis satu. */
    private function halaman(): int
    {
        return max(1, (int) $this->request->getGet('halaman')) - 1;
    }

    private function batas(string $batas): int
    {
        return in_array((int) $batas, self::HALAMAN, true) ? (int) $batas : self::HALAMAN[0];
    }

    /** Hanya admin dan superadmin; rutenya sudah membatasi, ini pagar kedua. */
    private function bolehUbah(): bool
    {
        return in_array(session()->get('level'), ['admin', 'superadmin'], true);
    }

    private function tanggal(string $nilai): string
    {
        if (! preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $nilai, $m)) {
            return '';
        }

        return checkdate((int) $m[2], (int) $m[3], (int) $m[1]) ? $nilai : '';
    }

    /** Angka desimal dari input orang; selain itu dianggap kosong. */
    private function angka(mixed $nilai): ?float
    {
        $bersih = trim((string) $nilai);

        if ($bersih === '' || ! is_numeric($bersih)) {
            return null;
        }

        return round((float) $bersih, 2);
    }
}
