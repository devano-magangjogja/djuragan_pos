<?php

namespace App\Controllers\Admin\Settings;

use App\Controllers\BaseController;
use App\Models\JuraganModel;
use App\Models\KunciApiModel;

/**
 * Kelola kunci API untuk pihak luar (khusus Superadmin).
 *
 * Kunci dibuat di sini dan ditempel ke website partner. Pencocokan permintaan
 * tetap lewat hash SHA-256; salinan mentahnya disimpan ter-enkripsi supaya bisa
 * dilihat ulang dari detail kuncinya (teks kabur, klik untuk terlihat) — buat
 * pemilik yang kehilangan catatan kuncinya sendiri.
 */
class KunciApi extends BaseController
{
    public function index()
    {
        $model = new KunciApiModel();

        // token mentah untuk baris "lihat kunci"; kunci lama mengembalikan null
        // dan layarnya menawarkan membuat kunci baru
        $kunci = $model->daftar();

        foreach ($kunci as $k) {
            $k->token_asli = KunciApiModel::tokenAsli($k);
        }

        // angka bersih-bersih dihitung dari daftar yang sudah dijinjing saja,
        // tidak perlu query kedua: kunci tanpa terakhir_pakai = belum sekali pun dipakai
        $tak_terpakai = count(array_filter($kunci, static fn (object $k): bool => $k->terakhir_pakai === null));

        $data = [
            'title'        => 'Kunci API',
            'kunci'        => $kunci,
            'tak_terpakai' => $tak_terpakai,
            'toko'         => (new JuraganModel())->where('deleted_at', null)->orderBy('nama_juragan', 'ASC')->findAll(),
            'hak'          => KunciApiModel::HAK,
            'sukses'       => session('sukses'),
            'gagal'        => session('gagal'),
            'token'        => session('token_baru'),
            'errors'       => session('errors') ?: [],
        ];

        return view('admin/pengaturan/kunci_api', $data);
    }

    /** Kunci baru: token mentahnya ditampilkan sekali di sini dan tersimpan ter-enkripsi untuk dilihat ulang. */
    public function simpan()
    {
        $validation = \Config\Services::validation();
        $validation->setRuleGroup('kunciApi');

        if (! $validation->withRequest($this->request)->run()) {
            return redirect()->to('/admin/settings/kunci-api')
                ->withInput()->with('errors', $validation->getErrors());
        }

        $siap = KunciApiModel::siapan($this->request->getPost());

        if ($siap['hak'] === '' || $siap['juragan'] === '') {
            return redirect()->to('/admin/settings/kunci-api')
                ->withInput()->with('gagal', 'Pilih minimal satu hak akses dan satu toko.');
        }

        $model = new KunciApiModel();
        $buat  = KunciApiModel::baru();

        $model->insert([
            'nama'         => trim((string) $this->request->getPost('nama')),
            'kunci'        => $buat['hash'],
            'token_enkrip' => $buat['enkrip'],
            'hak'          => $siap['hak'],
            'juragan'      => $siap['juragan'],
            'batas_harian' => $siap['batas_harian'],
            'hidup'        => 1,
        ]);

        return redirect()->to('/admin/settings/kunci-api')
            ->with('sukses', 'Kunci API dibuat. Nilainya juga bisa dilihat lagi kapan saja dari detail kuncinya.')
            ->with('token_baru', $buat['kunci']);
    }

    /** Sunting nama, hak, toko, batas, dan hidup/cabut — tidak pernah kuncinya. */
    public function sunting($id_kunci = 0)
    {
        $model = new KunciApiModel();
        $baris = $model->find((int) $id_kunci);

        if ($baris === null) {
            return redirect()->to('/admin/settings/kunci-api')->with('gagal', 'Kunci tidak ditemukan.');
        }

        $validation = \Config\Services::validation();
        $validation->setRuleGroup('kunciApi');

        if (! $validation->withRequest($this->request)->run()) {
            return redirect()->to('/admin/settings/kunci-api')
                ->withInput()->with('errors', $validation->getErrors());
        }

        $siap = KunciApiModel::siapan($this->request->getPost());

        if ($siap['hak'] === '' || $siap['juragan'] === '') {
            return redirect()->to('/admin/settings/kunci-api')
                ->withInput()->with('gagal', 'Pilih minimal satu hak akses dan satu toko.');
        }

        $model->update((int) $id_kunci, [
            'nama'         => trim((string) $this->request->getPost('nama')),
            'hak'          => $siap['hak'],
            'juragan'      => $siap['juragan'],
            'batas_harian' => $siap['batas_harian'],
            'hidup'        => (int) $this->request->getPost('hidup') === 1 ? 1 : 0,
        ]);

        return redirect()->to('/admin/settings/kunci-api')->with('sukses', 'Kunci API diperbarui.');
    }

    /**
     * Cabut. Barisnya dibiarkan ada (hidup = 0) supaya terlihat pernah dipakai;
     * yang membuang barisnya dari daftar ada di hapus(), dan itupun hapus lunak.
     */
    public function cabut()
    {
        $model = new KunciApiModel();
        $id    = (int) $this->request->getPost('id_kunci');

        if ($model->find($id) === null) {
            return redirect()->to('/admin/settings/kunci-api')->with('gagal', 'Kunci tidak ditemukan.');
        }

        $model->cabut($id);

        return redirect()->to('/admin/settings/kunci-api')->with('sukses', 'Kunci dicabut, pemanggilnya langsung kena 401.');
    }

    /**
     * Hapus satu kunci dari daftar. Yang masih aktif dan terbukti dipakai boleh
     * ikut terhapus — konfirmasinya di layar yang menyebut risikonya, karena yang
     * punya kunci memang kadang lupa memberitahu situs partner sebelum berhenti
     * memakainya.
     */
    public function hapus($id_kunci = 0)
    {
        $model = new KunciApiModel();
        $id    = (int) ($id_kunci !== 0 ? $id_kunci : $this->request->getPost('id_kunci'));
        $baris = $id > 0 ? $model->find($id) : null;

        if ($baris === null) {
            return redirect()->to('/admin/settings/kunci-api')->with('gagal', 'Kunci tidak ditemukan.');
        }

        $model->arsipkan($id);

        return redirect()->to('/admin/settings/kunci-api')
            ->with('sukses', 'Kunci "' . $baris->nama . '" dihapus dari daftar.');
    }

    /** Bersih-bersih sekali klik: semua kunci yang belum pernah dipakai terarsipkan. */
    public function bersihkan()
    {
        $model = new KunciApiModel();
        $calon = $model->belumDipakai();

        if ($calon === []) {
            return redirect()->to('/admin/settings/kunci-api')
                ->with('gagal', 'Tidak ada kunci yang belum pernah dipakai.');
        }

        $jumlah = $model->arsipkan(...array_map(static fn (object $k): int => (int) $k->id_kunci, $calon));

        return redirect()->to('/admin/settings/kunci-api')->with(
            'sukses',
            $jumlah . ' kunci yang belum pernah dipakai dihapus dari daftar; yang pernah dipakai dibiarkan.'
        );
    }
}
