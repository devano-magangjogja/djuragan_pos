<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\InvoiceModel;
use App\Models\JuraganModel;
use App\Models\PembayaranModel;
use App\Models\UserModel;
use CodeIgniter\I18n\Time;

class Invoices extends BaseController
{
    /**
     * Halaman untuk membuat invoice baru
     *
     * @return string
     */
    public function tulis()
    {
        $data = [
            'title' => 'Tulis Orderan Baru',
        ];

        return view('admin/invoice/tulis', $data);
    }

    // menampilkan semua invoice
    public function lihat($juragan = 'semua', $hal = 'cek-bayar')
    {
        if (! in_array($hal, ['semua', 'cek-bayar', 'dalam-proses', 'belum-proses', 'selesai'], true)) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $juraganModel = new JuraganModel();
        $invModel     = new InvoiceModel();
        $title        = 'Semua Juragan';
        $id_juragan   = 0;

        if ($juragan !== 'semua') {
            $juragans = $juraganModel->where('juragan', $juragan)->findAll();
            if (count($juragans) < 1) {
                return redirect()->to('/faktur?juragan_notfound=' . $juragan);
            }
            $title      = $juragans[0]->nama_juragan;
            $id_juragan = $juragans[0]->id_juragan;
        }

        // pencarian
        $cari = $this->request->getGet('cari') ?? '';

        // halaman
        $limit = config('Pager')->perPage;
        $page  = (int) $this->request->getGet('page');

        if (! isset($page) || $page === 0 || $page === 1) {
            $page   = 1;
            $offset = 0;
        } else {
            $offset = ($page - 1) * $limit;
            $page   = $page;
        }

        $data = [
            'title'      => 'Invoice ' . $title,
            'orderan'    => $invModel->getAll($hal, $id_juragan, $cari, $limit, $offset),
            'juragan'    => $juragan,
            'juragan_id' => $id_juragan,
            'hal'        => $hal,
            'limit'      => $limit,
            'page'       => $page,
        ];

        return view('admin/invoice/lihat', $data);
    }

    // halaman untuk menyunting invoice verdasarkan $seri
    public function sunting($seri = '')
    {
        $invModel = new InvoiceModel();

        $x = $invModel->where('seri', $seri)->first();

        if ($seri === '' || empty($seri) || $x === null) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }
        $cari = [
            'kolom' => 'faktur',
            'q'     => $seri,
        ];

        $pesanan = $invModel->getAll('semua', 0, $cari);
        $data    = [
            'title'   => 'Sunting Orderan #' . $seri,
            'pesanan' => $pesanan['data'],
        ];

        return view('admin/invoice/sunting', $data);
    }

    // hapus invoice
    public function hapus_orderan()
    {
        if ($this->request->isAJAX()) {
            $invModel = new InvoiceModel();

            $invoice_id = $this->request->getPost('invoice_id');
            $juragan_id = $invModel->find($invoice_id)->juragan_id;
            $invModel->delete($invoice_id);

            // simpan notif
            simpan_notif(3, $juragan_id, $invoice_id);

            return $this->response->setJSON(['status' => 'Orderan dihapus', 'url' => site_url('admin/invoices')]);
        }

        throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
    }

    public function save()
    {
        $validation = \Config\Services::validation();
        $userModel  = new UserModel();

        if ($this->request->getPost()) {
            $validation->setRuleGroup('addInvoice');
        }

        if ($this->request->isAJAX()) {
            if (! $validation->withRequest($this->request)->run()) {
                $this->response->setStatusCode(406);
                $errors = $validation->getErrors();

                return $this->response->setJSON($errors);
            }
            $db = \Config\Database::connect();

            $user_id    = $this->request->getPost('pengguna');
            $t          = $userModel->find($user_id);
            $seri       = strtoupper(first_letter($t->name) . time());
            $juragan_id = $this->request->getPost('juragan');

            $data_invoice = [
                'tanggal_pesan'  => $this->request->getPost('tanggal_order'),
                'seri'           => $seri,
                'pemesan_id'     => $this->request->getPost('id_pemesan'),
                'kirimKepada_id' => $this->request->getPost('id_kirimKe'),
                'juragan_id'     => $juragan_id,
                'user_id'        => $user_id,
                // 'status_pesanan'=> '',
                // 'status_pembayaran'=> '',
                // 'status_pengiriman'=> '',
                'keterangan' => ($this->request->getPost('keterangan') !== '' ? $this->request->getPost('keterangan') : null),
                'rincian'    => rincian_json($this->request->getPost('rincian'), array_keys(meta_rincian('pesanan'))),
            ];

            $invModel = new InvoiceModel();

            // simpan ke database
            $invModel->insert($data_invoice);

            if ($db->affectedRows() > 0) {
                $invoice_id = $db->insertID();

                // simpan asal orderan
                $data_asal = [
                    'invoice_id' => $invoice_id,
                    'source_id'  => $this->request->getPost('asal_orderan'),
                    'label'      => ($this->request->getPost('label') !== '' ? $this->request->getPost('label') : null),
                ];
                $db->table('label_invoice')->insert($data_asal);

                // simpan produk
                $produks = $this->request->getPost('produk');

                // tambahkan `invoice_id` untuk tiap pesanan
                $produk = [];

                foreach ($produks as $k => $v) {
                    $rincian_produk = [];

                    foreach ($v as $p => $d) {
                        if ($p === 'rincian') {
                            $rincian_produk = is_array($d) ? $d : [];

                            continue;
                        }

                        $produk[$k]['invoice_id'] = $invoice_id;
                        $produk[$k][$p]           = $d;
                    }

                    $produk[$k]['rincian'] = rincian_json($rincian_produk, array_keys(meta_rincian('produk')));
                }
                $db->table('dibeli')->insertBatch(lengkapi_produk($produk));

                // simpan notif
                simpan_notif(1, $juragan_id, $invoice_id);

                // simpan biaya
                if ($this->request->getVar('biaya') !== null) {
                    $biayas = $this->request->getPost('biaya');

                    // tambahkan `invoice_id` untuk tiap biaya
                    $biaya = [];

                    foreach ($biayas as $k => $v) {
                        foreach ($v as $p => $d) {
                            $biaya[$k]['invoice_id'] = $invoice_id;
                            $biaya[$k][$p]           = ($d !== '' ? $d : null);
                        }
                    }
                    $db->table('biaya')->insertBatch($biaya);
                }
            }

            $ret = [
                'status' => 'data tersimpan',
                'url'    => site_url('admin/invoices/lihat/semua/semua?cari[kolom]=faktur&cari[q]=' . $seri),
            ];

            return $this->response->setJSON($ret);
        }
    }

    public function update()
    {
        $validation = \Config\Services::validation();

        if ($this->request->getPost()) {
            $validation->setRuleGroup('updateInvoice');
        }

        if ($this->request->isAJAX()) {
            if (! $validation->withRequest($this->request)->run()) {
                $this->response->setStatusCode(406);
                $errors = $validation->getErrors();

                return $this->response->setJSON($errors);
            }
            $db = \Config\Database::connect();

            $invoice_id = $this->request->getPost('id_invoice');
            $user_id    = $this->request->getPost('pengguna');

            $data_invoice = [
                'id_invoice'    => $invoice_id,
                'tanggal_pesan' => $this->request->getPost('tanggal_order'),
                // 'seri'			=> $seri,
                'pemesan_id'     => $this->request->getPost('id_pemesan'),
                'kirimKepada_id' => $this->request->getPost('id_kirimKe'),
                'juragan_id'     => $this->request->getPost('juragan'),
                'user_id'        => $user_id,
                'keterangan'     => ($this->request->getPost('keterangan') !== '' ? $this->request->getPost('keterangan') : null),
                'rincian'        => rincian_json($this->request->getPost('rincian'), array_keys(meta_rincian('pesanan'))),
            ];

            $invModel = new InvoiceModel();

            // simpan ke database
            $invModel->save($data_invoice);

            if ($db->affectedRows() > 0) {
                // $invoice_id = $db->insertID();

                // hapus data asal orderan
                $db->table('label_invoice')->delete(['invoice_id' => $invoice_id]);

                // simpan asal orderan
                $data_asal = [
                    'invoice_id' => $invoice_id,
                    'source_id'  => $this->request->getPost('asal_orderan'),
                    'label'      => ($this->request->getPost('label') !== '' ? $this->request->getPost('label') : null),
                ];
                $db->table('label_invoice')->insert($data_asal);

                // hapus produk dibeli
                $db->table('dibeli')->delete(['invoice_id' => $invoice_id]);

                // simpan produk
                $produks = $this->request->getPost('produk');

                // tambahkan `invoice_id` untuk tiap pesanan
                $produk = [];

                foreach ($produks as $k => $v) {
                    $rincian_produk = [];

                    foreach ($v as $p => $d) {
                        if ($p === 'rincian') {
                            $rincian_produk = is_array($d) ? $d : [];

                            continue;
                        }

                        $produk[$k]['invoice_id'] = $invoice_id;
                        $produk[$k][$p]           = $d;
                    }

                    $produk[$k]['rincian'] = rincian_json($rincian_produk, array_keys(meta_rincian('produk')));
                }
                $db->table('dibeli')->insertBatch(lengkapi_produk($produk));

                // hapus biaya
                $db->table('biaya')->delete(['invoice_id' => $invoice_id]);

                // simpan notif
                $juragan_id = $invModel->find($invoice_id)->juragan_id;
                simpan_notif(2, $juragan_id, $invoice_id);

                // simpan biaya
                if ($this->request->getVar('biaya') !== null) {
                    $biayas = $this->request->getPost('biaya');

                    // tambahkan `invoice_id` untuk tiap biaya
                    $biaya = [];

                    foreach ($biayas as $k => $v) {
                        foreach ($v as $p => $d) {
                            $biaya[$k]['invoice_id'] = $invoice_id;
                            $biaya[$k][$p]           = ($d !== '' ? $d : null);
                        }
                    }
                    $db->table('biaya')->insertBatch($biaya);
                }
            }

            $ret = [
                'status' => 'data tersimpan',
                'url'    => site_url('admin/invoices/lihat/semua/semua?cari[kolom]=id&cari[q]=' . $invoice_id),
            ];

            return $this->response->setJSON($ret);
        }
    }

    public function save_progress()
    {
        $validation = \Config\Services::validation();

        if ($this->request->getPost()) {
            $validation->setRuleGroup('simpanProgress');
        }

        if ($this->request->isAJAX()) {
            if (! $validation->withRequest($this->request)->run()) {
                $this->response->setStatusCode(406);
                $errors = $validation->getErrors();

                return $this->response->setJSON($errors);
            }
            $invoice_id = $this->request->getPost('id_invoice');
            $status     = $this->request->getPost('status');
            $stat       = $this->request->getPost('stat'); // if 1 = akhir, 0 = mulai
            $keterangan = $this->request->getPost('keterangan');

            $data = [
                'invoice_id' => $invoice_id,
                'status'     => $status,
                'stat'       => $stat,
                'keterangan' => ($keterangan !== '' ? $keterangan : null),
            ];

            $invModel = new InvoiceModel();

            // ambil yang terakhir
            $arr = array_slice($invModel->status($invoice_id)->getResult(), -1);

            if (empty($arr)) {
                // jika kosong, bikin baru
                $this->_simpan_status($data);

                // update orderan menjadi `diproses` (2)
                $update_invoice = [
                    'id_invoice'     => $invoice_id,
                    'status_pesanan' => '2',
                ];
                $invModel->save($update_invoice);
            } else {
                // pastinya update yang sudah ada (belum lengkap)
                // atau bikin baru kalau yang terakhir sudah lengkap
                if ($arr[0]->tanggal_selesai === null) {
                    // update
                    $this->_simpan_status($data, $arr[0]->id_status);
                } else {
                    // create
                    $this->_simpan_status($data);
                }
            }

            $response = [
                'status' => 200,
                'url'    => site_url('admin/invoices/lihat/semua/semua?cari[kolom]=id&cari[q]=' . $invoice_id),
            ];

            return $this->response->setJSON($response);
        }
    }

    private function _simpan_status($data, $id_status = false)
    {
        $db      = \Config\Database::connect();
        $builder = $db->table('invoice_status');

        $builder->set('invoice_id', $data['invoice_id']);
        $builder->set('status', $data['status']);
        $builder->set('tanggal_masuk', time());

        if ($data['stat'] === '1') {
            $builder->set('tanggal_selesai', time());
            $builder->set('keterangan_selesai', $data['keterangan']);
        } else {
            $builder->set('keterangan_masuk', $data['keterangan']);
        }

        $pro = (int) $data['status'];
        $stt = (int) $data['stat'];

        switch ($pro) {
            case 1:
                $status = 7;
                if ($stt === 1) {
                    $status = 8;
                }
                break;

            case 2:
                $status = 9;
                if ($stt === 1) {
                    $status = 10;
                }
                break;

            case 3:
                $status = 11;
                if ($stt === 1) {
                    $status = 12;
                }
                break;

            case 4:
                $status = 13;
                if ($stt === 1) {
                    $status = 14;
                }
                break;

            case 5:
                $status = 15;
                if ($stt === 1) {
                    $status = 16;
                }
                break;

            case 6:
                $status = 17;
                if ($stt === 1) {
                    $status = 18;
                }
                break;

            case 7:
                $status = 19;
                if ($stt === 1) {
                    $status = 20;
                }
                break;
        }

        $invModel = new InvoiceModel();

        // simpan notif
        $juragan_id = $invModel->find($data['invoice_id'])->juragan_id;
        simpan_notif($status, $juragan_id, $data['invoice_id']);

        if ($id_status === false) {
            $builder->insert();
        } else {
            $builder->where('id_status', $id_status);
            $builder->update();
        }
    }

    // tambah pembayaran
    public function simpan_pembayaran()
    {
        $validation = \Config\Services::validation();

        if ($this->request->getPost()) {
            $validation->setRuleGroup('tambahPembayaran');
        }

        if ($this->request->isAJAX()) {
            if (! $validation->withRequest($this->request)->run()) {
                $this->response->setStatusCode(406);
                $errors = $validation->getErrors();

                return $this->response->setJSON($errors);
            }

            $time            = Time::parse($this->request->getPost('tanggal_pembayaran'))->getTimestamp();
            $invoice_id      = $this->request->getPost('invoice_id');
            $pembayaranModel = new PembayaranModel();
            $invModel        = new InvoiceModel();

            $data = [
                'invoice_id'         => $invoice_id,
                'sumber_dana'        => $this->request->getPost('sumber_dana'),
                'total_pembayaran'   => $this->request->getPost('total_pembayaran'),
                'tanggal_pembayaran' => $time,
            ];

            $pembayaranModel->save($data);

            // simpan notif
            $juragan_id = $invModel->find($invoice_id)->juragan_id;
            simpan_notif(4, $juragan_id, $invoice_id);

            // update status pembayaran (invoice)
            $this->_update_status_pembayaran($invoice_id);

            return $this->response->setJSON([
                'url' => site_url('admin/invoices/lihat/semua/semua?cari[kolom]=id&cari[q]=' . $invoice_id),
            ]);
        }
    }

    // update status pembayaran
    public function update_bayar()
    {
        $validation = \Config\Services::validation();

        if ($this->request->getPost()) {
            $validation->setRuleGroup('updatePembayaran');
        }

        if ($this->request->isAJAX()) {
            if (! $validation->withRequest($this->request)->run()) {
                $this->response->setStatusCode(406);
                $errors = $validation->getErrors();

                return $this->response->setJSON($errors);
            }

            $status          = $this->request->getPost('status');
            $pembayaranModel = new PembayaranModel();
            $invModel        = new InvoiceModel();

            $data = [
                'id_pembayaran' => $this->request->getPost('id_pembayaran'),
                'status'        => $status,
                'tanggal_cek'   => time(),
            ];
            $pembayaranModel->save($data);

            $invoice_id = $this->request->getPost('invoice_id');

            // simpan notif
            $juragan_id = $invModel->find($invoice_id)->juragan_id;
            simpan_notif(($status === '3' ? 5 : 6), $juragan_id, $invoice_id);

            // update status pembayaran (invoice)
            $this->_update_status_pembayaran($invoice_id);

            $res = [
                'status' => 'data tersimpan',
                'url'    => site_url('admin/invoices/lihat/semua/semua?cari[kolom]=id&cari[q]=' . $invoice_id),
            ];

            return $this->response->setJSON($res);
        }
    }

    private function _update_status_pembayaran($invoice_id)
    {
        $invModel = new InvoiceModel();

        $cek = $invModel->total_biaya($invoice_id)->getResult()[0];
        // cek yang terbayar dan belum terbayar
        $terbayar    = (int) $cek->terbayar;
        $total_bayar = (int) $cek->barang + (int) $cek->lain;
        $belum_bayar = $total_bayar - $terbayar;
        $belum_cek   = (int) $cek->belumcek;

        if ($belum_cek > 0) {
            if ($terbayar > 0) { // ada yang belum dicek, tapi sudah ada dana masuk
                $status_bayar = '3';
            } else {
                $status_bayar = '2';
            }
        } else { //  tidak ada yang pelu dicek
            if ($terbayar === 0) {
                $status_bayar = '1';
            } else {
                if ($terbayar === $total_bayar) {
                    // sudah lunas
                    $status_bayar = '6';
                } elseif ($terbayar < $total_bayar) {
                    // masih belum lunas / kredit
                    $status_bayar = '4';
                } elseif ($terbayar > $total_bayar) {
                    // ada kelebihan
                    $status_bayar = '5';
                }
            }
        }

        // update status_pembayaran
        // jadikan status
        $update_invoice = [
            'id_invoice'        => $invoice_id,
            'status_pembayaran' => $status_bayar,
        ];
        $invModel->save($update_invoice);

        return true;
    }

    public function detail_status($invoice_id)
    {
        if ($this->request->isAJAX()) {
            $invModel = new InvoiceModel();
            $arr      = array_slice($invModel->status($invoice_id)->getResult(), -1);

            return $this->response->setJSON($arr);
        }

        throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
    }

    public function info_pembayaran()
    {
        if ($this->request->isAJAX()) {
            $pembayaranModel = new PembayaranModel();
            $invoice_id      = $this->request->getGet('id');
            $x               = $pembayaranModel->ambil($invoice_id)->get()->getResult();

            $res = [];

            foreach ($x as $bayar) {
                $res[] = [
                    'id'            => (int) $bayar->id,
                    'nama'          => $bayar->nama,
                    'atas_nama'     => $bayar->atas_nama,
                    'sumber'        => (int) $bayar->sumber,
                    'nominal'       => number_to_currency($bayar->nominal, 'IDR'), //(int) $bayar->nominal,
                    'status'        => (int) $bayar->status,
                    'tanggal_bayar' => (int) $bayar->tanggal_pembayaran,
                    'tanggal_cek'   => ($bayar->tanggal_cek !== null ? (int) $bayar->tanggal_cek : null),
                ];
            }

            return $this->response->setJSON($res);
        }
    }
}
