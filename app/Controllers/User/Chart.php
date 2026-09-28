<?php

namespace App\Controllers\User;

use App\Controllers\BaseController;
use App\Models\InvoiceModel;

class Chart extends BaseController
{
    public function index()
    {
        $invModel = new InvoiceModel();

        $data = [
            'title'   => 'Chart',
            'counter' => $invModel->counter_terkirim()->getResult(),
        ];

        return view('user/chart', $data);
    }
}
