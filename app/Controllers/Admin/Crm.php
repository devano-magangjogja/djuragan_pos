<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Libraries\WhatsApp\WhatsAppManager;
use App\Models\CrmModel;
use Config\Database;

class Crm extends BaseController
{
    protected CrmModel $crmModel;
    protected WhatsAppManager $wa;

    public function __construct()
    {
        $this->crmModel = new CrmModel();
        $this->wa       = WhatsAppManager::getInstance();
    }

    /**
     * Dasbor Ringkasan CRM yang Bercerita
     */
    public function index()
    {
        $ringkasan  = $this->crmModel->getRingkasan();
        $reminders  = $this->crmModel->getReminders();
        $logPesan   = $this->crmModel->getLogPesan(10);
        $provider   = $this->wa->getActiveProviderName();
        $isSandbox  = ($provider === 'kapso') ? (int) $this->wa->getSetting('kapso_sandbox_mode', '1') : 0;

        // Ambil beberapa follow-up aktif hari ini
        $followupHariIni = $this->crmModel->getFollowupList(['status' => 'menunggu'], 5);

        $data = [
            'title'           => 'CRM & WhatsApp Center',
            'ringkasan'       => $ringkasan,
            'reminders'       => array_slice($reminders, 0, 6),
            'followupHariIni' => $followupHariIni,
            'logPesan'        => $logPesan,
            'provider'        => $provider,
            'isSandbox'       => $isSandbox,
        ];

        return view('admin/crm/dasbor', $data);
    }

    /**
     * Halaman Manajemen & Segmentasi Pelanggan (dengan filter status & tag diperkuat)
     */
    public function pelanggan()
    {
        $segmen = $this->request->getGet('segmen') ?? 'semua';
        $status = $this->request->getGet('status') ?? 'semua';
        $tagId  = (int) ($this->request->getGet('tag_id') ?? 0);
        $cari   = trim($this->request->getGet('cari') ?? '');
        $page   = max(1, (int) ($this->request->getGet('page') ?? 1));
        $limit  = 25;
        $offset = ($page - 1) * $limit;

        $filters = [
            'segmen' => $segmen,
            'status' => $status,
            'tag_id' => $tagId,
            'cari'   => $cari,
        ];

        $daftarPelanggan = $this->crmModel->getDaftarPelanggan($filters, $limit, $offset);
        $totalData       = $this->crmModel->countDaftarPelanggan($filters);
        $totalPages      = ceil($totalData / $limit);
        $allTags         = $this->crmModel->getAllTags();

        $data = [
            'title'           => 'CRM - Data Pelanggan',
            'daftarPelanggan' => $daftarPelanggan,
            'segmen'          => $segmen,
            'status'          => $status,
            'tagId'           => $tagId,
            'allTags'         => $allTags,
            'cari'            => $cari,
            'page'            => $page,
            'totalPages'      => $totalPages,
            'totalData'       => $totalData,
        ];

        return view('admin/crm/pelanggan', $data);
    }

    /**
     * Halaman Customer 360 / Profil Detail Pelanggan
     */
    public function pelanggan_detail(int $id)
    {
        $pelanggan = $this->crmModel->getPelangganDetail($id);
        if (!$pelanggan) {
            return redirect()->to(site_url('admin/crm/pelanggan'))->with('error', 'Pelanggan tidak ditemukan.');
        }

        // Ringkasan nilai & kontribusi
        $ringkasanNilai = $this->crmModel->getRingkasanNilaiPelanggan($id);

        // Riwayat transaksi POS & invoice
        $transaksiList = $this->crmModel->getRiwayatTransaksiPelanggan($id);

        // Riwayat mutasi pembayaran
        $pembayaranList = $this->crmModel->getRiwayatPembayaranPelanggan($id);

        // Timeline aktivitas multi-channel
        $timeline = $this->crmModel->getTimelinePelanggan($id);

        // Follow-up khusus customer ini
        $followupList = $this->crmModel->getFollowupList(['pelanggan_id' => $id], 50);

        // Master tag untuk pilihan tambah tag
        $allTags = $this->crmModel->getAllTags();

        // Template pesan cepat
        $db = Database::connect();
        $templates = $db->table('crm_template')->get()->getResultArray();

        $data = [
            'title'          => 'Customer 360 - ' . $pelanggan['nama_pelanggan'],
            'pelanggan'      => $pelanggan,
            'nilai'          => $ringkasanNilai,
            'transaksiList'  => $transaksiList,
            'pembayaranList' => $pembayaranList,
            'timeline'       => $timeline,
            'followupList'   => $followupList,
            'allTags'        => $allTags,
            'templates'      => $templates,
        ];

        return view('admin/crm/pelanggan_detail', $data);
    }

    /**
     * Simpan status CRM, Opt-in broadcast, dan Catatan Customer
     */
    public function simpan_profil(int $id)
    {
        $statusCrm       = $this->request->getPost('status_crm');
        $optInBroadcast  = $this->request->getPost('opt_in_broadcast') !== null ? (int) $this->request->getPost('opt_in_broadcast') : 1;
        $catatan         = trim($this->request->getPost('catatan') ?? '');

        $this->crmModel->simpanProfilCrm($id, [
            'status_crm'       => $statusCrm,
            'opt_in_broadcast' => $optInBroadcast,
            'catatan'          => $catatan,
        ]);

        // Catat di timeline jika catatan baru diisi
        $catatanBaru = trim($this->request->getPost('catatan_baru') ?? '');
        if (!empty($catatanBaru)) {
            $user = pengguna_sesi();
            $this->crmModel->tambahAktivitas([
                'pelanggan_id' => $id,
                'tipe'         => 'catatan',
                'judul'        => 'Catatan Staff Ditambahkan',
                'deskripsi'    => $catatanBaru,
                'user_id'      => $user,
            ]);
        }

        if ($this->request->isAJAX()) {
            return $this->response->setJSON(['status' => 'success', 'message' => 'Profil CRM berhasil diperbarui.']);
        }

        return redirect()->back()->with('pesan', 'Profil customer berhasil diperbarui.');
    }

    /**
     * Simpan Data Ukuran dan Preferensi Pakaian Customer
     */
    public function simpan_ukuran(int $id)
    {
        $detailUkuran = [
            // Atasan / Baju / Jas
            'lingkar_dada'     => trim($this->request->getPost('lingkar_dada') ?? ''),
            'panjang_baju'     => trim($this->request->getPost('panjang_baju') ?? ''),
            'lebar_bahu'       => trim($this->request->getPost('lebar_bahu') ?? ''),
            'panjang_lengan'   => trim($this->request->getPost('panjang_lengan') ?? ''),
            'lingkar_leher'    => trim($this->request->getPost('lingkar_leher') ?? ''),
            'panjang_jas'      => trim($this->request->getPost('panjang_jas') ?? ''),
            // Bawahan / Celana
            'lingkar_pinggang' => trim($this->request->getPost('lingkar_pinggang') ?? ''),
            'panjang_celana'   => trim($this->request->getPost('panjang_celana') ?? ''),
            'lingkar_paha'     => trim($this->request->getPost('lingkar_paha') ?? ''),
            'lingkar_pinggul'  => trim($this->request->getPost('lingkar_pinggul') ?? ''),
            'pesak'            => trim($this->request->getPost('pesak') ?? ''),
            'lingkar_kaki'     => trim($this->request->getPost('lingkar_kaki') ?? ''),
            // Catatan kustom
            'catatan_ukuran'   => trim($this->request->getPost('catatan_ukuran') ?? ''),
        ];

        $payload = [
            'ukuran_baju_standar'   => trim($this->request->getPost('ukuran_baju_standar') ?? ''),
            'ukuran_celana_standar' => trim($this->request->getPost('ukuran_celana_standar') ?? ''),
            'ukuran_jas_standar'    => trim($this->request->getPost('ukuran_jas_standar') ?? ''),
            'preferensi_warna'      => trim($this->request->getPost('preferensi_warna') ?? ''),
            'preferensi_bahan'      => trim($this->request->getPost('preferensi_bahan') ?? ''),
            'preferensi_model'      => trim($this->request->getPost('preferensi_model') ?? ''),
            'detail_ukuran'         => $detailUkuran,
        ];

        $this->crmModel->simpanUkuranPreferensi($id, $payload);

        // Catat ke timeline
        $user = pengguna_sesi();
        $this->crmModel->tambahAktivitas([
            'pelanggan_id' => $id,
            'tipe'         => 'fitting',
            'judul'        => 'Data Ukuran & Preferensi Pakaian Diperbarui',
            'deskripsi'    => 'Data ukuran tubuh dan preferensi model/bahan diperbarui oleh staff.',
            'user_id'      => $user,
        ]);

        if ($this->request->isAJAX()) {
            return $this->response->setJSON(['status' => 'success', 'message' => 'Data ukuran & preferensi berhasil disimpan.']);
        }

        return redirect()->back()->with('pesan', 'Data ukuran & preferensi pakaian berhasil disimpan.');
    }

    /**
     * Tambah Aktivitas Timeline (Fitting, Telepon, Kunjungan, dll)
     */
    public function tambah_aktivitas(int $id)
    {
        $tipe      = $this->request->getPost('tipe') ?? 'catatan';
        $judul     = trim($this->request->getPost('judul') ?? '');
        $deskripsi = trim($this->request->getPost('deskripsi') ?? '');
        $invoiceId = (int) ($this->request->getPost('invoice_id') ?? 0);
        $user      = pengguna_sesi();

        if (empty($judul)) {
            $judul = ucfirst($tipe) . ' Customer';
        }

        $this->crmModel->tambahAktivitas([
            'pelanggan_id' => $id,
            'tipe'         => $tipe,
            'judul'        => $judul,
            'deskripsi'    => $deskripsi,
            'invoice_id'   => $invoiceId > 0 ? $invoiceId : null,
            'user_id'      => $user,
        ]);

        if ($this->request->isAJAX()) {
            return $this->response->setJSON(['status' => 'success', 'message' => 'Aktivitas berhasil dicatat ke timeline.']);
        }

        return redirect()->back()->with('pesan', 'Aktivitas berhasil ditambahkan ke timeline.');
    }

    /**
     * Tambah Tag ke Pelanggan
     */
    public function tambah_tag(int $id)
    {
        $tagId    = (int) $this->request->getPost('tag_id');
        $namaBaru = trim($this->request->getPost('nama_tag_baru') ?? '');
        $warna    = trim($this->request->getPost('warna') ?? 'primary');

        if ($tagId > 0) {
            $this->crmModel->tambahTagPelanggan($id, $tagId);
        } elseif (!empty($namaBaru)) {
            $newTagId = $this->crmModel->buatMasterTag($namaBaru, $warna);
            $this->crmModel->tambahTagPelanggan($id, $newTagId);
        }

        return redirect()->back()->with('pesan', 'Tag customer berhasil diperbarui.');
    }

    /**
     * Hapus Tag dari Pelanggan
     */
    public function hapus_tag(int $id, int $tag_id)
    {
        $this->crmModel->hapusTagPelanggan($id, $tag_id);

        if ($this->request->isAJAX()) {
            return $this->response->setJSON(['status' => 'success']);
        }

        return redirect()->back()->with('pesan', 'Tag berhasil dihapus.');
    }

    /**
     * Halaman Follow-up Terpadu: Follow-up Customer, Follow-up Tagihan, dan Reminder
     */
    public function followup()
    {
        $tab    = $this->request->getGet('tab') ?? 'tagihan'; // tagihan, reminder, customer
        $cari   = trim($this->request->getGet('cari') ?? '');
        $status = $this->request->getGet('status') ?? 'menunggu';

        // 1. Data Follow-up Customer
        $followupList = $this->crmModel->getFollowupList([
            'tipe'   => 'customer',
            'status' => $status !== 'semua' ? $status : '',
            'cari'   => $cari,
        ], 50);

        // 2. Data Follow-up Tagihan
        $page                 = max(1, (int) ($this->request->getGet('page') ?? 1));
        $limit                = 25;
        $offset               = ($page - 1) * $limit;
        $invoicesTagihan      = $this->crmModel->getInvoiceBelumLunas($cari, $limit, $offset);
        $totalInvoicesTagihan = $this->crmModel->countInvoiceBelumLunas($cari);
        $totalPagesTagihan    = (int) ceil($totalInvoicesTagihan / $limit);

        // 3. Data Reminder Otomatis
        $reminders = $this->crmModel->getReminders();

        // Template tagihan
        $db = Database::connect();
        $templateTagihan = $db->table('crm_template')->where('kode', 'tagihan_belum_lunas')->get()->getRowArray();

        $data = [
            'title'                => 'Pusat Follow-up & Reminder CRM',
            'tab'                  => $tab,
            'cari'                 => $cari,
            'status'               => $status,
            'page'                 => $page,
            'limit'                => $limit,
            'totalPagesTagihan'    => $totalPagesTagihan,
            'followupList'         => $followupList,
            'invoicesTagihan'      => $invoicesTagihan,
            'totalInvoicesTagihan' => $totalInvoicesTagihan,
            'reminders'            => $reminders,
            'templateTagihan'      => $templateTagihan['pesan'] ?? '',
        ];

        return view('admin/crm/followup', $data);
    }

    /**
     * Simpan Follow-up Baru
     */
    public function simpan_followup()
    {
        $pelangganId = (int) $this->request->getPost('pelanggan_id');
        $judul       = trim($this->request->getPost('judul') ?? '');
        $kategori    = trim($this->request->getPost('kategori') ?? 'lainnya');
        $catatan     = trim($this->request->getPost('catatan') ?? '');
        $tglTempo    = $this->request->getPost('tanggal_jatuh_tempo') ?: date('Y-m-d');
        $invoiceId   = (int) ($this->request->getPost('invoice_id') ?? 0);
        $user        = pengguna_sesi();

        if (empty($pelangganId) || empty($judul)) {
            return redirect()->back()->with('error', 'Pelanggan dan Judul follow-up wajib diisi.');
        }

        $this->crmModel->simpanFollowup([
            'pelanggan_id'        => $pelangganId,
            'tipe'                => 'customer',
            'kategori'            => $kategori,
            'judul'               => $judul,
            'catatan'             => $catatan,
            'tanggal_jatuh_tempo' => $tglTempo,
            'invoice_id'          => $invoiceId > 0 ? $invoiceId : null,
            'user_id'             => $user,
        ]);

        return redirect()->back()->with('pesan', 'Jadwal follow-up berhasil dibuat.');
    }

    /**
     * Selesaikan Follow-up
     */
    public function selesaikan_followup(int $id)
    {
        $user    = pengguna_sesi();
        $catatan = trim($this->request->getPost('catatan_penyelesaian') ?? '');

        $this->crmModel->selesaikanFollowup($id, $user, $catatan);

        if ($this->request->isAJAX()) {
            return $this->response->setJSON(['status' => 'success', 'message' => 'Follow-up berhasil ditandai selesai.']);
        }

        return redirect()->back()->with('pesan', 'Follow-up berhasil diselesaikan.');
    }

    /**
     * Kirim Pesan Langsung dari Profil Customer 360
     */
    public function kirim_pesan_pelanggan(int $id)
    {
        $nomor = trim($this->request->getPost('nomor') ?? '');
        $pesan = trim($this->request->getPost('pesan') ?? '');

        if (empty($nomor) || empty($pesan)) {
            return $this->response->setJSON(['status' => 'error', 'message' => 'Nomor dan pesan wajib diisi.']);
        }

        $user = pengguna_sesi();

        $pesan = crm_isi_token($pesan, $this->crmModel->getTokenPesan((int) $id));

        $res = $this->wa->send($nomor, $pesan, [
            'pelanggan_id' => $id,
            'tipe_pesan'   => 'manual',
            'user_id'      => $user,
        ]);

        if ($res['success']) {
            // Catat ke timeline
            $this->crmModel->tambahAktivitas([
                'pelanggan_id' => $id,
                'tipe'         => 'wa',
                'judul'        => 'Pesan WhatsApp Terkirim',
                'deskripsi'    => $pesan,
                'user_id'      => $user,
            ]);

            return $this->response->setJSON([
                'status'  => 'success',
                'message' => 'Pesan WhatsApp berhasil terkirim ke customer!',
                'notice'  => $res['notice'] ?? null,
            ]);
        }

        return $this->response->setJSON([
            'status'  => 'error',
            'message' => 'Gagal mengirim pesan: ' . ($res['error'] ?? 'Periksa koneksi WhatsApp.'),
        ]);
    }

    /**
     * Halaman Deteksi & Merge Customer Duplikat
     */
    public function duplikat()
    {
        $duplikatList = $this->crmModel->getDuplikatList();

        $data = [
            'title'        => 'Deteksi & Penggabungan Akun Duplikat',
            'duplikatList' => $duplikatList,
        ];

        return view('admin/crm/duplikat', $data);
    }

    /**
     * Eksekusi Merge Dua Customer
     */
    public function proses_merge()
    {
        $idUtama    = (int) $this->request->getPost('id_utama');
        $idDuplikat = (int) $this->request->getPost('id_duplikat');
        $user       = pengguna_sesi();

        if (empty($idUtama) || empty($idDuplikat)) {
            return $this->response->setJSON(['status' => 'error', 'message' => 'Pilih customer utama dan customer yang akan digabung.']);
        }

        $res = $this->crmModel->mergeCustomer($idUtama, $idDuplikat, $user);

        if ($res['success']) {
            return $this->response->setJSON(['status' => 'success', 'message' => $res['message']]);
        }

        return $this->response->setJSON(['status' => 'error', 'message' => $res['message']]);
    }

    /**
     * AJAX: Cari Customer Otomatis dari Nomor WhatsApp (Untuk Live Chat)
     */
    public function cek_kontak_wa()
    {
        $nomor = trim($this->request->getGet('nomor') ?? '');
        if (empty($nomor)) {
            return $this->response->setJSON(['status' => 'error', 'message' => 'Nomor kosong.']);
        }

        $pelanggan = $this->crmModel->cariCustomerByWa($nomor);
        if ($pelanggan) {
            $nilai = $this->crmModel->getRingkasanNilaiPelanggan((int) $pelanggan['id_pelanggan']);
            return $this->response->setJSON([
                'status'    => 'found',
                'pelanggan' => [
                    'id_pelanggan'     => $pelanggan['id_pelanggan'],
                    'nama_pelanggan'   => $pelanggan['nama_pelanggan'],
                    'nomor_utama'      => $pelanggan['nomor_utama'],
                    'status_crm'       => $pelanggan['status_crm'],
                    'total_transaksi'  => $nilai['total_transaksi'],
                    'total_pengeluaran'=> number_format($nilai['total_pengeluaran'], 0, ',', '.'),
                    'sisa_piutang'     => number_format($nilai['sisa_piutang'], 0, ',', '.'),
                    'count_penjualan'  => $nilai['count_penjualan'],
                    'count_custom'     => $nilai['count_custom'],
                    'count_sewa'       => $nilai['count_sewa'],
                    'transaksi_terakhir'=> $nilai['transaksi_terakhir'],
                ],
            ]);
        }

        return $this->response->setJSON(['status' => 'not_found', 'message' => 'Nomor ini belum terdaftar sebagai customer.']);
    }

    /**
     * AJAX: Buat Customer Baru Langsung dari Live Chat
     */
    public function buat_customer_wa()
    {
        $nomor  = trim($this->request->getPost('nomor') ?? '');
        $nama   = trim($this->request->getPost('nama') ?? '');
        $alamat = trim($this->request->getPost('alamat') ?? '');
        $user   = pengguna_sesi();

        if (empty($nomor) || empty($nama)) {
            return $this->response->setJSON(['status' => 'error', 'message' => 'Nama dan nomor WhatsApp wajib diisi.']);
        }

        $res = $this->crmModel->buatCustomerDariWa($nomor, $nama, '', $alamat, $user);

        if ($res['success']) {
            return $this->response->setJSON([
                'status'    => 'success',
                'message'   => $res['message'],
                'pelanggan' => $res['data'],
            ]);
        }

        return $this->response->setJSON(['status' => 'error', 'message' => $res['message']]);
    }

    /**
     * Follow-up Tagihan Invoice Belum Lunas
     */
    public function tagihan()
    {
        $cari = trim($this->request->getGet('cari') ?? '');
        $invoices = $this->crmModel->getInvoiceBelumLunas($cari, 50);

        // Ambil template tagihan
        $db = Database::connect();
        $template = $db->table('crm_template')->where('kode', 'tagihan_belum_lunas')->get()->getRowArray();

        $data = [
            'title'     => 'Follow-up Tagihan Belum Lunas',
            'invoices'  => $invoices,
            'cari'      => $cari,
            'template'  => $template['pesan'] ?? '',
            'provider'  => $this->wa->getActiveProviderName(),
            'isSandbox' => ($this->wa->getActiveProviderName() === 'kapso') ? (int) $this->wa->getSetting('kapso_sandbox_mode', '1') : 0,
        ];

        return view('admin/crm/tagihan', $data);
    }

    /**
     * Notifikasi Pengiriman & Resi
     */
    public function pengiriman()
    {
        $cari = trim($this->request->getGet('cari') ?? '');
        $invoices = $this->crmModel->getInvoicePengiriman($cari, 50);

        $db = Database::connect();
        $template = $db->table('crm_template')->where('kode', 'notifikasi_resi')->get()->getRowArray();

        $data = [
            'title'     => 'Notifikasi Resi Pengiriman',
            'invoices'  => $invoices,
            'cari'      => $cari,
            'template'  => $template['pesan'] ?? '',
            'provider'  => $this->wa->getActiveProviderName(),
            'isSandbox' => ($this->wa->getActiveProviderName() === 'kapso') ? (int) $this->wa->getSetting('kapso_sandbox_mode', '1') : 0,
        ];

        return view('admin/crm/pengiriman', $data);
    }

    /**
     * Broadcast Promo & Pesan Massal (Memperhatikan Opt-In Broadcast)
     */
    public function broadcast()
    {
        $db = Database::connect();
        $template = $db->table('crm_template')->where('kode', 'broadcast_promo')->get()->getRowArray();
        $riwayatBroadcast = $db->table('crm_broadcast')->orderBy('id_broadcast', 'DESC')->get(15)->getResultArray();

        $provider = $this->wa->getActiveProviderName();
        $isSandbox = ($provider === 'kapso') ? (int) $this->wa->getSetting('kapso_sandbox_mode', '1') : 0;

        $data = [
            'title'            => 'Broadcast Promo WhatsApp',
            'template'         => $template['pesan'] ?? '',
            'riwayatBroadcast' => $riwayatBroadcast,
            'provider'         => $provider,
            'sandboxNumber'    => $this->wa->getSetting('kapso_sandbox_test_number', '6285161384750'),
            'isSandbox'        => $isSandbox,
        ];

        return view('admin/crm/broadcast', $data);
    }

    /**
     * Eksekusi Kirim Broadcast Promo (Filter Opt-in Broadcast)
     */
    public function kirim_broadcast()
    {
        $segmen = $this->request->getPost('segmen') ?? 'semua';
        $judul  = trim($this->request->getPost('judul') ?? 'Broadcast Promo');
        $pesan  = trim($this->request->getPost('pesan') ?? '');

        if (empty($pesan)) {
            return $this->response->setJSON(['status' => 'error', 'message' => 'Isi pesan broadcast wajib diisi.']);
        }

        $provider  = $this->wa->getActiveProviderName();
        $isSandbox = ($provider === 'kapso') ? (int) $this->wa->getSetting('kapso_sandbox_mode', '1') : 0;
        $sandboxNumber = $this->wa->getSetting('kapso_sandbox_test_number', '6285161384750');

        $daftarPelanggan = $this->crmModel->getDaftarPelanggan(['segmen' => $segmen], 100, 0);

        // Filter Opt-In Broadcast: Jangan kirim ke customer yang opt_in_broadcast === 0
        $daftarPelanggan = array_filter($daftarPelanggan, static function ($p) {
            return !isset($p['opt_in_broadcast']) || (int) $p['opt_in_broadcast'] === 1;
        });
        $daftarPelanggan = array_values($daftarPelanggan);

        if (empty($daftarPelanggan)) {
            return $this->response->setJSON(['status' => 'error', 'message' => 'Tidak ada kontak pelanggan yang menerima promo (opt-in) untuk segmen ini.']);
        }

        $totalTerkirim = 0;
        $totalGagal    = 0;

        if ($isSandbox === 1) {
            $contohPelanggan = $daftarPelanggan[0];
            $pesanFinal = crm_isi_token($pesan, [
                'nama'        => $contohPelanggan['nama_pelanggan'],
                'total_order' => $contohPelanggan['jumlah_order'],
            ]);

            $pesanSandbox = "[MODE SANDBOX - Target Sampel: {$contohPelanggan['nama_pelanggan']} ({$contohPelanggan['nomor_utama']})]\n\n" . $pesanFinal;
            $res = $this->wa->send($sandboxNumber, $pesanSandbox, [
                'pelanggan_id' => $contohPelanggan['id_pelanggan'],
                'tipe_pesan'   => 'broadcast',
            ]);

            if ($res['success']) {
                $totalTerkirim = 1;
            } else {
                $totalGagal = 1;
            }
        } else {
            foreach ($daftarPelanggan as $p) {
                if (empty($p['nomor_utama'])) {
                    continue;
                }

                $pesanFinal = crm_isi_token($pesan, [
                    'nama'        => $p['nama_pelanggan'],
                    'total_order' => $p['jumlah_order'],
                ]);

                $res = $this->wa->send($p['nomor_utama'], $pesanFinal, [
                    'pelanggan_id' => $p['id_pelanggan'],
                    'tipe_pesan'   => 'broadcast',
                ]);

                if ($res['success']) {
                    $totalTerkirim++;
                } else {
                    $totalGagal++;
                }
            }
        }

        $db = Database::connect();
        $db->table('crm_broadcast')->insert([
            'judul'          => $judul,
            'segmen'         => $segmen,
            'pesan'          => $pesan,
            'total_target'   => count($daftarPelanggan),
            'total_terkirim' => $totalTerkirim,
            'total_gagal'    => $totalGagal,
            'user_id'        => pengguna_sesi(),
            'created_at'     => time(),
        ]);

        return $this->response->setJSON([
            'status'  => 'success',
            'message' => "Broadcast selesai diproses. Terkirim: {$totalTerkirim}, Gagal: {$totalGagal}." . ($isSandbox ? ' (Mode Sandbox aktif: dikirim ke nomor sandbox)' : ''),
        ]);
    }

    /**
     * Riwayat Pesan WhatsApp
     */
    public function log()
    {
        $logPesan = $this->crmModel->getLogPesan(100);

        $data = [
            'title'    => 'Riwayat Pengiriman Pesan WhatsApp',
            'logPesan' => $logPesan,
        ];

        return view('admin/crm/log', $data);
    }

    /**
     * Template Pesan CRM
     */
    public function template()
    {
        $db = Database::connect();
        $templates = $db->table('crm_template')->orderBy('id_template', 'ASC')->get()->getResultArray();

        $data = [
            'title'     => 'Template Pesan WhatsApp CRM',
            'templates' => $templates,
        ];

        return view('admin/crm/template', $data);
    }

    /**
     * Simpan / Perbarui Template Pesan
     */
    public function simpan_template()
    {
        $idTemplate = (int) $this->request->getPost('id_template');
        $judul      = trim($this->request->getPost('judul') ?? '');
        $pesan      = trim($this->request->getPost('pesan') ?? '');

        if (empty($judul) || empty($pesan)) {
            return $this->response->setJSON(['status' => 'error', 'message' => 'Judul dan isi pesan wajib diisi.']);
        }

        $db = Database::connect();
        $db->table('crm_template')->where('id_template', $idTemplate)->update([
            'judul'      => $judul,
            'pesan'      => $pesan,
            'updated_at' => time(),
        ]);

        return $this->response->setJSON(['status' => 'success', 'message' => 'Template pesan berhasil diperbarui.']);
    }

    /**
     * Pengaturan Gateway WhatsApp
     */
    public function pengaturan()
    {
        $settings = $this->wa->loadSettings();

        $data = [
            'title'    => 'Pengaturan Gateway WhatsApp',
            'settings' => $settings,
        ];

        return view('admin/crm/pengaturan', $data);
    }

    /**
     * Simpan Pengaturan Gateway WhatsApp
     */
    public function simpan_pengaturan()
    {
        $keys = [
            'wa_provider',
            'kapso_api_key',
            'kapso_phone_number_id',
            'kapso_base_url',
            'kapso_sandbox_mode',
            'kapso_sandbox_test_number',
            'fonnte_token',
            'fonnte_base_url',
        ];

        foreach ($keys as $key) {
            $val = $this->request->getPost($key);
            if ($val !== null) {
                $this->wa->setSetting($key, trim((string) $val));
            }
        }

        return $this->response->setJSON([
            'status'  => 'success',
            'message' => 'Pengaturan Gateway WhatsApp berhasil disimpan.',
        ]);
    }

    /**
     * Tes Koneksi / Kirim Pesan Uji Coba
     */
    public function tes_koneksi()
    {
        $nomor = trim($this->request->getPost('nomor') ?? '');
        $pesan = trim($this->request->getPost('pesan') ?? 'Halo! Ini adalah pesan pengujian koneksi WhatsApp CRM Djuragan POS.');
        $provider = $this->wa->getActiveProviderName();

        if (empty($nomor)) {
            if ($provider === 'kapso') {
                $nomor = $this->wa->getSetting('kapso_sandbox_test_number', '6285161384750');
            } else {
                return $this->response->setJSON(['status' => 'error', 'message' => 'Nomor WhatsApp tujuan pengetesan wajib diisi.']);
            }
        }

        $res = $this->wa->send($nomor, $pesan, [
            'tipe_pesan' => 'manual',
        ]);

        if ($res['success']) {
            return $this->response->setJSON([
                'status'     => 'success',
                'message'    => 'Pesan WhatsApp berhasil dikirim via ' . ucfirst($res['provider'] ?? $provider) . '!',
                'message_id' => $res['message_id'],
                'provider'   => $res['provider'] ?? $provider,
                'notice'     => $res['notice'] ?? null,
            ]);
        }

        return $this->response->setJSON([
            'status'  => 'error',
            'message' => 'Gagal mengirim pesan: ' . ($res['error'] ?? 'Periksa pengaturan API WhatsApp.'),
        ]);
    }

    /**
     * AJAX: Cek Status Koneksi Perangkat Fonnte
     */
    public function cek_status_fonnte()
    {
        $res = $this->wa->getFonnteDeviceStatus();
        return $this->response->setJSON($res);
    }

    /**
     * AJAX: Ambil QR Code Fonnte untuk Pairing
     */
    public function ambil_qr_fonnte()
    {
        $res = $this->wa->getFonnteQr();
        return $this->response->setJSON($res);
    }

    /**
     * AJAX: Status jalur pesan masuk (webhook) untuk panel di Live Chat.
     * Menjawab dua hal: gateway-nya pernah mengirim tidak, dan kalau mengirim
     * pesannya tercatat tidak.
     */
    public function webhook_status()
    {
        $db    = Database::connect();
        $event = $this->wa->eventWebhookTerakhir();

        $masukTerakhir = $db->table('crm_chat_pesan')
            ->select('created_at, nomor_wa')
            ->where('arah', 'masuk')
            ->groupStart()
                ->where('wa_message_id', null)
                ->orWhere('wa_message_id NOT LIKE', 'selftest-%')
            ->groupEnd()
            ->orderBy('id_pesan', 'DESC')
            ->get(1)
            ->getRowArray();

        $jumlahHariIni = $db->table('crm_chat_pesan')
            ->where('arah', 'masuk')
            ->where('created_at >=', strtotime('today'))
            ->countAllResults();

        return $this->response->setJSON([
            'status' => 'success',
            'data'   => [
                'provider'    => $this->wa->getActiveProviderName(),
                'url_webhook' => site_url('webhook/whatsapp'),
                'event'       => $event === null ? null : [
                    'waktu'    => (int) $event['waktu'],
                    'sumber'   => $event['sumber'] ?? '-',
                    'nomor'    => $event['nomor'] ?? null,
                    'diproses' => (bool) ($event['diproses'] ?? false),
                ],
                'masuk_terakhir' => $masukTerakhir === null ? null : [
                    'waktu' => (int) $masukTerakhir['created_at'],
                    'nomor' => $masukTerakhir['nomor_wa'],
                ],
                'masuk_hari_ini' => $jumlahHariIni,
            ],
        ]);
    }

    /**
     * AJAX: Uji jalur pencatatan pesan masuk.
     *
     * Payload contoh dijalankan lewat jalur yang sama dengan webhook
     * (recordIncomingMessage → crm_chat_pesan → Live Chat), lalu baris ujinya
     * dibersihkan lagi supaya daftar percakapan tidak kotor. Sengaja tidak
     * memanggil URL publik dari dalam server: dev server PHP cuma punya satu
     * pekerja, jadi permintaan bersarang menggantung sampai timeout. Apakah URL
     * publik terjangkau gateway terlihat dari baris "Event gateway terakhir".
     */
    public function webhook_uji()
    {
        $idUji    = 'selftest-' . time();
        $nomorUji = '628999000111';
        $isi      = 'Uji pencatatan pesan masuk dari panel Live Chat';

        $this->wa->recordIncomingMessage($nomorUji, $isi, $idUji, ['selftest' => true], 'Uji Panel Webhook');

        $db   = Database::connect();
        $baru = $db->table('crm_chat_pesan')
            ->select('id_pesan, percakapan_id')
            ->where('wa_message_id', $idUji)
            ->where('arah', 'masuk')
            ->get()->getRowArray();

        if ($baru === null) {
            return $this->response->setJSON([
                'status'  => 'error',
                'message' => 'Payload uji tidak tercatat di crm_chat_pesan. Periksa log aplikasi.',
                'data'    => ['tercatat' => false, 'dibersihkan' => false],
            ]);
        }

        $idPercakapan = (int) $baru['percakapan_id'];
        $db->table('crm_chat_pesan')->where('id_pesan', (int) $baru['id_pesan'])->delete();

        // percakapan ikut dibuang hanya kalau isinya memang pesan uji itu saja
        $sisa = $db->table('crm_chat_pesan')->where('percakapan_id', $idPercakapan)->countAllResults();
        if ($sisa === 0) {
            $db->table('crm_chat_percakapan')->where('id_percakapan', $idPercakapan)->delete();
        }

        return $this->response->setJSON([
            'status'  => 'success',
            'message' => 'Jalur pencatatan sehat: pesan uji masuk ke Live Chat lalu dibersihkan. Kalau balasan asli tetap tidak muncul, gateway-nya yang belum mengirim ke URL webhook.',
            'data'    => ['tercatat' => true, 'dibersihkan' => true],
        ]);
    }

    /**
     * Alias endpoint kirim_wa → kirim_pesan (kompatibilitas route lama)
     */
    public function kirim_wa()
    {
        return $this->kirim_pesan();
    }

    /**
     * Kirim Pesan Manual / Follow-up Satuan
     */
    public function kirim_pesan()
    {
        $idInvoice = (int) $this->request->getPost('id_invoice');
        $nomor     = trim($this->request->getPost('nomor') ?? '');
        $pesan     = trim($this->request->getPost('pesan') ?? '');
        $tipePesan = $this->request->getPost('tipe_pesan') ?? 'tagihan';

        if (empty($nomor) || empty($pesan)) {
            return $this->response->setJSON(['status' => 'error', 'message' => 'Nomor tujuan dan isi pesan wajib diisi.']);
        }

        $inv = null;
        if ($idInvoice > 0) {
            $inv = $this->crmModel->getInvoiceDetailPesan($idInvoice);
        }

        $pelangganId = $inv['id_pelanggan'] ?? null;

        // Token {nama} {invoice} {total} {sisa} {link_invoice} {kurir} {resi} diisi
        // di sini juga, bukan hanya di JavaScript, supaya pesan yang tertinggal
        // ber-token (misalnya dikirim lewat tab lama) tidak pernah sampai ke
        // pelanggan dalam bentuk kurung kurawal.
        $pesan = crm_isi_token($pesan, $this->crmModel->getTokenPesan((int) $pelangganId, $idInvoice));

        $res = $this->wa->send($nomor, $pesan, [
            'invoice_id'   => $idInvoice > 0 ? $idInvoice : null,
            'pelanggan_id' => $pelangganId,
            'tipe_pesan'   => $tipePesan,
        ]);

        if ($res['success']) {
            if ($pelangganId) {
                $this->crmModel->tambahAktivitas([
                    'pelanggan_id' => $pelangganId,
                    'tipe'         => 'wa',
                    'judul'        => 'Follow-up WhatsApp Terkirim (' . ucfirst($tipePesan) . ')',
                    'deskripsi'    => $pesan,
                    'invoice_id'   => $idInvoice > 0 ? $idInvoice : null,
                    'user_id'      => pengguna_sesi(),
                ]);
            }

            return $this->response->setJSON([
                'status'     => 'success',
                'message'    => 'Pesan WhatsApp berhasil terkirim!',
                'message_id' => $res['message_id'],
                'notice'     => $res['notice'] ?? null,
            ]);
        }

        return $this->response->setJSON([
            'status'  => 'error',
            'message' => 'Gagal mengirim pesan: ' . ($res['error'] ?? 'Periksa konfigurasi WhatsApp API.'),
        ]);
    }

    /**
     * Halaman Live Chat WhatsApp
     */
    public function chat()
    {
        $activeId = (int) ($this->request->getGet('id') ?? 0);
        $db = Database::connect();

        $templates     = $db->table('crm_template')->get()->getResultArray();
        $isSandbox     = (int) $this->wa->getSetting('kapso_sandbox_mode', '1');

        $data = [
            'title'         => 'Live Chat WhatsApp CRM',
            'activeId'      => $activeId,
            'templates'     => $templates,
            'isSandbox'     => $isSandbox,
        ];

        return view('admin/crm/chat', $data);
    }

    /**
     * API: Ambil daftar percakapan Live Chat (JSON)
     */
    public function chat_percakapan()
    {
        $db = Database::connect();
        $q  = trim($this->request->getGet('q') ?? '');

        $builder = $db->table('crm_chat_percakapan cp')
            ->select('cp.*, p.nama_pelanggan, pro.status_crm')
            ->join('pelanggan p', 'p.id_pelanggan = cp.pelanggan_id', 'left')
            ->join('crm_pelanggan_profil pro', 'pro.pelanggan_id = cp.pelanggan_id', 'left')
            ->orderBy('cp.waktu_terakhir', 'DESC');

        if (!empty($q)) {
            $builder->groupStart()
                ->like('cp.nomor_wa', $q)
                ->orLike('cp.nama_kontak', $q)
                ->orLike('p.nama_pelanggan', $q)
                ->groupEnd();
        }

        $rows = $builder->get(60)->getResultArray();

        $list = [];
        foreach ($rows as $r) {
            $list[] = [
                'id_percakapan'  => (int) $r['id_percakapan'],
                'nomor_wa'       => $r['nomor_wa'],
                'pelanggan_id'   => $r['pelanggan_id'] ? (int) $r['pelanggan_id'] : null,
                'nama_kontak'    => $this->namaChat($r),
                'status_crm'     => $r['status_crm'] ?? null,
                'pesan_terakhir' => $r['pesan_terakhir'] ?? '',
                'arah_terakhir'  => $r['arah_terakhir'],
                'unread_admin'   => (int) $r['unread_admin'],
                'waktu'          => !empty($r['waktu_terakhir']) ? date('H:i', (int) $r['waktu_terakhir']) : '',
                'tanggal'        => !empty($r['waktu_terakhir']) ? date('d/m/Y', (int) $r['waktu_terakhir']) : '',
            ];
        }

        return $this->response->setJSON(['status' => 'success', 'data' => $list]);
    }

    /**
     * Nama yang tampil di daftar Live Chat.
     *
     * Label chat yang diisi manual lewat "Ganti nama chat" lebih diprioritaskan
     * daripada nama profil customer supaya hasil edit langsung terlihat. Label
     * bawaan sistem (kosong, nomor, atau "Kontak 4750") bukan hasil edit, jadi
     * tetap mengikuti nama profil.
     */
    private function namaChat(array $r): string
    {
        $label    = trim((string) ($r['nama_kontak'] ?? ''));
        $nomorWa  = (string) ($r['nomor_wa'] ?? '');
        $eksplisit = $label !== ''
            && $label !== $nomorWa
            && ! preg_match('/^\d+$/', $label)
            && ! preg_match('/^Kontak \d{1,4}$/', $label);

        if ($eksplisit) {
            return $label;
        }

        return ($r['nama_pelanggan'] ?? '') ?: ($label !== '' ? $label : $nomorWa);
    }

    /**
     * API: Ambil isi pesan untuk satu percakapan (JSON)
     */
    public function chat_pesan(int $id_percakapan)
    {
        $db = Database::connect();
        $lastId = (int) ($this->request->getGet('last_id') ?? 0);

        $conv = $db->table('crm_chat_percakapan')->where('id_percakapan', $id_percakapan)->get()->getRowArray();
        if (!$conv) {
            return $this->response->setJSON(['status' => 'error', 'message' => 'Percakapan tidak ditemukan.']);
        }

        if ($lastId === 0) {
            $db->table('crm_chat_percakapan')->where('id_percakapan', $id_percakapan)->update(['unread_admin' => 0]);
        }

        // Ambil data pelanggan jika ada
        $pelanggan = null;
        if ($lastId === 0) {
            if (!empty($conv['pelanggan_id'])) {
                $pelanggan = $this->crmModel->getPelangganDetail((int) $conv['pelanggan_id']);
            } else {
                // Coba auto-match nomor WA jika belum terhubung
                $autoMatch = $this->crmModel->cariCustomerByWa($conv['nomor_wa']);
                if ($autoMatch) {
                    $conv['pelanggan_id'] = $autoMatch['id_pelanggan'];
                    $db->table('crm_chat_percakapan')->where('id_percakapan', $id_percakapan)->update([
                        'pelanggan_id' => $autoMatch['id_pelanggan'],
                    ]);
                    $pelanggan = $autoMatch;
                }
            }

            if ($pelanggan) {
                $nilai = $this->crmModel->getRingkasanNilaiPelanggan((int) $pelanggan['id_pelanggan']);
                $pelanggan['ringkasan_nilai'] = $nilai;
            }
        }

        $builder = $db->table('crm_chat_pesan')
            ->where('percakapan_id', $id_percakapan)
            ->orderBy('id_pesan', 'ASC');

        if ($lastId > 0) {
            $builder->where('id_pesan >', $lastId);
        }

        $pesanRows = $builder->get(200)->getResultArray();

        $messages = [];
        foreach ($pesanRows as $m) {
            $messages[] = [
                'id_pesan'   => (int) $m['id_pesan'],
                'arah'       => $m['arah'],
                'isi_pesan'  => $m['isi_pesan'],
                'status'     => $m['status'],
                'waktu'      => date('H:i', (int) $m['created_at']),
                'tanggal'    => date('d M Y', (int) $m['created_at']),
                'timestamp'  => (int) $m['created_at'],
            ];
        }

        if ($lastId > 0 && !empty($messages)) {
            $db->table('crm_chat_percakapan')->where('id_percakapan', $id_percakapan)->update(['unread_admin' => 0]);
        }

        return $this->response->setJSON([
            'status'      => 'success',
            'incremental' => $lastId > 0,
            'percakapan'  => [
                'id'           => (int) $conv['id_percakapan'],
                'nomor_wa'     => $conv['nomor_wa'],
                'pelanggan_id' => $conv['pelanggan_id'] ? (int) $conv['pelanggan_id'] : null,
                'nama_kontak'  => $conv['nama_kontak'] ?: $conv['nomor_wa'],
            ],
            'pelanggan'   => $lastId === 0 ? $pelanggan : null,
            'messages'    => $messages,
        ]);
    }

    /**
     * API: Polling ringan pesan baru di percakapan
     */
    public function chat_polling(int $id_percakapan)
    {
        $db    = Database::connect();
        $lastId = (int) ($this->request->getGet('last_id') ?? 0);

        $latestRow = $db->table('crm_chat_pesan')
            ->select('id_pesan, arah')
            ->where('percakapan_id', $id_percakapan)
            ->orderBy('id_pesan', 'DESC')
            ->get(1)
            ->getRowArray();

        $latestId    = $latestRow ? (int) $latestRow['id_pesan'] : 0;
        $hasNewMsg   = $latestId > $lastId;
        $newMsgArah  = $hasNewMsg ? ($latestRow['arah'] ?? null) : null;

        $conv = $db->table('crm_chat_percakapan')
            ->select('unread_admin, arah_terakhir')
            ->where('id_percakapan', $id_percakapan)
            ->get()->getRowArray();

        return $this->response->setJSON([
            'status'       => 'success',
            'has_new'      => $hasNewMsg,
            'latest_id'    => $latestId,
            'new_msg_arah' => $newMsgArah,
            'unread_admin' => $conv ? (int) $conv['unread_admin'] : 0,
        ]);
    }

    /**
     * API: Kirim pesan balasan di Live Chat
     */
    public function chat_kirim()
    {
        $idPercakapan = (int) $this->request->getPost('id_percakapan');
        $pesan        = trim($this->request->getPost('pesan') ?? '');
        $nomorBaru    = trim($this->request->getPost('nomor') ?? '');

        if (empty($pesan)) {
            return $this->response->setJSON(['status' => 'error', 'message' => 'Pesan tidak boleh kosong.']);
        }

        $db = Database::connect();
        $targetNumber = null;
        $pelangganId  = null;
        $namaKontak   = '';

        if ($idPercakapan > 0) {
            $conv = $db->table('crm_chat_percakapan')->where('id_percakapan', $idPercakapan)->get()->getRowArray();
            if ($conv) {
                $targetNumber = $conv['nomor_wa'];
                $pelangganId  = $conv['pelanggan_id'];
                // Kontak yang belum jadi customer punya nama sama dengan nomornya;
                // itu bukan nama yang layak dikirim ke orang.
                $namaKontak = $conv['nama_kontak'] !== $conv['nomor_wa'] ? (string) $conv['nama_kontak'] : '';
            }
        } elseif (!empty($nomorBaru)) {
            $targetNumber = preg_replace('/[^\d]/', '', $nomorBaru);
        }

        if (empty($targetNumber)) {
            return $this->response->setJSON(['status' => 'error', 'message' => 'Nomor tujuan tidak valid.']);
        }

        if (empty($pelangganId)) {
            $autoMatch   = $this->crmModel->cariCustomerByWa($targetNumber);
            $pelangganId = $autoMatch['id_pelanggan'] ?? null;
        }

        $pesan = crm_isi_token($pesan, $this->crmModel->getTokenPesan((int) $pelangganId, 0, $namaKontak));

        $res = $this->wa->send($targetNumber, $pesan, [
            'pelanggan_id' => $pelangganId,
            'tipe_pesan'   => 'manual',
            'user_id'      => pengguna_sesi(),
        ]);

        if ($res['success']) {
            if ($pelangganId) {
                $this->crmModel->tambahAktivitas([
                    'pelanggan_id' => $pelangganId,
                    'tipe'         => 'wa',
                    'judul'        => 'Pesan WhatsApp Keluar (Live Chat)',
                    'deskripsi'    => $pesan,
                    'user_id'      => pengguna_sesi(),
                ]);
            }

            $convIdOut = $idPercakapan;
            if ($convIdOut <= 0 && !empty($targetNumber)) {
                $clean = preg_replace('/[^\d]/', '', $targetNumber);
                $c = $db->table('crm_chat_percakapan')->where('nomor_wa', $clean)->get()->getRowArray();
                if ($c) {
                    $convIdOut = (int) $c['id_percakapan'];
                }
            }

            return $this->response->setJSON([
                'status'        => 'success',
                'message'       => 'Pesan terkirim!',
                'message_id'    => $res['message_id'],
                'id_percakapan' => $convIdOut,
            ]);
        }

        return $this->response->setJSON([
            'status'  => 'error',
            'message' => 'Gagal mengirim: ' . ($res['error'] ?? 'Terjadi kesalahan pada WhatsApp API.'),
        ]);
    }

    /**
     * API: Hapus satu percakapan lengkap dengan seluruh isinya dari Live Chat.
     * Semua barisnya di crm_chat_pesan ikut terhapus permanen dan profil
     * pelanggan tidak disentuh. Kalau nomor itu chat lagi, percakapan baru
     * dibuat ulang oleh webhook seperti biasa.
     */
    public function chat_hapus()
    {
        $idPercakapan = (int) $this->request->getPost('id_percakapan');
        if ($idPercakapan <= 0) {
            return $this->response->setJSON(['status' => 'error', 'message' => 'Pilih chat yang mau dihapus.']);
        }

        $db = Database::connect();
        $conv = $db->table('crm_chat_percakapan')->select('id_percakapan')
            ->where('id_percakapan', $idPercakapan)->get()->getRowArray();
        if (! $conv) {
            return $this->response->setJSON(['status' => 'error', 'message' => 'Chat tidak ditemukan.']);
        }

        $db->table('crm_chat_pesan')->where('percakapan_id', $idPercakapan)->delete();
        $db->table('crm_chat_percakapan')->where('id_percakapan', $idPercakapan)->delete();

        return $this->response->setJSON(['status' => 'success']);
    }

    /**
     * API: Ganti label percakapan (crm_chat_percakapan.nama_kontak) saja.
     * Nama pelanggan di profil CRM (pelanggan.nama_pelanggan) tidak disentuh —
     * selama percakapan sudah tertaut customer, daftar chat tetap menampilkan
     * nama dari profil itu.
     */
    public function chat_ganti_nama()
    {
        $idPercakapan = (int) $this->request->getPost('id_percakapan');
        $nama         = trim($this->request->getPost('nama_kontak') ?? '');

        if ($idPercakapan <= 0 || $nama === '') {
            return $this->response->setJSON(['status' => 'error', 'message' => 'Nama tidak boleh kosong.']);
        }

        $db   = Database::connect();
        $conv = $db->table('crm_chat_percakapan')->select('id_percakapan')
            ->where('id_percakapan', $idPercakapan)->get()->getRowArray();
        if (! $conv) {
            return $this->response->setJSON(['status' => 'error', 'message' => 'Percakapan tidak ditemukan.']);
        }

        // kolom nama_kontak VARCHAR(100)
        $nama = mb_substr($nama, 0, 100);
        $db->table('crm_chat_percakapan')->where('id_percakapan', $idPercakapan)
            ->update(['nama_kontak' => $nama]);

        return $this->response->setJSON(['status' => 'success', 'nama_kontak' => $nama]);
    }

    /**
     * AJAX: Isi token template untuk pratinjau, dipakai dropdown Template di
     * Live Chat dan di profil Customer 360. CS harus melihat nama/nota sungguhan
     * saat memilih template, bukan {nama} yang baru terisi diam-diam ketika
     * pesan ditekan kirim.
     */
    public function chat_token()
    {
        $idPercakapan = (int) $this->request->getGet('id_percakapan');
        $idPelanggan  = (int) $this->request->getGet('id_pelanggan');
        $namaKontak   = '';

        if ($idPelanggan <= 0 && $idPercakapan > 0) {
            $conv = Database::connect()->table('crm_chat_percakapan')
                ->where('id_percakapan', $idPercakapan)->get()->getRowArray();

            if ($conv === null) {
                return $this->response->setJSON(['status' => 'error', 'message' => 'Percakapan tidak ditemukan.']);
            }

            $idPelanggan = (int) $conv['pelanggan_id'];
            $namaKontak  = $conv['nama_kontak'] !== $conv['nomor_wa'] ? (string) $conv['nama_kontak'] : '';

            if ($idPelanggan <= 0) {
                $autoMatch   = $this->crmModel->cariCustomerByWa($conv['nomor_wa']);
                $idPelanggan = (int) ($autoMatch['id_pelanggan'] ?? 0);
            }
        }

        return $this->response->setJSON([
            'status' => 'success',
            'data'   => $this->crmModel->getTokenPesan($idPelanggan, 0, $namaKontak),
        ]);
    }

    /**
     * API: Ambil status pengiriman beberapa pesan sekaligus (untuk update centang real-time)
     * GET admin/crm/chat/status_pesan?ids=1,2,3,4
     */
    public function chat_status_pesan()
    {
        $idsParam = trim($this->request->getGet('ids') ?? '');
        if (empty($idsParam)) {
            return $this->response->setJSON(['status' => 'error', 'message' => 'Tidak ada ID pesan.']);
        }

        // Hanya izinkan digit dan koma untuk keamanan
        $ids = array_filter(array_map('intval', explode(',', $idsParam)));
        if (empty($ids)) {
            return $this->response->setJSON(['status' => 'error', 'message' => 'ID tidak valid.']);
        }

        $db   = Database::connect();
        $rows = $db->table('crm_chat_pesan')
            ->select('id_pesan, status')
            ->whereIn('id_pesan', $ids)
            ->where('arah', 'keluar')
            ->get()
            ->getResultArray();

        $data = [];
        foreach ($rows as $r) {
            $data[(int) $r['id_pesan']] = $r['status'];
        }

        return $this->response->setJSON(['status' => 'success', 'data' => $data]);
    }
}
