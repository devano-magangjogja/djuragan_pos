<?php

namespace App\Controllers;

use App\Models\FeedbackModel;

/**
 * Tombol melayang "Kirim Masukan" milik CS dan Admin.
 *
 * Isinya ditampung di halaman Kotak Masukan (Admin\Settings\Masukan) yang hanya
 * dibuka Superadmin, jadi Superadmin sendiri tidak mengirim masukan: pagar di
 * bawah ini menjaga supaya panggilan langsung ke alamat ini tidak menambah
 * baris dari akun yang akan membaca hasilnya.
 */
class Masukan extends BaseController
{
    public function kirim()
    {
        if ((string) session()->get('level') === 'superadmin') {
            return redirect()->back();
        }

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

        // ciptakan hash 6 karakter untuk id dari waktu sekarang. Kolomnya kunci
        // utama dan save() model menganggap id yang sudah ada sebagai update, jadi
        // dua kirim dalam detik yang sama tidak boleh saling menimpa
        do {
            $id = substr(md5(microtime(true) . session('id')), 0, 6);
        } while ($feedbackModel->find($id) !== null);

        // simpan data
        $feedbackModel->save([
            'id'   => $id,
            'nama' => (string) session('name'),
            'isi'  => trim($this->request->getPost('keterangan')),
        ]);

        return redirect()->back();
    }
}
