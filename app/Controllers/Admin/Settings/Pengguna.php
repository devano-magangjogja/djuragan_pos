<?php

namespace App\Controllers\Admin\Settings;

use App\Controllers\BaseController;
use App\Models\RelasiModel;
use App\Models\UserModel;

class Pengguna extends BaseController
{
    public function pengguna()
    {
        $data = [
            'title' => 'Pengaturan Pengguna',
        ];

        return view('admin/pengaturan/pengguna', $data);
    }

    public function save_pengguna() // new insert
    {
        $validation = \Config\Services::validation();
        $userModel  = new UserModel();

        if ($this->request->getPost()) {
            $validation->setRuleGroup('addPengguna');
        }

        if (! $validation->withRequest($this->request)->run()) {
            $errors = $validation->getErrors();

            var_dump($errors);
        } else {
            $db          = \Config\Database::connect();
            $relasiModel = new RelasiModel();
            $level       = $this->request->getPost('level');

            $userModel->save([
                'username' => strtolower($this->request->getPost('username')),
                'password' => password_hash($this->request->getPost('password'), PASSWORD_BCRYPT),
                'name'     => $this->request->getPost('nama'),
                'email'    => strtolower($this->request->getPost('email')),
                'level'    => $level,
                'status'   => $this->request->getPost('status'),
            ]);
            $id = $db->insertID();

            $juragans = $this->request->getPost('juragan');

            if ($db->affectedRows() > 0) {
                foreach ($juragans as $juragan) {
                    $relasiModel->insert([
                        'table'      => 1, // juragan-user
                        'juragan_id' => $juragan,
                        'val_id'     => $id,
                    ]);
                }
            }
        }

        return redirect()->to('/admin/settings/pengguna');
    }

    public function update_pengguna() // update if exist
    {
        $validation = \Config\Services::validation();
        $userModel  = new UserModel();

        if ($this->request->getPost()) {
            $validation->setRuleGroup('editPengguna');
        }

        if (! $validation->withRequest($this->request)->run()) {
            $errors = $validation->getErrors();

            var_dump($errors);
        } else {
            $db          = \Config\Database::connect();
            $relasiModel = new RelasiModel();
            $juragans    = $this->request->getPost('juragan');
            $id          = $this->request->getPost('id');

            $data     = [];
            $password = $this->request->getPost('password');
            if (! empty($password)) {
                $data['password'] = password_hash($this->request->getPost('password'), PASSWORD_BCRYPT);
            }

            $data['id']     = $this->request->getPost('id');
            $data['name']   = $this->request->getPost('nama');
            $data['email']  = strtolower($this->request->getPost('email'));
            $level          = $this->request->getPost('level');
            $data['level']  = strtolower($level);
            $data['status'] = strtolower($this->request->getPost('status'));

            $userModel->save($data);

            // simpan ulang semua data relasi
            if ($db->affectedRows() > -1) {
                // hapus semua relasi
                $relasiModel->where(['table' => '1', 'val_id' => $id])->delete();

                $juragans = $this->request->getPost('juragan');

                foreach ($juragans as $juragan) {
                    $relasiModel->insert([
                        'table'      => 1, // juragan-user
                        'juragan_id' => $juragan,
                        'val_id'     => $id,
                    ]);
                }
            }
        }

        return redirect()->to('/admin/settings/pengguna');
    }
}
