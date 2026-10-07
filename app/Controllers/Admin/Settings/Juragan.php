<?php

namespace App\Controllers\Admin\Settings;

use App\Controllers\BaseController;
use App\Controllers\Juragan as Jrgn;
use App\Models\BankModel;
use App\Models\JuraganModel;
use App\Models\RelasiModel;
use App\Models\UserModel;

class Juragan extends BaseController
{
    /**
     * Menampilkan halaman pengaturan Juragan
     *
     * @return string Halaman pengaturan Juragan
     */
    public function index(): string
    {
        $bankModel    = new BankModel();
        $juraganModel = new JuraganModel();

        // daftar akun per level, untuk pemilih penanggung jawab toko
        $calon = Jrgn::get_users();

        $data = [
            'title'     => 'Pengaturan Juragan',
            'sukses'    => session('sukses'),
            'gagal'     => session('gagal'),
            'juragan'   => $juraganModel->orderBy('nama_juragan asc')->findAll(),
            'banks'     => $bankModel->orderBy('atas_nama ASC, nama_bank ASC')->findAll(),
            'pengelola' => $juraganModel->pengelola(),
            'orderan'   => $juraganModel->jumlahOrderan(),
            'admins'    => array_values(array_filter($calon, static fn (array $u): bool => $u['level'] === 'admin')),
            'cs_list'   => array_values(array_filter($calon, static fn (array $u): bool => $u['level'] === 'cs')),
        ];

        return view('admin/pengaturan/juragan', $data);
    }

    public function save_juragan() // new insert
    {
        $validation = \Config\Services::validation();

        if ($this->request->getPost()) {
            $validation->setRuleGroup('addJuragan');
        }

        if (! $validation->withRequest($this->request)->run()) {
            return redirect()->to('/admin/settings/juragan')
                ->withInput()->with('gagal', implode(' ', $validation->getErrors()));
        }

        $db           = \Config\Database::connect();
        $juraganModel = new JuraganModel();
        $relasiModel  = new RelasiModel();
        $nama_juragan = $this->request->getPost('nama_juragan');
        $banks        = $this->request->getPost('bank');

        $juraganModel->save([
            // random_string() sudah tidak mengenal tipe "sha1" sejak CI 4.7; bentuk
            // slug yang sama (40 karakter hex) dibuat langsung dari random_bytes
            'juragan'      => bin2hex(random_bytes(20)),
            'nama_juragan' => $nama_juragan,
        ]);
        $id = $db->insertID();

        // simpan ke tabel relasi (juragan-bank)
        if ($db->affectedRows() > 0) {
            foreach ($banks as $bank) {
                $relasiModel->insert([
                    'table'      => 2, // juragan-bank
                    'juragan_id' => $id,
                    'val_id'     => $bank,
                ]);
            }
        }

        return redirect()->to('/admin/settings/juragan')->with('sukses', 'Juragan ' . $nama_juragan . ' berhasil ditambahkan.');
    }

    /**
     * Proses simpan update Juragan
     *
     * @return \CodeIgniter\HTTP\RedirectResponse
     */
    public function update()
    {
        $validation = \Config\Services::validation();

        if ($this->request->getPost()) {
            $validation->setRuleGroup('editJuragan');
        }

        if (! $validation->withRequest($this->request)->run()) {
            return redirect()->to('/admin/settings/juragan')->with('error', $validation->getErrors());
        }

        $db           = \Config\Database::connect();
        $juraganModel = new JuraganModel();
        $relasiModel  = new RelasiModel();
        $banks        = $this->request->getPost('bank');
        $id_juragan   = $this->request->getPost('id');
        $nama_juragan = $this->request->getPost('nama_juragan');

        $juraganModel->save([
            'id_juragan'   => $id_juragan,
            'nama_juragan' => $nama_juragan,
        ]);

        // hapus semua tabel relasi
        $relasiModel->where(['table' => '2', 'juragan_id' => $id_juragan])->delete();

        // simpan ulang semua data relasi
        if ($db->affectedRows() > -1) {
            foreach ($banks as $bank) {
                $relasiModel->insert([
                    'table'      => 2, // juragan-bank
                    'juragan_id' => $id_juragan,
                    'val_id'     => $bank,
                ]);
            }
        }

        return redirect()->to('/admin/settings/juragan');
    }

    /**
     * Tunjuk penanggung jawab toko: satu Admin dan satu CS untuk satu toko.
     * Akun level lain yang tertaut di toko ini tidak disentuh, dan akun Admin/CS
     * yang digantikan tetap memegang tokonya yang lain.
     *
     * @return \CodeIgniter\HTTP\RedirectResponse
     */
    public function pengelola()
    {
        $juraganModel = new JuraganModel();
        $relasiModel  = new RelasiModel();
        $userModel    = new UserModel();
        $db           = \Config\Database::connect();

        $id_juragan = (int) $this->request->getPost('juragan');
        $admin      = (int) $this->request->getPost('admin');
        $cs         = (int) $this->request->getPost('cs');

        if ($juraganModel->find($id_juragan) === null) {
            return redirect()->to('/admin/settings/juragan')->with('gagal', 'Tokonya tidak ditemukan.');
        }

        // dua-duanya harus akun aktif dengan level yang pas
        foreach (['admin' => $admin, 'cs' => $cs] as $level => $id) {
            if ($id === 0) {
                continue;
            }

            $akun = $userModel->find($id);

            if ($akun === null || $akun->level !== $level || $akun->status !== 'active') {
                return redirect()->to('/admin/settings/juragan')
                    ->with('gagal', 'Penanggung jawab ' . $level . ' harus akun aktif berlevel ' . strtoupper($level) . '.');
            }
        }

        if ($admin > 0 && $admin === $cs) {
            return redirect()->to('/admin/settings/juragan')
                ->with('gagal', 'Satu akun tidak bisa memegang peran Admin dan CS sekaligus.');
        }

        // pemegang sekarang: dipakai untuk tahu siapa yang lepas dan cache siapa yang dibuang
        $sekarang = $juraganModel->pengelola([$id_juragan])[$id_juragan] ?? [];
        $tertaut  = array_merge(
            array_column($sekarang['admin'] ?? [], 'id'),
            array_column($sekarang['cs'] ?? [], 'id')
        );

        $db->transBegin();

        // hanya baris Admin/CS milik toko ini yang ditata ulang
        if ($tertaut !== []) {
            $relasiModel->where(['table' => '1', 'juragan_id' => $id_juragan])->whereIn('val_id', $tertaut)->delete();
        }

        foreach (array_filter([$admin, $cs]) as $id) {
            $relasiModel->insert([
                'table'      => 1, // juragan-user
                'juragan_id' => $id_juragan,
                'val_id'     => $id,
            ]);
        }

        if ($db->transStatus() === false) {
            $db->transRollback();

            return redirect()->to('/admin/settings/juragan')
                ->with('gagal', 'Penanggung jawab toko gagal disimpan.');
        }

        $db->transCommit();

        $this->buangCache(array_unique(array_merge($tertaut, array_filter([$admin, $cs]))));

        return redirect()->to('/admin/settings/juragan')
            ->with('sukses', 'Penanggung jawab toko tersimpan.');
    }

    /**
     * Hapus lunak satu juragan. Relasi bank dan penanggung jawab dibiarkan utuh
     * supaya datanya masih bisa dipulihkan; tokonya hanya hilang dari daftar dan
     * slug-nya tidak lagi membuka nota lama.
     *
     * @return \CodeIgniter\HTTP\RedirectResponse
     */
    public function hapus()
    {
        $juraganModel = new JuraganModel();
        $id           = (int) $this->request->getPost('id_juragan');
        $juragan      = $juraganModel->find($id);

        if ($juragan === null) {
            return redirect()->to('/admin/settings/juragan')->with('gagal', 'Juragan tidak ditemukan.');
        }

        $tertaut = [];

        foreach ($juraganModel->pengelola([$id])[$id] ?? [] as $daftar) {
            $tertaut = array_merge($tertaut, array_column($daftar, 'id'));
        }

        $juraganModel->delete($id);
        $this->buangCache(array_unique(array_map('intval', $tertaut)));

        return redirect()->to('/admin/settings/juragan')
            ->with('sukses', 'Juragan ' . $juragan->nama_juragan . ' berhasil dihapus.');
    }

    /**
     * Daftar juragan tiap akun disimpan 30 menit di writable/cache, jadi setiap
     * perubahan relasi harus membuang kunci akun yang tersentuh.
     *
     * @param array<int> $user_ids
     */
    private function buangCache(array $user_ids): void
    {
        $cache = \Config\Services::cache();

        foreach ($user_ids as $id) {
            $cache->delete('juragan_by_user_' . (int) $id);
            $cache->delete('juragan_by_user_' . (int) $id . '_bank');
        }
    }
}
