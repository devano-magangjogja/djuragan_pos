<?php

namespace App\Controllers;

use App\Models\FeedbackModel;

class Masukan extends BaseController
{
    public function kirim()
    {
        $validation = \Config\Services::validation();

        // validasi data submit
        if ($this->request->getPost()) {
            // make rules validation for signin
            $validation->setRules([
                'keterangan' => ['label' => 'keterangan', 'rules' => 'required'],
            ]);
        }

        if (! $validation->withRequest($this->request)->run()) {
            return redirect()->back();
        }

        $feedbackModel = new FeedbackModel();

        // ciptakan hash 6 karakter untuk id menggunakan waktu sekarang
        $id = substr(md5(time()), 0, 6);

        // simpan data
        $feedbackModel->save([
            'id'   => $id,
            'nama' => session('name'),
            'isi'  => trim($this->request->getPost('keterangan')),
        ]);

        return redirect()->back();
    }
}
