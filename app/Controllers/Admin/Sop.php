<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\SopModel;
use CodeIgniter\Exceptions\PageNotFoundException;

/**
 * Pustaka SOP.
 *
 * CS membaca aturannya, Admin menulisnya. Dokumen yang sudah tidak dipakai tidak
 * pernah dihapus — hanya dimatikan, supaya tugas lama yang menyebut sebuah SOP
 * tetap bisa ditelusuri orangnya.
 */
class Sop extends BaseController
{
    public function index()
    {
        helper('fungsi');

        $model  = new SopModel();
        $kelola = $this->bolehKelola();
        $cari   = trim((string) $this->request->getGet('cari'));

        $baris = $model->daftar([
            'aktif'    => $kelola ? '' : 1,
            'untuk'    => $kelola ? '' : ((string) session()->get('level') === 'cs' ? 'cs' : 'admin'),
            'kategori' => (string) $this->request->getGet('kategori'),
            'cari'     => $cari,
        ]);

        return view('admin/sop/index', [
            'title'    => 'SOP & Panduan',
            'baris'    => $baris,
            'cari'     => $cari,
            'kategori' => (string) $this->request->getGet('kategori'),
            'daftar_kategori' => $model->kategori(),
            'untuk'    => SopModel::UNTUK,
            'kelola'   => $kelola,
            'kabar'    => (string) session()->getFlashdata('sukses'),
            'galat'    => (string) session()->getFlashdata('galat'),
        ]);
    }

    public function lihat(int $id)
    {
        helper('fungsi');

        $model  = new SopModel();
        $sop    = $model->satu($id);
        $kelola = $this->bolehKelola();

        if ($sop === null || (! $kelola && ((int) $sop['aktif'] !== 1 || ! $this->untukPeran((string) $sop['untuk'])))) {
            throw PageNotFoundException::forPageNotFound();
        }

        return view('admin/sop/lihat', [
            'title'  => $sop['judul'],
            'sop'    => $sop,
            'untuk'  => SopModel::UNTUK,
            'kelola' => $kelola,
            'kabar'  => (string) session()->getFlashdata('sukses'),
            'galat'  => (string) session()->getFlashdata('galat'),
        ]);
    }

    /** Tambah atau sunting dokumen (Admin & Superadmin). */
    public function simpan()
    {
        helper('fungsi');

        $model = new SopModel();
        $judul = trim((string) $this->request->getPost('judul'));
        $id    = (int) $this->request->getPost('id_sop');

        if ($judul === '') {
            return redirect()->back()->with('galat', 'Tulisi dulu judul SOP-nya.')->withInput();
        }

        if ($id > 0 && $model->satu($id) === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        $tersimpan = $model->simpan([
            'judul'    => $judul,
            'isi'      => (string) $this->request->getPost('isi'),
            'kategori' => (string) $this->request->getPost('kategori'),
            'untuk'    => (string) $this->request->getPost('untuk'),
            'urut'     => (int) $this->request->getPost('urut'),
            'aktif'    => (int) $this->request->getPost('aktif'),
        ], (int) session()->get('id'), $id);

        return redirect()->to(site_url('admin/sop/' . $tersimpan))->with('sukses', 'SOP disimpan.');
    }

    public function matikan()
    {
        $model = new SopModel();
        $id    = (int) $this->request->getPost('id_sop');

        if ($model->satu($id) === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        $model->setAktif($id, (string) $this->request->getPost('aktif') === '1');

        return redirect()->to(site_url('admin/sop'))->with('sukses', 'Status SOP diperbarui.');
    }

    // ------------------------------------------------------------------ dalam

    private function bolehKelola(): bool
    {
        return in_array((string) session()->get('level'), ['admin', 'superadmin'], true);
    }

    /** CS hanya boleh membaca dokumen yang ditujukan untuk semua peran atau untuk CS. */
    private function untukPeran(string $untuk): bool
    {
        $level = (string) session()->get('level');

        return $untuk === 'semua' || $untuk === $level;
    }
}
