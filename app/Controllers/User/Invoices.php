<?php

namespace App\Controllers\User;

use App\Controllers\BaseController;
use App\Models\InvoiceModel;
use App\Models\JuraganModel;
use App\Models\PembayaranModel;
use App\Models\UkuranKomponenModel;
use App\Models\UserModel;
use CodeIgniter\I18n\Time;

class Invoices extends BaseController
{
    public function tulis()
    {
        $komponen = new UkuranKomponenModel();

        $data = [
            'title'           => 'Tulis Orderan Baru',
            'jenis_produk'    => $komponen->jenis(),
            'template_ukuran' => $komponen->semuaTemplate(),
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
            $rincian    = $this->request->getPost('rincian');

            // orderan hanya boleh dititipkan ke toko yang dipegang akun ini
            if (! $this->bolehToko((int) $juragan_id)) {
                $this->response->setStatusCode(406);

                return $this->response->setJSON(['juragan' => 'Toko ini tidak berada di bawah akun Anda.']);
            }

            $data_invoice = [
                'tanggal_pesan'  => $this->request->getPost('tanggal_order'),
                'seri'           => $seri,
                'pemesan_id'     => $this->request->getPost('id_pemesan'),
                'kirimKepada_id' => $this->request->getPost('id_kirimKe'),
                'juragan_id'     => $juragan_id,
                'user_id'        => $user_id,
                'keterangan'     => ($this->request->getPost('keterangan') !== '' ? trim($this->request->getPost('keterangan')) : null),
                'deadline'       => deadline_iso($rincian),
                'rincian'        => rincian_json($rincian, array_keys(meta_rincian('pesanan')), ['tipe' => array_keys(tipe_pesanan()), 'orderan' => ['satuan', 'rombongan']]),
            ];

            // sama seperti di panel admin: satu orderan, satu transaksi
            $db->transBegin();

            $invModel->insert($data_invoice);
            $invoice_id = (int) $db->insertID();
            $galat      = [];

            if ($invoice_id > 0) {
                // simpan asal orderan
                $data_asal = [
                    'invoice_id' => $invoice_id,
                    'source_id'  => $this->request->getPost('asal_orderan'),
                    'label'      => ($this->request->getPost('label') !== '' ? trim($this->request->getPost('label')) : null),
                ];
                $db->table('label_invoice')->insert($data_asal);

                // simpan produk, lalu ukuran terstruktur per item
                $produk = produk_dibeli($this->request->getPost('produk'), $invoice_id);
                $peta   = sinkron_dibeli($invoice_id, $produk['baris']);
                $galat  = array_merge(
                    simpan_ukuran_produk($invoice_id, $peta, $produk['ukuran']),
                    simpan_anggota($invoice_id, $this->request->getPost('anggota_dikirim'), $this->request->getPost('anggota'))
                );

                if ($galat === []) {
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
            } else {
                $galat['invoice'] = 'Orderan gagal disimpan, coba lagi ya.';
            }

            if ($galat !== [] || ! $db->transStatus()) {
                $db->transRollback();
                $this->response->setStatusCode(406);

                return $this->response->setJSON($galat);
            }

            $db->transCommit();

            $juragan = $juraganModel->byInvoiceId($invoice_id)->getResult()[0]->juragan;
            $ret     = [
                'status' => 'data tersimpan',
                // sama seperti panel admin: foto naik setelah orderan punya id
                'id'     => $invoice_id,
                'url'    => site_url('user/invoices/lihat/' . $juragan . '/semua?cari[kolom]=faktur&cari[q]=' . $seri),
            ];

            return $this->response->setJSON($ret);
        }
    }

    // menampilkan semua invoice
    public function lihat($juragan = '', $hal = 'dalam-proses', $kategori = '')
    {
        if (! in_array($hal, ['semua', 'pembayaran', 'cek-bayar', 'dalam-proses', 'belum-proses', 'selesai', 'saring'], true)) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        // tiap tab punya chip filter sendiri, jadi kategori hanya boleh dari daftar tab ini
        $daftar_kategori = kategori_tab($hal);

        if ($kategori === '') {
            // tab saring dibuka dari kartu dasbor, defaultnya saringan paling luas
            $kategori = match ($hal) {
                'belum-proses' => 'semua',
                'saring'       => 'aktif',
                default        => 'perlu-cek',
            };
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

            // akun warisan ada yang belum punya relasi juragan, jadi tidak ada slug untuk
            // dituju; tanpa pagar ini halaman hanya melempar property tak dikenal (HTTP 500).
            // Pesannya dikirim lewat view 404 milik app, bukan PageNotFoundException, karena
            // override 404 di Config/Routes.php membuang semua pesan supaya detail internal
            // router tidak terbaca tamu.
            if (empty($juragan->juragan)) {
                return response()->setStatusCode(404)->setBody(
                    view('errors/html/error_page', [
                        'message' => 'Akun ini belum ditautkan ke juragan mana pun, jadi belum ada orderan yang bisa dibuka. Hubungi admin untuk menautkan akun ke juragan.',
                    ]),
                );
            }

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
            $nota            = $this->notaMilikToko((int) $invoice_id);

            // pembayaran hanya boleh dicatat untuk nota di toko sendiri
            if ($nota === null) {
                return $this->tolakToko();
            }

            // halaman lama masih bisa menampilkan tombolnya, jadi invoice lunas ditolak di sini juga
            if (tagihan_lunas($nota->status_pembayaran)) {
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

            // simpan notif
            simpan_notif(4, $nota->juragan_id, $invoice_id);

            // update status pembayaran (invoice)
            $invModel->perbaruiStatusPembayaran($invoice_id);

            $juragan = $juraganModel->byInvoiceId($invoice_id)->getResult()[0]->juragan;

            return $this->response->setJSON([
                'url' => site_url('user/invoices/lihat/' . $juragan . '/semua?cari[kolom]=id&cari[q]=' . $invoice_id),
            ]);
        }
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

            // riwayat pembayaran nota toko lain tidak boleh dibaca
            if ($this->notaMilikToko((int) $invoice_id) === null) {
                return $this->tolakToko();
            }

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
