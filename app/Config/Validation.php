<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;
use CodeIgniter\Validation\CreditCardRules;
use CodeIgniter\Validation\FileRules;
use CodeIgniter\Validation\FormatRules;
use CodeIgniter\Validation\Rules;

class Validation extends BaseConfig
{
    //--------------------------------------------------------------------
    // Setup
    //--------------------------------------------------------------------

    /**
     * Stores the classes that contain the
     * rules that are available.
     *
     * @var string[]
     */
    public $ruleSets = [
        Rules::class,
        FormatRules::class,
        FileRules::class,
        CreditCardRules::class,
    ];

    /**
     * Specifies the views that are used to display the
     * errors.
     *
     * @var array<string, string>
     */
    public $templates = [
        'list'   => 'CodeIgniter\Validation\Views\list',
        'single' => 'CodeIgniter\Validation\Views\single',
    ];

    //--------------------------------------------------------------------
    // Rules
    //--------------------------------------------------------------------

    public $signin = [
        'username' => 'required',
        'password' => 'required',
    ];
    public $signup = [
        'username' => 'required|min_length[3]|max_length[100]|is_unique[user.username]|alpha_dash',
        'password' => 'required|min_length[6]',
        'nama'     => 'required|min_length[3]|max_length[50]',
        'email'    => 'required|valid_email|max_length[100]|is_unique[user.email]',
    ];
    public $addBank = [
        'nama_bank'      => 'required|in_list[bri,bni,bca,mandiri,edc]',
        'nomor_rekening' => 'required|max_length[30]|numeric|is_unique[bank.rekening]',
        'atas_nama'      => 'required|max_length[50]|alpha_space',
    ];
    public $updateBank = [
        'id_bank'                => 'required|numeric',
        'sunting_nama_bank'      => 'required|in_list[bri,bni,bca,mandiri,edc]',
        'sunting_nomor_rekening' => 'required|max_length[30]|numeric|is_unique[bank.rekening,id_bank,{id_bank}]',
        'sunting_atas_nama'      => 'required|max_length[50]|alpha_space',
    ];
    // pesan khusus grup: lok api masih 'en', jadi aturan angka di bawah ini
    // dibahasakan agar tidak mencolok di form
    public $addBank_errors = [
        'nomor_rekening' => ['numeric' => 'Nomor rekening hanya boleh angka.'],
    ];
    public $updateBank_errors = [
        'sunting_nomor_rekening' => ['numeric' => 'Nomor rekening hanya boleh angka.'],
    ];
    public $addJuragan = [
        'nama_juragan' => 'required|min_length[3]|max_length[60]|is_unique[juragan.nama_juragan]',
        'bank'         => 'required',
    ];
    public $editJuragan = [
        'id'           => 'required|integer',
        'nama_juragan' => 'required|min_length[3]|max_length[60]',
        'bank'         => 'required',
    ];
    public $addPengguna = [
        'username' => 'required|min_length[3]|max_length[100]|is_unique[user.username]|alpha_dash',
        'password' => 'required|min_length[6]',
        'nama'     => 'required|min_length[3]|max_length[50]',
        'email'    => 'required|valid_email|max_length[100]|is_unique[user.email]',
        'level'    => 'required|in_list[superadmin,admin,cs,viewer,reseller]',
        'status'   => 'required|in_list[pending,inactive,active,blocked]',
    ];
    public $editPengguna = [
        'id'     => 'required|numeric',
        'nama'   => 'required|min_length[3]|max_length[50]',
        'email'  => 'required|valid_email|max_length[100]|is_unique[user.email,id,{id}]',
        'level'  => 'required|in_list[superadmin,admin,cs,viewer,reseller]',
        'status' => 'required|in_list[pending,inactive,active,blocked]',
    ];
    public $editProfil = [
        'id'    => 'required|numeric',
        'nama'  => 'required|min_length[3]|max_length[50]',
        'email' => 'required|valid_email|max_length[100]|is_unique[user.email,id,{id}]',
    ];
    public $addInvoice = [
        'juragan'       => 'required',
        'pengguna'      => 'required',
        'asal_orderan'  => 'required',
        'tanggal_order' => 'required',
        'juragan'       => 'required',
        'id_pemesan'    => 'required',
        'id_kirimKe'    => 'required',
        // 'keterangan' => 'required',
        'produk' => 'required',
        // 'biaya' 		=> 'required'
        // detail pesanan bersifat opsional
        'rincian.deadline' => 'permit_empty|valid_date',
        'rincian.ambil'    => 'permit_empty|valid_date',
        'rincian.kembali'  => 'permit_empty|valid_date',
        'rincian.jaminan'  => 'permit_empty|max_length[100]',
    ];
    public $updateInvoice = [
        'id_invoice'    => 'required',
        'juragan'       => 'required',
        'pengguna'      => 'required',
        'asal_orderan'  => 'required',
        'tanggal_order' => 'required',
        'juragan'       => 'required',
        'id_pemesan'    => 'required',
        'id_kirimKe'    => 'required',
        // 'keterangan' => 'required',
        'produk' => 'required',
        // 'biaya' 		=> 'required'
        // detail pesanan bersifat opsional
        'rincian.deadline' => 'permit_empty|valid_date',
        'rincian.ambil'    => 'permit_empty|valid_date',
        'rincian.kembali'  => 'permit_empty|valid_date',
        'rincian.jaminan'  => 'permit_empty|max_length[100]',
    ];
    public $simpanProgress = [
        'id_invoice' => 'required|integer',
        'status'     => 'required',
        'stat'       => 'required',
        // 'keterangan' 	=> 'required'
    ];
    public $tambahPembayaran = [
        'invoice_id'       => 'required|integer',
        'sumber_dana'      => 'required',
        'total_pembayaran' => 'required|integer',
        // 'status' 			=> 'required',
        'tanggal_pembayaran' => 'required',
    ];
    public $updatePembayaran = [
        'id_pembayaran' => 'required|integer',
        'invoice_id'    => 'required|integer',
        'status'        => 'required',
    ];
    public $tambahPengiriman = [
        'invoice_id'    => 'required|integer',
        'kurir'         => 'required',
        'ongkir'        => 'required|integer',
        'qty'           => 'required',
        'resi'          => 'required',
        'tanggal_kirim' => 'required',
    ];
    public $addStok = [
        'juragan_id' => 'required|integer',
        'kode'       => 'required|max_length[20]',
        'ukuran'     => 'permit_empty|max_length[6]',
        'harga'      => 'required|integer',
        // stok boleh negatif supaya salah hitung gudang bisa langsung dikoreksi
        'stok'       => 'required|integer',
        'keterangan' => 'permit_empty|max_length[120]',
    ];
    public $updateStok = [
        'id_stok'    => 'required|integer',
        'kode'       => 'required|max_length[20]',
        'ukuran'     => 'permit_empty|max_length[6]',
        'harga'      => 'required|integer',
        'stok'       => 'required|integer',
        'keterangan' => 'permit_empty|max_length[120]',
    ];
}
