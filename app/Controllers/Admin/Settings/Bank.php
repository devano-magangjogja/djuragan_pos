<?php

namespace App\Controllers\Admin\Settings;

use App\Controllers\BaseController;
use App\Models\BankModel;
use App\Models\PembayaranModel;
use App\Models\RelasiModel;

class Bank extends BaseController
{
    /**
     * Halaman pengaturan untuk bank/edc
     *
     * @return string
     */
    public function index()
    {
        $validation = \Config\Services::validation();
        $bankModel  = new BankModel();

        // pesan validasi datang dari permintaan sebelumnya, jadi dipasang ulang
        // supaya modalnya terbuka lagi dengan isian yang salah ditandai
        foreach ((array) session('validation') as $bidang => $pesan) {
            $validation->setError($bidang, (string) $pesan);
        }

        $getBank = $bankModel->select('tipe_bank, atas_nama, nama_bank, id_bank, rekening')
            ->orderBy('atas_nama ASC, nama_bank ASC')->findAll();

        $data = [
            'title'      => 'Pengaturan Bank & EDC',
            'banks'      => $getBank,
            'pemakaian'  => $this->pemakaian(),
            'validation' => $validation,
            'sukses'     => session('sukses'),
            'gagal'      => session('gagal'),
        ];

        return view('admin/pengaturan/bank', $data);
    }

    /**
     * Jumlah catatan pembayaran per rekening, dipakai untuk melengkapi pesan
     * konfirmasi hapus
     */
    private function pemakaian(): array
    {
        $bayar = (new PembayaranModel())->select('sumber_dana, COUNT(*) as jumlah')
            ->groupBy('sumber_dana')->findAll();

        return array_column($bayar, 'jumlah', 'sumber_dana');
    }

    /**
     * Proses simpan bank/edc
     *
     * @return \CodeIgniter\HTTP\RedirectResponse
     */
    public function simpan()
    {
        $validation = \Config\Services::validation();

        if ($this->request->getPost()) {
            $validation->setRuleGroup('addBank');
        }

        if (! $validation->withRequest($this->request)->run()) {
            return redirect()->to('/admin/settings/bank')
                ->withInput()->with('validation', $validation->getErrors());
        }

        $bankModel = new BankModel();
        $tipe      = $this->request->getPost('nama_bank');
        $tipe_bank = null;
        if (in_array($tipe, ['bca', 'bni', 'mandiri', 'bri'], true)) {
            $tipe_bank = '1';
        } elseif (in_array($tipe, ['edc'], true)) {
            $tipe_bank = '2';
        }
        $bankModel->insert([
            'nama_bank' => $tipe,
            'tipe_bank' => $tipe_bank,
            'rekening'  => $this->request->getPost('nomor_rekening'),
            'atas_nama' => $this->request->getPost('atas_nama'),
        ]);

        return redirect()->to('/admin/settings/bank');
    }

    /**
     * Update bank/edc
     */
    public function perbarui()
    {
        $validation = \Config\Services::validation();

        if ($this->request->getPost()) {
            $validation->setRuleGroup('updateBank');
        }

        if (! $validation->withRequest($this->request)->run()) {
            return redirect()->to('/admin/settings/bank')
                ->withInput()->with('validation', $validation->getErrors());
        }

        $bankModel = new BankModel();
        $id_bank   = $this->request->getPost('id_bank');
        $tipe      = $this->request->getPost('sunting_nama_bank');
        $tipe_bank = null;
        if (in_array($tipe, ['bca', 'bni', 'mandiri', 'bri'], true)) {
            $tipe_bank = '1';
        } elseif (in_array($tipe, ['edc'], true)) {
            $tipe_bank = '2';
        }
        $bankModel->update($id_bank, [
            'nama_bank' => $tipe,
            'tipe_bank' => $tipe_bank,
            'rekening'  => $this->request->getPost('sunting_nomor_rekening'),
            'atas_nama' => $this->request->getPost('sunting_atas_nama'),
        ]);

        return redirect()->to('/admin/settings/bank');
    }

    /**
     * Hapus lunak rekening. Barisnya tetap ada di tabel bank, jadi nota lama
     * masih menampilkan sumber dananya; yang hilang hanya pilihan rekeningnya.
     */
    public function hapus()
    {
        $bankModel = new BankModel();
        $id        = (int) $this->request->getPost('id_bank');
        $bank      = $bankModel->find($id);

        if ($bank === null) {
            return redirect()->to('/admin/settings/bank')->with('gagal', 'Rekening tidak ditemukan.');
        }

        $dipakai = (new PembayaranModel())->where('sumber_dana', $id)->countAllResults();

        // tautan ke juragan ikut dilepas: daftar rekening juragan dibangun dengan
        // query mentah yang tidak menyaring deleted_at, jadi rekening lama akan
        // tetap muncul kalau relasinya dibiarkan
        (new RelasiModel())->where('val_id', $id)->where('table', 2)->delete();

        $bankModel->delete($id);

        $pesan = 'Rekening ' . $bank->rekening . ' berhasil dihapus.';

        if ($dipakai > 0) {
            $pesan .= ' ' . $dipakai . ' catatan pembayaran tetap terbaca, hanya pilihannya yang hilang.';
        }

        return redirect()->to('/admin/settings/bank')->with('sukses', $pesan);
    }
}
