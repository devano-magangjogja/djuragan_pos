<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\StokModel;

/**
 * Pantau stok dan tambah barang per juragan.
 */
class Produk extends BaseController
{
    private const KEADAAN = ['', 'ada', 'menipis', 'habis'];

    /**
     * Daftar stok. Query string: juragan, cari, keadaan, page.
     *
     * @return string
     */
    public function index()
    {
        $ids       = $this->juraganIds();
        $stokModel = new StokModel();

        $juragan = (int) $this->request->getGet('juragan');
        // juragan yang tidak dimiliki pengguna dianggap tidak ada
        if ($juragan > 0 && ! in_array($juragan, $ids, true)) {
            $juragan = 0;
        }

        $cari = trim((string) $this->request->getGet('cari'));

        $keadaan = (string) $this->request->getGet('keadaan');
        if (! in_array($keadaan, self::KEADAAN, true)) {
            $keadaan = '';
        }

        $limit = config('Pager')->perPage;
        $page  = max(1, (int) $this->request->getGet('page'));
        $offset = ($page - 1) * $limit;

        $total = $stokModel->jumlah($ids, $juragan, $cari, $keadaan);

        $data = [
            'title'     => 'Produk & Stok',
            'stok'      => $stokModel->daftar($ids, $juragan, $cari, $keadaan, $limit, $offset),
            'ringkas'   => $stokModel->ringkas($ids),
            'juragan'   => $juragan,
            'juragans'  => $this->juraganPilihan(),
            'cari'      => $cari,
            'keadaan'   => $keadaan,
            'ukuran'    => $stokModel->daftarUkuran(),
            'kode'      => $stokModel->daftarKode(),
            'total'     => $total,
            'limit'     => $limit,
            'page'      => $page,
            'sukses'    => session('sukses'),
            'gagal'     => session('gagal'),
            'errors'    => session('errors') ?: [],
            'ambangnya' => StokModel::AMBANG_MENIPIS,
        ];

        return view('admin/produk', $data);
    }

    /**
     * Simpan varian stok baru.
     *
     * @return \CodeIgniter\HTTP\RedirectResponse
     */
    public function simpan()
    {
        $validation = \Config\Services::validation();
        $validation->setRuleGroup('addStok');

        if (! $validation->withRequest($this->request)->run()) {
            return redirect()->to($this->alamatKembali())
                ->withInput()->with('errors', $validation->getErrors());
        }

        $juragan_id = (int) $this->request->getPost('juragan_id');
        $kode       = $this->kodePost();
        $ukuran     = $this->ukuranPost();

        if (! $this->bolehJuragan($juragan_id)) {
            return redirect()->to($this->alamatKembali())->with('gagal', 'Juragan ini tidak bisa diakses.');
        }

        $stokModel = new StokModel();

        if ($stokModel->kembar(0, $juragan_id, $kode, $ukuran)) {
            return redirect()->to($this->alamatKembali())
                ->withInput()->with('gagal', 'Varian ' . $kode . ' ' . $ukuran . ' sudah terdaftar.');
        }

        $stokModel->insert([
            'juragan_id' => $juragan_id,
            'produk_id'  => jamin_produk($kode),
            'kode'       => $kode,
            'ukuran'     => $ukuran,
            'harga'      => (int) $this->request->getPost('harga'),
            'stok'       => (int) $this->request->getPost('stok'),
            'keterangan' => $this->keteranganPost(),
        ]);

        return redirect()->to($this->alamatKembali())->with('sukses', 'Barang berhasil ditambahkan.');
    }

    /**
     * Perbarui harga, stok, atau keterangan satu varian.
     *
     * @return \CodeIgniter\HTTP\RedirectResponse
     */
    public function perbarui()
    {
        $validation = \Config\Services::validation();
        $validation->setRuleGroup('updateStok');

        if (! $validation->withRequest($this->request)->run()) {
            return redirect()->to($this->alamatKembali())
                ->withInput()->with('errors', $validation->getErrors());
        }

        $stokModel = new StokModel();
        $id        = (int) $this->request->getPost('id_stok');
        $baris     = $stokModel->find($id);

        if ($baris === null || ! $this->bolehJuragan((int) $baris->juragan_id)) {
            return redirect()->to($this->alamatKembali())->with('gagal', 'Barang tidak ditemukan.');
        }

        $kode   = $this->kodePost();
        $ukuran = $this->ukuranPost();

        if ($stokModel->kembar($id, (int) $baris->juragan_id, $kode, $ukuran)) {
            return redirect()->to($this->alamatKembali())
                ->with('gagal', 'Varian ' . $kode . ' ' . $ukuran . ' sudah terdaftar.');
        }

        $stokModel->update($id, [
            'produk_id'  => jamin_produk($kode),
            'kode'       => $kode,
            'ukuran'     => $ukuran,
            'harga'      => (int) $this->request->getPost('harga'),
            'stok'       => (int) $this->request->getPost('stok'),
            'keterangan' => $this->keteranganPost(),
        ]);

        return redirect()->to($this->alamatKembali())->with('sukses', 'Barang berhasil diperbarui.');
    }

    /**
     * Hapus lunak satu varian. Transaksi lama tetap utuh.
     *
     * @return \CodeIgniter\HTTP\RedirectResponse
     */
    public function hapus()
    {
        $stokModel = new StokModel();
        $id        = (int) $this->request->getPost('id_stok');
        $baris     = $stokModel->find($id);

        if ($baris === null || ! $this->bolehJuragan((int) $baris->juragan_id)) {
            return redirect()->to($this->alamatKembali())->with('gagal', 'Barang tidak ditemukan.');
        }

        $stokModel->delete($id);

        return redirect()->to($this->alamatKembali())->with('sukses', 'Barang berhasil dihapus.');
    }

    /** Nama varian tidak boleh punya spasi ganda; huruf besar dibiarkan seperti ditulis. */
    private function kodePost(): string
    {
        return mb_substr(trim((string) preg_replace('/\s+/', ' ', (string) $this->request->getPost('kode'))), 0, 20);
    }

    private function ukuranPost(): string
    {
        return mb_substr(trim((string) $this->request->getPost('ukuran')), 0, 6);
    }

    private function keteranganPost(): ?string
    {
        $keterangan = trim((string) $this->request->getPost('keterangan'));

        return $keterangan === '' ? null : mb_substr($keterangan, 0, 120);
    }

    private function bolehJuragan(int $juragan_id): bool
    {
        return $juragan_id > 0 && in_array($juragan_id, $this->juraganIds(), true);
    }

    /**
     * Kembali ke daftar dengan filter yang sedang dipakai, supaya pesan sukses
     * atau kesalahan tidak muncul di halaman kosong.
     */
    private function alamatKembali(): string
    {
        $kueri = array_filter([
            'juragan' => (int) $this->request->getPost('kembali_juragan'),
            'keadaan' => (string) $this->request->getPost('kembali_keadaan'),
            'cari'    => (string) $this->request->getPost('kembali_cari'),
        ], static fn ($v) => $v !== '' && $v !== 0);

        return site_url('admin/produk' . ($kueri === [] ? '' : '?' . http_build_query($kueri)));
    }
}
