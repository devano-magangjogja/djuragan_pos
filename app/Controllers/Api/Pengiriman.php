<?php

namespace App\Controllers\Api;

use CodeIgniter\API\ResponseTrait;
use CodeIgniter\I18n\Time;

class Pengiriman extends \CodeIgniter\Controller
{
    use ResponseTrait;

    public function get()
    {
        $pengiriman = new \App\Models\PengirimanModel();
        // helper('fungsi');

        $id = $this->request->getGet('id');
        if (! isset($id) || empty($id) || (int) $id === 0) {
            $res = [];
        } else {
            $res = $pengiriman->where(['id_pengiriman' => $id])->findAll()[0];
        }

        return $this->respond($res);
    }

    public function save()
    {
        $pengiriman = new \App\Models\PengirimanModel();
        $invoice    = new \App\Models\InvoiceModel();
        helper('fungsi');

        $time       = Time::parse($this->request->getPost('tanggal_kirim'))->getTimestamp();
        $invoice_id = $this->request->getPost('invoice_id');
        $qty        = $this->request->getPost('qty');
        $kurir      = $this->request->getPost('kurir');
        if ($kurir === 'lainnya') {
            $kurir = $this->request->getPost('lainnya');
        }

        $id_pengiriman = $this->request->getPost('id_pengiriman');

        $kirim_id = [];
        if ((int) $id_pengiriman > 0) {
            $kirim_id = [
                'id_pengiriman' => $id_pengiriman,
            ];
        }

        $data = [
            'invoice_id'    => $invoice_id,
            'kurir'         => $kurir,
            'ongkir'        => $this->request->getPost('ongkir'),
            'resi'          => $this->request->getPost('resi'),
            'qty_kirim'     => $qty,
            'tanggal_kirim' => $time,
        ];

        $data = array_merge($data, $kirim_id);

        $pengiriman->save($data);

        $db = \Config\Database::connect();

        if ($db->affectedRows() > 0) {
            // cek sudah selesai kirim atau belum
            $hasil = $invoice->jumlah_produk($invoice_id)->getResult()[0];

            $status = '2';
            if ($hasil->wajib_kirim === $hasil->sudah_kirim) {
                $status = '3';
            } elseif ((int) $hasil->wajib_kirim < (int) $hasil->sudah_kirim) {
                $status = '3';
            }

            // save db invoice
            $invoice->save([
                'id_invoice'        => $invoice_id,
                'status_pengiriman' => $status,
            ]);

            // simpan notif
            $juragan_id = $invoice->find($invoice_id)->juragan_id;
            if ((int) $id_pengiriman > 0) {
                simpan_notif(23, $juragan_id, $invoice_id);
            } else {
                simpan_notif(($status === '2' ? 21 : 22), $juragan_id, $invoice_id);
            }
        }

        $res = [
            'status' => 'OK',
            'url'    => site_url('admin/invoices/lihat/semua/semua?cari[kolom]=id&cari[q]=' . $invoice_id),
        ];

        return $this->respond($res);
    }
}
