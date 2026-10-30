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
        $provider   = $this->wa->getActiveProviderName();
        $isSandbox  = ($provider === 'kapso') ? (int) $this->wa->getSetting('kapso_sandbox_mode', '1') : 0;

        // Ambil beberapa follow-up aktif hari ini
        $followupHariIni = $this->crmModel->getFollowupList(['status' => 'menunggu'], 5);

        $data = [
            'title'           => 'Dashboard CRM',
            'ringkasan'       => $ringkasan,
            'reminders'       => array_slice($reminders, 0, 6),
            'followupHariIni' => $followupHariIni,
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
        // nama tahap produksi diambil dari master helper yang sama dengan Tracking
        // Transaksi, jadi label di daftar tagihan tidak pernah berbeda dengan di nota
        helper('fungsi');

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
        // 'siap' (bawaan): hanya nota yang barangnya sudah selesai/packing, karena
        // itulah yang realistis ditagih hari ini. 'semua' menampilkan seluruh
        // piutang, termasuk yang masih dikerjakan.
        $saring = (string) ($this->request->getGet('saring') ?? 'siap');
        if (! in_array($saring, ['siap', 'semua'], true)) {
            $saring = 'siap';
        }

        $page                 = max(1, (int) ($this->request->getGet('page') ?? 1));
        $limit                = 25;
        $offset               = ($page - 1) * $limit;
        $invoicesTagihan      = $this->crmModel->getInvoiceBelumLunas($cari, $limit, $offset, $saring);
        $totalInvoicesTagihan = $this->crmModel->countInvoiceBelumLunas($cari, $saring);
        $totalPagesTagihan    = (int) ceil($totalInvoicesTagihan / $limit);

        // kedua angka dipakai tombol pengalih, supaya pengguna melihat berapa yang
        // siap ditagih dan berapa total piutang tanpa perlu berganti tab
        $jumlahSiap  = $saring === 'siap'
            ? $totalInvoicesTagihan
            : $this->crmModel->countInvoiceBelumLunas($cari, 'siap');
        $jumlahSemua = $saring === 'semua'
            ? $totalInvoicesTagihan
            : $this->crmModel->countInvoiceBelumLunas($cari, 'semua');

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
            'saring'               => $saring,
            'jumlahSiap'           => $jumlahSiap,
            'jumlahSemua'          => $jumlahSemua,
            'tahap'                => tahap_produksi(),
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
        $semuaDuplikat = $this->crmModel->getDuplikatList();
        $perHalaman    = 25;
        $totalDuplikat = count($semuaDuplikat);
        $totalPages    = (int) ceil($totalDuplikat / $perHalaman);
        $page          = max(1, min($totalPages, (int) ($this->request->getGet('page') ?: 1)));

        $data = [
            'title'         => 'Deteksi & Penggabungan Akun Duplikat',
            'duplikatList'  => array_slice($semuaDuplikat, ($page - 1) * $perHalaman, $perHalaman),
            'page'          => $page,
            'totalPages'    => $totalPages,
            'totalDuplikat' => $totalDuplikat,
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
     * Follow-up Tagihan Invoice Belum Lunas
     */
    public function tagihan()
    {
        $cari = trim($this->request->getGet('cari') ?? '');

        // daftar penuh tidak pernah dimuat sekaligus: 25 baris per halaman, dan
        // angkanya diambil dari query COUNT terpisah supaya "Ditemukan ..." tetap
        // berarti total, bukan banyaknya baris di halaman ini saja
        $perHalaman    = 25;
        $totalTagihan  = $this->crmModel->countInvoiceBelumLunas($cari);
        $totalPages    = (int) ceil($totalTagihan / $perHalaman);
        $page          = max(1, min(max($totalPages, 1), (int) ($this->request->getGet('page') ?: 1)));
        $invoices      = $this->crmModel->getInvoiceBelumLunas($cari, $perHalaman, ($page - 1) * $perHalaman);

        // Ambil template tagihan
        $db = Database::connect();
        $template = $db->table('crm_template')->where('kode', 'tagihan_belum_lunas')->get()->getRowArray();

        $data = [
            'title'        => 'Follow-up Tagihan Belum Lunas',
            'invoices'     => $invoices,
            'cari'         => $cari,
            'template'     => $template['pesan'] ?? '',
            'provider'     => $this->wa->getActiveProviderName(),
            'isSandbox'    => ($this->wa->getActiveProviderName() === 'kapso') ? (int) $this->wa->getSetting('kapso_sandbox_mode', '1') : 0,
            'page'         => $page,
            'totalPages'   => $totalPages,
            'totalTagihan' => $totalTagihan,
        ];

        return view('admin/crm/tagihan', $data);
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
     * AJAX: isi token {nama} {invoice} {total} ... untuk pratinjau template di
     * popus "Kirim WhatsApp" pada Profil Customer 360. Dulu endpoint ini bagian
     * dari Live Chat (admin/crm/chat/token); halamannya sudah dihapus, jadi
     * hanya jalur pelanggan yang tersisa di sini.
     */
    public function pesan_token()
    {
        $idPelanggan = (int) $this->request->getGet('id_pelanggan');

        if ($idPelanggan <= 0) {
            return $this->response->setJSON(['status' => 'error', 'message' => 'Pelanggan tidak ditemukan.']);
        }

        return $this->response->setJSON([
            'status' => 'success',
            'data'   => $this->crmModel->getTokenPesan($idPelanggan, 0),
        ]);
    }

}
