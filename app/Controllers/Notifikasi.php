<?php

namespace App\Controllers;

use CodeIgniter\Controller;

class Notifikasi extends Controller
{
    public function hapus()
    {
        $notifikasi = new \App\Models\NotifikasiModel();

        $builder = $notifikasi->builder();
        $builder->where('read_at !=', null);
        $counting = $builder->countAllResults();

        $notifikasi->where('read_at !=', null)->delete();

        return $this->response->setJSON(['status' => 200, 'message' => $counting . ' notifikasi sudah dihapus']);
    }

    // --
}
