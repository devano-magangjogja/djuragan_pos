<?php

namespace App\Controllers\Admin\Settings;

use App\Controllers\BaseController;
use App\Models\BankModel;

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

        $getBank = $bankModel->select('tipe_bank, atas_nama, nama_bank, id_bank, rekening')
            ->orderBy('atas_nama ASC, nama_bank ASC')->findAll();

        $data = [
            'title'      => 'Pengaturan Bank & EDC',
            'banks'      => $getBank,
            'validation' => $validation,
        ];

        return view('admin/pengaturan/bank', $data);
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
                ->withInput()->with('validation', $validation);
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
                ->withInput()->with('validation', $validation);
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
}
