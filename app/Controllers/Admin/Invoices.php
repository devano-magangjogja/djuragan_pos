<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\AnggotaModel;
use App\Models\FotoModel;
use App\Models\InvoiceModel;
use App\Models\JuraganModel;
use App\Models\NilaiUkuranModel;
use App\Models\PembayaranModel;
use App\Models\UkuranKomponenModel;
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
        $komponen = new UkuranKomponenModel();

        $data = [
            'title'           => 'Tulis Orderan Baru',
            'jenis_produk'    => $komponen->jenis(),
            'template_ukuran' => $komponen->semuaTemplate(),
        ];

        return view('admin/invoice/tulis', $data);
    }

    // menampilkan semua invoice
    public function lihat($juragan = 'semua', $hal = 'pembayaran', $kategori = '')
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
            return redirect()->to(site_url('admin/invoices/lihat/' . $juragan . '/pembayaran/' . $kategori));
        }

        $juraganModel = new JuraganModel();
        $invModel     = new InvoiceModel();
        $title        = 'Semua Juragan';
        $id_juragan   = 0;

        // satu toko, satu pengelola: akun yang cuma memegang satu toko tidak perlu
        // memilih, jadi daftar "semua" langsung diarahkan ke tokonya sendiri
        $tunggal = $this->tokoTunggal();

        if ($juragan === 'semua' && $tunggal !== null) {
            return redirect()->to(site_url('admin/invoices/lihat/' . $tunggal['slug'] . '/' . $hal . '/' . $kategori));
        }

        if ($juragan !== 'semua') {
            $juragans = $juraganModel->where('juragan', $juragan)->findAll();
            if (count($juragans) < 1) {
                return redirect()->to('/faktur?juragan_notfound=' . $juragan);
            }
            $title      = $juragans[0]->nama_juragan;
            $id_juragan = $juragans[0]->id_juragan;

            // slug toko orang lain tidak boleh membuka daftar transaksinya
            if (! $this->bolehToko($id_juragan)) {
                throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
            }
        }

        // superadmin memegang semua toko, jadi tidak perlu dibatasi daftar panjang
        $hanya = session()->get('level') === 'superadmin' ? null : $this->tokoBoleh();

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
            'title'               => 'Transaksi ' . $title,
            'orderan'             => $invModel->getAll($hal, $id_juragan, $cari, $limit, $offset, $kategori, $hanya),
            'juragan'             => $juragan,
            'juragan_id'          => $id_juragan,
            'hal'                 => $hal,
            'kategori'            => $kategori,
            'kategori_pembayaran' => $daftar_kategori,
            'jumlah_kategori'     => in_array($hal, ['pembayaran', 'belum-proses'], true) ? $invModel->countPembayaran($id_juragan, $hal, $hanya) : [],
            'limit'               => $limit,
            'page'                => $page,
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

        // nota milik toko lain tetap 404 walau nomor notasinya diketahui
        if (! $this->bolehToko((int) $x->juragan_id)) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }
        $cari = [
            'kolom' => 'faktur',
            'q'     => $seri,
        ];

        $pesanan  = $invModel->getAll('semua', 0, $cari);
        $komponen = new UkuranKomponenModel();
        $nilai    = new NilaiUkuranModel();
        $anggota  = new AnggotaModel();
        $id       = (int) $x->id_invoice;

        // ukuran tiap orang ikut menempel di baris anggota form
        $jenis_org   = $nilai->petaJenis($id, 'anggota_id');
        $ukuran_org  = $nilai->perItemForm($id, 'anggota_id');
        $anggota_item = $anggota->untukInvoice($id);

        foreach ($anggota_item as $i => $a) {
            $anggota_item[$i]['jenis'] = $jenis_org[(int) $a['id_anggota']] ?? null;
            $anggota_item[$i]['nilai'] = $ukuran_org[(int) $a['id_anggota']] ?? [];
        }

        $data = [
            'title'           => 'Sunting Orderan #' . $seri,
            'pesanan'         => $pesanan['data'],
            'jenis_produk'    => $komponen->jenis(),
            'template_ukuran' => $komponen->semuaTemplate(),
            'ukuran_item'     => $nilai->perItemForm($id),
            'jenis_item'      => $nilai->petaJenis($id),
            'anggota_item'    => $anggota_item,
            'foto_sudah'      => (new FotoModel())->untukInvoice($id),
        ];

        return view('admin/invoice/sunting', $data);
    }

    // hapus invoice
    public function hapus_orderan()
    {
        if ($this->request->isAJAX()) {
            $invModel   = new InvoiceModel();
            $invoice_id = (int) $this->request->getPost('invoice_id');
            $nota       = $this->notaMilikToko($invoice_id);

            // nota yang bukan miliknya tidak boleh dihapus akun mana pun di luar tokonya
            if ($nota === null) {
                return $this->tolakToko();
            }

            // alasan wajib dan hanya dari daftar yang dikenal: Error/Void Rate
            // memisahkan kesalahan operasional dari pembatalan yang sah
            $alasan = (string) $this->request->getPost('alasan');

            if (! array_key_exists($alasan, alasan_batal())) {
                return $this->response->setJSON([
                    'status' => 'Pilih alasan pembatalan lebih dulu.',
                    'url'    => site_url('admin/invoices/lihat/semua/semua?cari[kolom]=id&cari[q]=' . $invoice_id),
                ]);
            }

            $invModel->delete($invoice_id);

            // deleted_by/alasan_batal ada di luar allowedFields InvoiceModel,
            // jadi ditulis langsung; soft delete-nya sudah terjadi di baris yang sama
            \Config\Database::connect()->table('invoice')
                ->where('id_invoice', $invoice_id)
                ->update([
                    'deleted_by'   => pengguna_sesi(),
                    'alasan_batal' => $alasan,
                ]);

            // simpan notif
            simpan_notif(3, $nota->juragan_id, $invoice_id);

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
                // 'status_pesanan'=> '',
                // 'status_pembayaran'=> '',
                // 'status_pengiriman'=> '',
                'keterangan' => ($this->request->getPost('keterangan') !== '' ? $this->request->getPost('keterangan') : null),
                'deadline'   => deadline_iso($rincian),
                'rincian'    => rincian_json($rincian, array_keys(meta_rincian('pesanan')), ['tipe' => array_keys(tipe_pesanan()), 'orderan' => ['satuan', 'rombongan']]),
            ];

            $invModel = new InvoiceModel();

            // invoice, produk, ukuran, dan biaya ditulis sebagai satu transaksi:
            // orderan tidak boleh sampai tersimpan separuh
            $db->transBegin();

            $invModel->insert($data_invoice);
            $invoice_id = (int) $db->insertID();
            $galat      = [];

            if ($invoice_id > 0) {
                // simpan asal orderan
                $data_asal = [
                    'invoice_id' => $invoice_id,
                    'source_id'  => $this->request->getPost('asal_orderan'),
                    'label'      => ($this->request->getPost('label') !== '' ? $this->request->getPost('label') : null),
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

                    // simpan notif, kirim kesemua user
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

            $ret = [
                'status' => 'data tersimpan',
                // id dibutuhkan halaman untuk mengirim foto setelah orderan ada
                'id'     => $invoice_id,
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

            $invModel   = new InvoiceModel();
            $invoice_id = (int) $this->request->getPost('id_invoice');
            $user_id    = $this->request->getPost('pengguna');

            if ($invModel->find($invoice_id) === null) {
                $this->response->setStatusCode(406);

                return $this->response->setJSON(['id_invoice' => 'Orderannya tidak ditemukan, mungkin sudah dihapus.']);
            }

            // dua-duanya harus milik akun ini: notasinya dan toko tujuan pemindahannya
            if (! $this->notaMilikToko($invoice_id) || ! $this->bolehToko((int) $this->request->getPost('juragan'))) {
                $this->response->setStatusCode(406);

                return $this->response->setJSON(['juragan' => 'Toko ini tidak berada di bawah akun Anda.']);
            }

            $data_invoice = [
                'id_invoice'    => $invoice_id,
                'tanggal_pesan' => $this->request->getPost('tanggal_order'),
                // 'seri'			=> $seri,
                'pemesan_id'     => $this->request->getPost('id_pemesan'),
                'kirimKepada_id' => $this->request->getPost('id_kirimKe'),
                'juragan_id'     => $this->request->getPost('juragan'),
                'user_id'        => $user_id,
                'keterangan'     => ($this->request->getPost('keterangan') !== '' ? $this->request->getPost('keterangan') : null),
            ];

            // rincian invoice lama jangan ikut hilang kalau form tidak mengirimkannya
            $rincian_invoice = $this->request->getPost('rincian');

            if (is_array($rincian_invoice)) {
                $data_invoice['rincian']  = rincian_json($rincian_invoice, array_keys(meta_rincian('pesanan')), ['tipe' => array_keys(tipe_pesanan()), 'orderan' => ['satuan', 'rombongan']]);
                $data_invoice['deadline'] = deadline_iso($rincian_invoice);
            }

            // sama seperti save(): semua tabel ikut satu transaksi supaya suntingan
            // yang ditolak (misalnya angka ukuran tidak sah) tidak meninggalkan
            // orderan separuh jadi
            $db->transBegin();

            $invModel->save($data_invoice);

            // hapus data asal orderan
            $db->table('label_invoice')->delete(['invoice_id' => $invoice_id]);

            // simpan asal orderan
            $data_asal = [
                'invoice_id' => $invoice_id,
                'source_id'  => $this->request->getPost('asal_orderan'),
                'label'      => ($this->request->getPost('label') !== '' ? $this->request->getPost('label') : null),
            ];
            $db->table('label_invoice')->insert($data_asal);

            // simpan produk; baris yang nomornya (key = id_beli) masih ada di-update
            // supaya ukuran yang sudah tercatat tetap nempel di itemnya
            $produk = produk_dibeli($this->request->getPost('produk'), $invoice_id);
            $peta   = sinkron_dibeli($invoice_id, $produk['baris']);
            $galat  = array_merge(
                simpan_ukuran_produk($invoice_id, $peta, $produk['ukuran']),
                simpan_anggota($invoice_id, $this->request->getPost('anggota_dikirim'), $this->request->getPost('anggota'))
            );

            if ($galat === []) {
                // hapus biaya
                $db->table('biaya')->delete(['invoice_id' => $invoice_id]);

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

                // total bisa berubah sementara pembayarannya sudah ada, jadi statusnya dihitung ulang
                $invModel->perbaruiStatusPembayaran($invoice_id);

                // simpan notif
                $juragan_id = $invModel->find($invoice_id)->juragan_id;
                simpan_notif(2, $juragan_id, $invoice_id);
            }

            if ($galat !== [] || ! $db->transStatus()) {
                $db->transRollback();
                $this->response->setStatusCode(406);

                return $this->response->setJSON($galat);
            }

            $db->transCommit();

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

            // progres hanya boleh diisi untuk nota di toko sendiri
            if ($this->notaMilikToko((int) $invoice_id) === null) {
                return $this->tolakToko();
            }

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
        // pelaksananya ikut tercatat: tanpa kolom ini kecepatan fulfillment tidak
        // bisa dikaitkan ke orang, dan nilai lama tetap NULL (tidak ditebak)
        $builder->set('user_id', pengguna_sesi());

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
            $invoice_id      = $this->request->getPost('invoice_id');
            $nota            = $this->notaMilikToko((int) $invoice_id);

            // status pembayaran hanya boleh diubah untuk nota di toko sendiri
            if ($nota === null) {
                return $this->tolakToko();
            }

            $data = [
                'id_pembayaran' => $this->request->getPost('id_pembayaran'),
                'status'        => $status,
                'tanggal_cek'   => time(),
            ];
            $pembayaranModel->save($data);

            // simpan notif
            simpan_notif(($status === '3' ? 5 : 6), $nota->juragan_id, $invoice_id);

            // update status pembayaran (invoice)
            $invModel->perbaruiStatusPembayaran($invoice_id);

            $res = [
                'status' => 'data tersimpan',
                'url'    => site_url('admin/invoices/lihat/semua/semua?cari[kolom]=id&cari[q]=' . $invoice_id),
            ];

            return $this->response->setJSON($res);
        }
    }

    public function detail_status($invoice_id)
    {
        if ($this->request->isAJAX()) {
            // isi timeline nota toko lain tidak boleh dibaca
            if ($this->notaMilikToko((int) $invoice_id) === null) {
                return $this->tolakToko();
            }

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

            // riwayat pembayaran nota toko lain tidak boleh dibaca
            if ($this->notaMilikToko((int) $invoice_id) === null) {
                return $this->tolakToko();
            }

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
