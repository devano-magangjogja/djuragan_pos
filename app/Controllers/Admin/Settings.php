<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;

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
        ];

        return view('admin/pengaturan/dasbor', $data);
    }
}
