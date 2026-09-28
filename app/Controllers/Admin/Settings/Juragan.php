<?php

namespace App\Controllers\Admin\Settings;

use App\Controllers\BaseController;
use App\Models\BankModel;
use App\Models\JuraganModel;
use App\Models\RelasiModel;

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

        $data = [
            'title'   => 'Pengaturan Juragan',
            'juragan' => $juraganModel->orderBy('nama_juragan asc')->findAll(),
            'banks'   => $bankModel->orderBy('atas_nama ASC, nama_bank ASC')->findAll(),
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
            $errors = $validation->getErrors();

        // var_dump($errors);
        } else {
            $db           = \Config\Database::connect();
            $juraganModel = new JuraganModel();
            $relasiModel  = new RelasiModel();
            $nama_juragan = $this->request->getPost('nama_juragan');
            $banks        = $this->request->getPost('bank');

            $juraganModel->save([
                'juragan'      => random_string('sha1', 40), // url_title( $nama_juragan, '-', TRUE ),
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
        }

        return redirect()->to('/admin/settings/juragan');
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
}
