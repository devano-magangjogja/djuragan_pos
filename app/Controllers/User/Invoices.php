<?php

namespace App\Controllers\User;

use App\Controllers\BaseController;
use App\Models\InvoiceModel;
use App\Models\JuraganModel;
use App\Models\PembayaranModel;
use App\Models\UserModel;
use CodeIgniter\I18n\Time;

class Invoices extends BaseController
{
    public function tulis()
    {
        $data = [
            'title' => 'Tulis Orderan Baru',
        ];

        return view('user/invoice/tulis', $data);
    }

    public function save()
    {
        $validation = \Config\Services::validation();

        if ($this->request->getPost()) {
            $validation->setRuleGroup('addInvoice');
        }

        if ($this->request->isAJAX()) {
            if (! $validation->withRequest($this->request)->run()) {
                $this->response->setStatusCode(406);
                $errors = $validation->getErrors();

                return $this->response->setJSON($errors);
            }

            $db           = \Config\Database::connect();
            $userModel    = new UserModel();
            $invModel     = new InvoiceModel();
            $juraganModel = new JuraganModel();

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
                'keterangan'     => ($this->request->getPost('keterangan') !== '' ? trim($this->request->getPost('keterangan')) : null),
                'rincian'        => rincian_json($this->request->getPost('rincian'), array_keys(meta_rincian('pesanan')), ['tipe' => array_keys(tipe_pesanan())]),
            ];

            // simpan ke database
            $invModel->insert($data_invoice);

            if ($db->affectedRows() > 0) {
                $invoice_id = $db->insertID();

                // simpan asal orderan
                $data_asal = [
                    'invoice_id' => $invoice_id,
                    'source_id'  => $this->request->getPost('asal_orderan'),
                    'label'      => ($this->request->getPost('label') !== '' ? trim($this->request->getPost('label')) : null),
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

                // simpan notifikasi, kirim kesemua user
                simpan_notif(1, $juragan_id, $invoice_id);
            }

            $juragan = $juraganModel->byInvoiceId($invoice_id)->getResult()[0]->juragan;
            $ret     = [
                'status' => 'data tersimpan',
                'url'    => site_url('user/invoices/lihat/' . $juragan . '/semua?cari[kolom]=faktur&cari[q]=' . $seri),
            ];

            return $this->response->setJSON($ret);
        }
    }

    // menampilkan semua invoice
    public function lihat($juragan = '', $hal = 'dalam-proses', $kategori = '')
    {
        if (! in_array($hal, ['semua', 'pembayaran', 'cek-bayar', 'dalam-proses', 'belum-proses', 'selesai'], true)) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        // tiap tab punya chip filter sendiri, jadi kategori hanya boleh dari daftar tab ini
        $daftar_kategori = kategori_tab($hal);

        if ($kategori === '') {
            $kategori = $hal === 'belum-proses' ? 'semua' : 'perlu-cek';
        }

        if (! array_key_exists($kategori, $daftar_kategori)) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        if ($hal === 'cek-bayar') {
            return redirect()->to(site_url('user/invoices/lihat/' . $juragan . '/pembayaran/' . $kategori));
        }

        // pencarian
        $cari       = $this->request->getGet('cari') ?? '';
        $user_id    = session('id');
        $id_juragan = 0;

        if ($juragan === '') {
            $juragan = $this->juraganBy($user_id);

            return redirect()->to('/user/invoices/lihat/' . $juragan->juragan);
        }

        // diijinkan atau tidak
        if ($this->allowedJuragan($user_id, $juragan)) {
            $invModel     = new InvoiceModel();
            $juraganModel = new JuraganModel();
            $juragans     = $juraganModel->where('juragan', $juragan)->findAll();
            $title        = $juragans[0]->nama_juragan;
            $id_juragan   = $juragans[0]->id_juragan;

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
                'title'               => 'Transaksi ' . $title,
                'orderan'             => $invModel->getAll($hal, $id_juragan, $cari, $limit, $offset, $kategori),
                'juragan'             => $juragan,
                'juragan_id'          => $id_juragan,
                'hal'                 => $hal,
                'kategori'            => $kategori,
                'kategori_pembayaran' => $daftar_kategori,
                'jumlah_kategori'     => in_array($hal, ['pembayaran', 'belum-proses'], true) ? $invModel->countPembayaran($id_juragan, $hal) : [],
                'limit'               => $limit,
                'page'                => $page,
            ];

            return view('user/invoice/lihat', $data);
        }

        throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
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

            $juraganModel    = new JuraganModel();
            $pembayaranModel = new PembayaranModel();
            $invModel        = new InvoiceModel();
            $time            = Time::parse($this->request->getPost('tanggal_pembayaran'))->getTimestamp();
            $invoice_id      = $this->request->getPost('invoice_id');

            // halaman lama masih bisa menampilkan tombolnya, jadi invoice lunas ditolak di sini juga
            if (tagihan_lunas($invModel->find($invoice_id)->status_pembayaran)) {
                $this->response->setStatusCode(406);

                return $this->response->setJSON([
                    'total_pembayaran' => 'Invoice ini sudah lunas, tidak bisa ditambah pembayaran lagi.',
                ]);
            }

            $data = [
                'invoice_id'         => $invoice_id,
                'sumber_dana'        => $this->request->getPost('sumber_dana'),
                'total_pembayaran'   => $this->request->getPost('total_pembayaran'),
                'tanggal_pembayaran' => $time,
            ];

            $pembayaranModel->save($data);

            // update status pembayaran (invoice)
            $this->_update_status_pembayaran($invoice_id);

            $juragan = $juraganModel->byInvoiceId($invoice_id)->getResult()[0]->juragan;

            return $this->response->setJSON([
                'url' => site_url('user/invoices/lihat/' . $juragan . '/semua?cari[kolom]=id&cari[q]=' . $invoice_id),
            ]);
        }
    }

    private function _update_status_pembayaran($invoice_id)
    {
        $invModel = new InvoiceModel();
        $cek      = $invModel->total_biaya($invoice_id)->getResult()[0];

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

        // simpan notif
        $juragan_id = $invModel->find($invoice_id)->juragan_id;
        simpan_notif(4, $juragan_id, $invoice_id);

        // update status_pembayaran
        // jadikan status
        $update_invoice = [
            'id_invoice'        => $invoice_id,
            'status_pembayaran' => $status_bayar,
        ];
        $invModel->save($update_invoice);

        return true;
    }

    // hapus invoice
    public function hapus_orderan()
    {
        if ($this->request->isAJAX()) {
            $invModel   = new InvoiceModel();
            $invoice_id = $this->request->getPost('invoice_id');

            $invModel->delete($invoice_id);

            return $this->response->setJSON([
                'status' => 'Orderan dihapus',
                'url'    => site_url('user/invoices'),
            ]);
        }

        throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
    }

    public function info_pembayaran()
    {
        if ($this->request->isAJAX()) {
            $pembayaranModel = new PembayaranModel();
            $invoice_id      = $this->request->getGet('id');
            $x               = $pembayaranModel->ambil($invoice_id)->get()->getResult();
            $res             = [];

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
