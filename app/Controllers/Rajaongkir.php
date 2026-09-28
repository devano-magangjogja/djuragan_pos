<?php

namespace App\Controllers;

use App\Libraries\Ongkir;

class Rajaongkir extends BaseController
{
    public function index()
    {
        $data = [
            'data' => 'not valid',
        ];

        return $this->response->setJSON($data);
    }

    public function provinsi()
    {
        $id_provinsi = $this->request->getGet('prov');

        $ongkir   = new Ongkir();
        $provinsi = $ongkir->provinsi($id_provinsi);

        return $this->response->setJSON($provinsi);
    }

    public function kota()
    {
        $id_provinsi = $this->request->getGet('prov');
        $id_kota     = $this->request->getGet('kota');

        $ongkir = new Ongkir();
        $kota   = $ongkir->kota($id_provinsi, $id_kota);

        return $this->response->setJSON($kota);
    }

    public function kecamatan()
    {
        $id_kota      = $this->request->getGet('kota');
        $id_kecamatan = $this->request->getGet('kec');

        $ongkir    = new Ongkir();
        $kecamatan = $ongkir->kecamatan($id_kota, $id_kecamatan);

        return $this->response->setJSON($kecamatan);
    }

    public function get()
    {
        $subdistrict = $this->request->getGet('kec');
        $weight      = $this->request->getGet('berat');

        $cost = $this->rajaongkir->cost(['city' => 501], ['subdistrict' => $subdistrict], $weight, 'jne:pos:jnt')->data;

        return $this->response->setJSON($cost);
    }
}
