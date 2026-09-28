<?php

namespace App\Controllers;

use App\Models\UserModel;

class Profil extends BaseController
{
    public function sunting()
    {
        $pengguna = $this->pengguna();

        if ($pengguna === null) {
            return redirect()->to('/auth');
        }

        return view('profil_sunting', [
            'title'      => 'Ubah Profil',
            'pengguna'   => $pengguna,
            'validation' => \Config\Services::validation(),
        ]);
    }

    public function simpan()
    {
        $pengguna = $this->pengguna();

        if ($pengguna === null) {
            return redirect()->to('/auth');
        }

        $validation = \Config\Services::validation();
        $validation->setRuleGroup('editProfil');

        if (! $validation->withRequest($this->request)->run()) {
            return $this->gagal('Periksa lagi ya.', $validation->listErrors());
        }

        $data = [
            'id'    => $pengguna->id,
            'name'  => $this->request->getPost('nama'),
            'email' => strtolower($this->request->getPost('email')),
        ];

        $sandiBaru = $this->request->getPost('sandi_baru');

        if (! empty($sandiBaru)) {
            $pesan = $this->perbaruiSandi($pengguna, $sandiBaru, $data);

            if ($pesan !== null) {
                return $this->gagal($pesan);
            }
        }

        (new UserModel())->save($data);

        session()->set([
            'name'  => $data['name'],
            'email' => $data['email'],
        ]);

        return redirect()->to('/user/sunting')
            ->with('status', '<div class="alert alert-success">Profil berhasil diperbarui.</div>');
    }

    /**
     * Memeriksa sandi lama lalu menaruh hash sandi baru di $data.
     *
     * @return string|null pesan error, atau null bila sandi baru siap dipakai
     */
    private function perbaruiSandi(object $pengguna, string $sandiBaru, array &$data)
    {
        $sandiLama = (string) $this->request->getPost('sandi_lama');

        // hash berpanjang 32 berarti masih md5, sisa kasus memakai bcrypt
        $cocok = strlen($pengguna->password) === 32
            ? md5($sandiLama) === $pengguna->password
            : password_verify($sandiLama, $pengguna->password);

        if (! $cocok) {
            return 'Kata sandi lama tidak sesuai.';
        }

        if (strlen($sandiBaru) < 6) {
            return 'Kata sandi baru minimal 6 karakter.';
        }

        if ($sandiBaru !== (string) $this->request->getPost('ulangi_sandi_baru')) {
            return 'Kata sandi baru tidak sama dengan pengulangannya.';
        }

        $data['password'] = password_hash($sandiBaru, PASSWORD_BCRYPT);

        return null;
    }

    private function gagal(string $judul, string $isi = '')
    {
        return redirect()->to('/user/sunting')
            ->withInput()
            ->with('status', '<div class="alert alert-danger"><strong class="d-block">' . $judul . '</strong>' . $isi . '</div>');
    }

    private function pengguna()
    {
        $id = session('id');

        if ($id === null) {
            return null;
        }

        return (new UserModel())->find($id);
    }
}
