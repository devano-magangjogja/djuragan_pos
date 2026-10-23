<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\FeedbackModel;

class Settings extends BaseController
{
    /**
     * Halaman dasbor pengaturan
     *
     * @return string
     */
    public function index()
    {
        $data = [
            'title' => 'Pengaturan',
            // angka kecil di tombol Kotak Masukan: berapa laporan CS dan Admin
            // yang masih menunggu dibaca
            'jumlah_masukan' => (new FeedbackModel())->jumlah(),
        ];

        return view('admin/pengaturan/dasbor', $data);
    }
}
