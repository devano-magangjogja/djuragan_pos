<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Controllers\Juragan;
use App\Models\JuraganModel;
use App\Models\LabelAsal;
use App\Models\TokoOnlineModel;
use CodeIgniter\Exceptions\PageNotFoundException;

/**
 * Toko online, CS yang memegangnya, dan sejauh mana CS sudah mengenalnya.
 *
 * Toko tidak dibuat di sini — order_juragan tetap satu-satunya daftar toko.
 * Yang ditambah di sini hanyalah sisi operasionalnya: toko ini jualan di platform
 * apa, url-nya apa, siapa CS-nya, dan apakah CS baru sudah paham karakter tokonya.
 *
 * Status pengenalan sengaja BUKAN hak akses. CS yang boleh membuka sebuah toko
 * tidak otomatis "sudah paham" tokonya, dan yang belum paham tetap boleh bekerja.
 */
class Toko extends BaseController
{
    private const BATAS = [10, 25, 50];

    public function index()
    {
        helper('fungsi');

        $model = new TokoOnlineModel();
        $s     = $this->saring();
        $batas = $this->batas();
        $page  = max(1, (int) $this->request->getGet('halaman'));
        $total = $model->jumlah($s);
        $page  = max(1, min($page, max(1, (int) ceil($total / $batas))));

        return view('admin/toko/index', [
            'title'    => 'Toko Online',
            's'        => $s,
            'baris'    => $model->daftar($s, $batas, ($page - 1) * $batas),
            'total'    => $total,
            'page'     => $page,
            'batas'    => $batas,
            'toko'     => $this->tokoId(),
            'platform' => $this->platform(),
            'kelola'   => $this->bolehKelola(),
            'kabar'    => (string) session()->getFlashdata('sukses'),
            'galat'    => (string) session()->getFlashdata('galat'),
        ]);
    }

    /** Layar CS: daftar toko yang ia pegang + kolom status pengenalan miliknya. */
    public function pengenalan()
    {
        helper('fungsi');

        $model = new TokoOnlineModel();

        return view('admin/toko/pengenalan', [
            'title'   => 'Pengenalan Toko',
            'toko'    => $model->milikUser((int) session()->get('id'), $this->tokoBoleh()),
            'status'  => TokoOnlineModel::PENGENALAN,
            'kabar'   => (string) session()->getFlashdata('sukses'),
            'galat'   => (string) session()->getFlashdata('galat'),
        ]);
    }

    /** Simpan atau ubah satu toko online (Admin & Superadmin). */
    public function simpan()
    {
        helper('fungsi');

        $id      = (int) $this->request->getPost('id_toko');
        $juragan = (int) $this->request->getPost('juragan_id');
        $nama    = trim((string) $this->request->getPost('nama'));
        $url     = trim((string) $this->request->getPost('url'));

        if ($nama === '') {
            return redirect()->back()->with('galat', 'Nama toko diisi dulu.')->withInput();
        }

        if ($juragan < 1 || ! $this->bolehToko($juragan)) {
            return redirect()->back()->with('galat', 'Pilih juragan yang benar — toko itu bukan milikmu.')->withInput();
        }

        if ($url !== '' && ! filter_var($url, FILTER_VALIDATE_URL)) {
            return redirect()->back()->with('galat', 'URL toko tidak terbaca sebagai alamat web.')->withInput();
        }

        $model = new TokoOnlineModel();

        if ($id > 0 && $model->satu($id) === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        // kunci unik (juragan_id, platform) menjaga satu toko tidak terdaftar dua
        // kali di platform yang sama; ditolak di sini supaya pesannya manusiawi
        $platform = trim((string) $this->request->getPost('platform'));
        $kembar   = (new TokoOnlineModel())->kembar($juragan, $platform, $id);

        if ($kembar !== null) {
            return redirect()->back()->with('galat', 'Toko itu sudah terdaftar untuk platform tersebut di juragan yang sama.')->withInput();
        }

        $model->simpan([
            'juragan_id' => $juragan,
            'platform'   => $platform,
            'nama'       => $nama,
            'url'        => $url,
            'aktif'      => (int) $this->request->getPost('aktif'),
            'catatan'    => (string) $this->request->getPost('catatan'),
        ], (int) session()->get('id'), $id);

        return redirect()->to(site_url('admin/toko'))->with('sukses', $id > 0 ? 'Toko diperbarui.' : 'Toko ditambahkan.');
    }

    /** Nonaktifkan/nyalakan lagi. Barisnya tidak pernah dihapus. */
    public function aktif()
    {
        $model = new TokoOnlineModel();
        $toko  = $this->toko((int) $this->request->getPost('id_toko'));

        $model->setAktif((int) $toko['id_toko'], (string) $this->request->getPost('aktif') === '1');

        return redirect()->to(site_url('admin/toko'))->with('sukses', 'Status toko diperbarui.');
    }

    /** Panel CS sebuah toko (Admin & Superadmin). */
    public function cs(int $id)
    {
        helper('fungsi');

        $model  = new TokoOnlineModel();
        $toko   = $this->toko($id);
        $punya  = array_column($model->akses($id), 'user_id');

        $staf = [];
        foreach (Juragan::get_users() as $u) {
            if ($u['level'] !== 'cs') {
                continue;
            }

            $staf[(int) $u['id']] = $u['nama'];
        }

        return view('admin/toko/cs', [
            'title'   => 'CS Toko Online',
            'toko'    => $toko,
            'daftar'  => $model->akses($id),
            'staf'    => array_diff_key($staf, array_flip(array_map('intval', $punya))),
            'status'  => TokoOnlineModel::PENGENALAN,
            'kelola'  => $this->bolehKelola(),
            'kabar'   => (string) session()->getFlashdata('sukses'),
            'galat'   => (string) session()->getFlashdata('galat'),
        ]);
    }

    public function tambahCs()
    {
        helper('fungsi');

        $model = new TokoOnlineModel();
        $toko  = $this->toko((int) $this->request->getPost('id_toko'));
        $user  = (int) $this->request->getPost('user_id');

        if (! array_key_exists($user, $this->stafCs())) {
            return redirect()->back()->with('galat', 'Akun itu tidak dikenal atau bukan CS aktif.');
        }

        $model->beriAkses((int) $toko['id_toko'], $user, (int) session()->get('id'));

        return redirect()->to(site_url('admin/toko/cs/' . (int) $toko['id_toko']))->with('sukses', 'CS ditautkan ke toko ini.');
    }

    /** @return array<int, string> id => nama, khusus CS aktif */
    private function stafCs(): array
    {
        $staf = [];

        foreach (Juragan::get_users() as $u) {
            if ($u['level'] === 'cs') {
                $staf[(int) $u['id']] = $u['nama'];
            }
        }

        return $staf;
    }

    public function copotCs()
    {
        $model = new TokoOnlineModel();
        $toko  = $this->toko((int) $this->request->getPost('id_toko'));

        $model->copotAkses((int) $toko['id_toko'], (int) $this->request->getPost('user_id'));

        return redirect()->to(site_url('admin/toko/cs/' . (int) $toko['id_toko']))->with('sukses', 'Tautan CS dilepas (sejarahnya di tugas lama tidak berubah).');
    }

    /** Admin/Superadmin mengubah status pengenalan seorang CS. */
    public function statusOrang()
    {
        helper('fungsi');

        $model = new TokoOnlineModel();
        $toko  = $this->toko((int) $this->request->getPost('id_toko'));

        $ok = $model->ubahPengenalan(
            (int) $toko['id_toko'],
            (int) $this->request->getPost('user_id'),
            (string) $this->request->getPost('pengenalan'),
            (string) $this->request->getPost('catatan'),
            (string) $this->request->getPost('materi'),
            (int) session()->get('id')
        );

        return redirect()->to(site_url('admin/toko/cs/' . (int) $toko['id_toko']))
            ->with($ok ? 'sukses' : 'galat', $ok ? 'Status pengenalan disimpan.' : 'Status tidak dikenal.');
    }

    /** CS mengisi status pengenalannya sendiri untuk satu toko. */
    public function statusSendiri()
    {
        helper('fungsi');

        $model = new TokoOnlineModel();
        $toko  = $this->toko((int) $this->request->getPost('id_toko'));
        $user  = (int) session()->get('id');

        if (! $model->aksesPunyaUser((int) $toko['id_toko'], $user)
            && ! in_array((int) $toko['juragan_id'], $this->tokoBoleh(), true)) {
            throw PageNotFoundException::forPageNotFound();
        }

        $ok = $model->ubahPengenalan(
            (int) $toko['id_toko'],
            $user,
            (string) $this->request->getPost('pengenalan'),
            (string) $this->request->getPost('catatan'),
            (string) $this->request->getPost('materi')
        );

        return redirect()->to(site_url('admin/toko/pengenalan'))
            ->with($ok ? 'sukses' : 'galat', $ok ? 'Status pengenalan tersimpan.' : 'Status tidak dikenal.');
    }

    // ------------------------------------------------------------------ dalam

    /** @return array<string, mixed> */
    private function toko(int $id): array
    {
        $toko = (new TokoOnlineModel())->satu($id);

        if ($toko === null || ! $this->bolehToko((int) $toko['juragan_id'])) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $toko;
    }

    private function bolehKelola(): bool
    {
        return in_array((string) session()->get('level'), ['admin', 'superadmin'], true);
    }

    /** Daftar marketplace dari asal_order — platform toko tidak diketik bebas. */
    private function platform(): array
    {
        $platform = [];

        foreach ((new LabelAsal())->where('status', 1)->orderBy('label', 'ASC')->findAll() as $a) {
            $platform[(string) $a->label] = (string) $a->label;
        }

        return $platform;
    }

    /** @return array<int, string> id_toko => nama */
    private function tokoId(): array
    {
        $ids = $this->tokoBoleh();

        if ($ids === []) {
            return [];
        }

        $peta = [];

        foreach ((new JuraganModel())->whereIn('id_juragan', $ids)->orderBy('nama_juragan', 'ASC')->findAll() as $t) {
            $peta[(int) $t->id_juragan] = $t->nama_juragan;
        }

        return $peta;
    }

    /** @return array<string, mixed> */
    private function saring(): array
    {
        $juragan = (int) $this->request->getGet('juragan');

        return [
            'cari'        => mb_substr(trim((string) $this->request->getGet('cari')), 0, 80),
            'platform'    => mb_substr(trim((string) $this->request->getGet('platform')), 0, 40),
            'aktif'       => (string) $this->request->getGet('aktif'),
            'juragan_id'  => $juragan > 0 && $this->bolehToko($juragan) ? $juragan : 0,
            'juragan_ids' => $this->bolehKelola() ? [] : $this->tokoBoleh(),
        ];
    }

    private function batas(): int
    {
        $n = (int) $this->request->getGet('batas');

        return in_array($n, self::BATAS, true) ? $n : 25;
    }
}
