<?php

namespace App\Controllers\Api;

use App\Models\InvoiceModel;
use CodeIgniter\API\ResponseTrait;

class Invoice extends \CodeIgniter\Controller
{
    use ResponseTrait;

    public function counter_tab($id_juragan)
    {
        $return   = [];
        $invModel = new InvoiceModel();

        $return = $invModel->getCount($id_juragan);

        return $this->response->setJSON($return);
    }
}
