<?php

namespace App\Controllers;

use App\Libraries\Ongkir;
use App\Models\PelangganModel;

class Pelanggan extends BaseController
{
    public function baru()
    {
        if ($this->request->isAJAX()) {
            $ongkir    = new Ongkir();
            $pelanggan = new PelangganModel();

            $d['nama_pelanggan'] = strtoupper(trim($this->request->getPost('nama_pelanggan')));
            $d['hp']             = json_encode(array_filter($this->request->getPost('hp')));

            $cod = $this->request->getVar('cod');
            $cod = ($cod === null ? '0' : '1');

            $d['cod'] = $cod;
            if ($cod === '1') {
                $d['kecamatan'] = null;
                $d['kabupaten'] = null;
                $d['provinsi']  = null;
                $d['alamat']    = null;
                $d['kodepos']   = null;
            } else {
                $d['kecamatan'] = $this->request->getPost('kecamatan');
                $d['kabupaten'] = $this->request->getPost('kabupaten');
                $d['provinsi']  = $this->request->getPost('provinsi');
                $d['alamat']    = htmlentities(strtoupper(trim(preg_replace('/\s+/', ' ', $this->request->getPost('alamat')))), ENT_QUOTES);
                $d['kodepos']   = $this->request->getPost('kodepos');
            }

            if ($this->request->getVar('id_pelanggan')) {
                $d['id_pelanggan'] = (int) $this->request->getPost('id_pelanggan');
            }

            $pelanggan->save($d);

            if ($this->request->getVar('id_pelanggan')) {
                $id = (int) $this->request->getPost('id_pelanggan');
            } else {
                $db = \Config\Database::connect();
                $id = $db->insertID();
            }

            $result = $pelanggan->ambil($id)->getFirstRow();

            $r['id_pelanggan']   = (int) $result->id_pelanggan;
            $r['hp']             = json_decode($result->hp);
            $r['nama_pelanggan'] = $result->nama_pelanggan;
            $r['cod']            = $result->cod;

            if ($result->cod === '0') {
                $r['nama_kecamatan'] = strtoupper(esc($ongkir->kecamatan($result->kabupaten, $result->kecamatan)['subdistrict_name']));
                $r['kecamatan']      = (int) $result->kecamatan;
                $r['nama_kabupaten'] = strtoupper(esc($ongkir->kota($result->provinsi, $result->kabupaten)['city_name']));
                $r['kabupaten']      = (int) $result->kabupaten;
                $r['nama_provinsi']  = strtoupper(esc($ongkir->provinsi($result->provinsi)['province_name']));
                $r['provinsi']       = (int) $result->provinsi;
                $r['alamat']         = $result->alamat;
                $r['kodepos']        = $result->kodepos;
            }

            return $this->response->setJSON($r);
        }

        throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
    }

    public function cari()
    {
        // if ($this->request->isAJAX()) {
        // } else {
        //     throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        // }

        $cari       = $this->request->getGet('q');
        $juragan_id = $this->request->getGet('juragan_id');
        $pelanggan  = new PelangganModel();
        $ongkir     = new Ongkir();

        if ($this->request->getVar('juragan_id') && $juragan_id !== null) {
            $juragan_id = $juragan_id;
        } else {
            $juragan_id = '0';
        }

        $builder = $pelanggan->cari($juragan_id, $cari);

        $s = [];
        $i = 0;

        foreach ($builder->getResult() as $u) {
            $s[$i] = [
                'id'   => (int) $u->id_pelanggan,
                'nama' => $u->nama_pelanggan,
                'hp'   => json_decode($u->hp),
                'cod'  => (int) $u->cod,
                'full' => 'C.O.D',
            ];

            if ($u->cod === '0') {
                $id_kecamatan = (int) $u->kecamatan;
                $id_kabupaten = (int) $u->kabupaten;
                $id_provinsi  = (int) $u->provinsi;

                $kota = $ongkir->kota($id_provinsi, $id_kabupaten);

                $nama_kecamatan = strtoupper($ongkir->kecamatan($id_kabupaten, $id_kecamatan)['subdistrict_name']);
                $nama_kabupaten = strtoupper(($kota['type'] === 'Kabupaten' ? '' : '(Kota) ') . $kota['city_name']);
                $nama_provinsi  = strtoupper($ongkir->provinsi($id_provinsi)['province']);

                $s[$i]['nama_kecamatan'] = $nama_kecamatan;
                $s[$i]['kecamatan']      = $id_kecamatan;
                $s[$i]['nama_kabupaten'] = $nama_kabupaten;
                $s[$i]['kabupaten']      = $id_kabupaten;
                $s[$i]['nama_provinsi']  = $nama_provinsi;
                $s[$i]['provinsi']       = $id_provinsi;
                $s[$i]['alamat']         = $u->alamat;
                $s[$i]['kodepos']        = $u->kodepos;
                $s[$i]['full']           = $u->alamat . ', ' . $nama_kecamatan . ', ' . $nama_kabupaten . ', ' . $nama_provinsi . ' - ' . $u->kodepos;
            }

            $i++;
        }

        $return = [
            'query'   => $cari,
            'results' => $s,
        ];

        return $this->response->setJSON($return);
    }
}
