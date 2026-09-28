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
            ];
        }

        return $this->respond($json);
    }
}
