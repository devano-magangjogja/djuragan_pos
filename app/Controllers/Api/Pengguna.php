<?php

namespace App\Controllers\Api;

use CodeIgniter\API\ResponseTrait;

// use App\Controllers\Juragan as Jrgn;

class Pengguna extends \CodeIgniter\Controller
{
    use ResponseTrait;

    public function all()
    {
        $user = new \App\Models\UserModel();
        // $cache = \Config\Services::cache();

        $gets = $user->orderBy('status DESC')->findAll();

        $json = [];

        foreach ($gets as $user) {
            $json[] = [
                'id'             => (int) $user->id,
                'username'       => $user->username,
                'nama'           => $user->name,
                'email'          => $user->email,
                'level'          => $user->level,
                'status'         => $user->status,
                'login_terakhir' => $user->login_terakhir,
                // dipakai popup sunting untuk mencentang hak baca katalog semua juragan
                'lihat_semua_produk' => (int) $user->lihat_semua_produk,
            ];
        }

        return $this->respond($json);
    }
}
