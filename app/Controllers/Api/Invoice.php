<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Models\InvoiceModel;
use CodeIgniter\API\ResponseTrait;

class Invoice extends BaseController
{
    use ResponseTrait;

    /**
     * Angka badge tab daftar transaksi. Dibatasi pada toko milik akun yang sedang
     * login, jadi id juragan yang ditebak tidak lagi membocorkan jumlah nota toko lain.
     */
    public function counter_tab($id_juragan)
    {
        $invModel   = new InvoiceModel();
        $id_juragan = (int) $id_juragan;
        $hanya      = session()->get('level') === 'superadmin' ? null : $this->tokoBoleh();

        if ($id_juragan > 0 && ! $this->bolehToko($id_juragan)) {
            // daftar kosong membuat semua badge jadi nol, bentuk jawabannya tetap sama
            $hanya = [];
        }

        return $this->response->setJSON($invModel->getCount($id_juragan, $hanya));
    }
}
