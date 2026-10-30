<?php
/**
 * Variabel di bawah disuntik runtime oleh Admin\Cod::index() lewat
 * view('admin/cod/index', $data). CI4 meng-extract array itu saat render
 * (View::render -> extract()), jadi tidak ada assignment di file ini dan
 * Intelephense (P1008) menyangkanya tak terdefinisi. Blok @var ini hanya
 * penanda tipe untuk analyzer, bukan kode yang dijalankan.
 *
 * @var array      $catatan          baris catatan COD halaman aktif
 * @var int        $total            jumlah catatan sesuai filter
 * @var int        $total_semua      jumlah catatan tanpa filter status (tab "Semua")
 * @var array      $status_counts    jumlah catatan per id status
 * @var int        $page             nomor halaman aktif (1-based)
 * @var int        $halaman          jumlah halaman
 * @var int        $per_halaman      jumlah kartu per halaman
 * @var array      $pilihan          master pilihan: ['keperluan'|'status'|'cs' => [['id','label'], ...]]
 * @var bool       $boleh_hapus      pengguna boleh menghapus pilihan (admin/superadmin)
 * @var bool       $boleh_status_baru pengguna boleh menambah/menghapus status transaksi (superadmin saja)
 * @var array      $dipakai          [id pilihan => ['tampil' => int, 'hapus' => int]]
 * @var string     $hari_ini         tanggal hari ini (Y-m-d)
 * @var array      $f                nilai filter GET: cari, status, cs, keperluan, dari, sampai, periode
 * @var bool       $ada_filter         ada filter yang aktif
 * @var bool       $ada_filter_modal   ringkasan filter siap ditampilkan
 * @var string     $ringkasan_filter   teks ringkasan filter
 * @var array      $errors           pesan validasi flashdata: ['kolom' => 'pesan', ...]
 * @var string|null $sukses          pesan berhasil flashdata
 * @var string|null $gagal           pesan gagal flashdata
 */

use CodeIgniter\I18n\Time;

// error datang dari flashdata controller: ['nama_customer' => 'pesan', ...]
$err = static fn (string $kolom): ?string => isset($errors[$kolom]) ? (string) $errors[$kolom] : null;

// Hanya modal yang gagal yang boleh terbuka lagi. Modal sunting satu-satunya
// yang mengirim id_cod, jadi isian lama menandai modal mana yang dimaksud.
$pernah_isi   = old('nama_customer') !== null || old('id_cod') !== null;
$gagal_sunting = $pernah_isi && old('id_cod') !== null;
$gagal_tambah  = $pernah_isi && ! $gagal_sunting;

// nama field-nya sama di kedua modal, jadi pesan hanya ditempelkan ke modal
// yang memang mengirimnya
$err_sunting = static fn (string $kolom): ?string => $gagal_sunting ? $err($kolom) : null;
$err_tambah  = static fn (string $kolom): ?string => $gagal_tambah ? $err($kolom) : null;

// warna pil status. Kata baru yang diketik pengguna tetap dapat pil, hanya
// tanpa warna, supaya daftar tidak tiba-tiba kehilangan kategorinya
$pil_status = static function (string $status): string {
    $kunci = mb_strtolower(trim($status));

    if (str_contains($kunci, 'sukses') || str_contains($kunci, 'selesai')) {
        return 'bg-selesai text-white';
    }

    if (str_contains($kunci, 'cancel') || str_contains($kunci, 'batal')) {
        return 'bg-red-600 text-white';
    }

    if (str_contains($kunci, 'revisi')) {
        return 'bg-tunggu text-ink-900';
    }

    return 'border border-ink-200 bg-ink-50 text-ink-700';
};

// "Rabu, 14 Oktober 2026"; tanggal yang tidak bisa dibaca ditampilkan apa ada-
// nya daripada kartunya gagal ter-render
$tanggal_panjang = static function (?string $tanggal): string {
    if (trim((string) $tanggal) === '') {
        return '';
    }

    try {
        return Time::createFromFormat('Y-m-d', $tanggal)->toLocalizedString('EEEE, d MMMM yyyy');
    } catch (Throwable) {
        return (string) $tanggal;
    }
};

// jam kolom TIME datang sebagai 14:30:00, yang dibaca pengguna cuma 14:30
$jam_ringkas = static fn (?string $jam): string => $jam === null ? '' : substr($jam, 0, 5);

// isi kartu untuk modal sunting, dikemas sekali per kartu dan dibaca JS
$isi_kartu = static fn (array $c): string => (string) esc(json_encode([
    'id'        => (int) $c['id_cod'],
    'tanggal'   => (string) $c['tanggal'],
    'jam'       => $jam_ringkas($c['jam'] ?? null),
    'nama'      => (string) $c['nama_customer'],
    'hp'        => (string) $c['no_hp'],
    'keperluan' => (string) ($c['keperluan'] ?? ''),
    'status'    => (string) ($c['status_transaksi'] ?? ''),
    'catatan'   => (string) $c['catatan'],
    'cs'        => (string) ($c['cs'] ?? ''),
    'sumber'    => (string) ($c['sumber'] ?? ''),
]));

// kalimat konfirmasi hapus; menyebut nama dan tanggal supaya yang ditunjuk jelas
$peringatan_hapus = static function (array $c) use ($tanggal_panjang, $jam_ringkas): string {
    $kapan = trim($tanggal_panjang($c['tanggal'] ?? null) . ' ' . $jam_ringkas($c['jam'] ?? null));

    return 'Hapus catatan ' . trim((string) $c['nama_customer']) . ($kapan === '' ? '' : ' (' . $kapan . ')')
        . '? Catatan yang dihapus hilang dari daftar ini.';
};

/**
 * Dropdown pilihan yang bisa ditambah baru.
 *
 * Nilainya tetap sebuah string biasa (name="keperluan" dst), jadi controller dan
 * PencatatanPilihanModel::jamin() tidak perlu tahu bahwa pemilihnya bukan
 * <input> bebas lagi. Pensil di paling bawah daftar memanggil pop-up bersama
 * (#modalPilihanBaru) supaya daftar dropdown tidak memanjang ke bawah; pilihan
 * baru langsung bisa dipakai dan baru masuk tabel master saat Simpan.
 *
 * Status transaksi adalah daftar yang dipakai bersama seluruh tim dan jadi
 * dasar tab penyaring, jadi pensilnya hanya dirender untuk Superadmin
 * ($boleh_status_baru); Admin dan CS memilih dari yang sudah ada. Pilihan bebas
 * tinggal keperluan dan nama CS, sedangkan sumber informasi diketik langsung di
 * kolomnya sendiri.
 *
 * $daftar berisi ['id' => .., 'label' => ..]. Id-nya dipakai untuk tong sampah
 * di kanan tiap pilihan, yang dirender untuk admin dan superadmin ($boleh_hapus):
 * keduanya boleh menambah maupun menghapus pilihan.
 *
 * $dipakai ([id pilihan => ['tampil' => n, 'hapus' => n]]) ikut dibaca supaya
 * pilihan yang masih dipakai catatan yang ada di daftar menampilkan tongnya dalam
 * keadaan mati beserta jumlahnya: foreign key RESTRICT pasti menolak, jadi lebih
 * baik alasannya terlihat daripada tombol yang kelihatannya tidak berfungsi.
 * Catatan yang sudah dihapus lunak tidak dihitung — pilihannya boleh dihapus dan
 * controller melepas baris tersembunyi itu lebih dulu.
 */
$kotak_pilihan = static function (string $nama, string $id, array $daftar, string $aktif, string $pesan, string $kosong, bool $bolehBaru = true) use ($boleh_hapus, $dipakai): string {
    $opsi = '';

    foreach ($daftar as $satu) {
        $label   = (string) $satu['label'];
        $idPilih = (int) $satu['id'];
        $pakai   = (int) ($dipakai[$idPilih]['tampil'] ?? 0);
        $tersembunyi = (int) ($dipakai[$idPilih]['hapus'] ?? 0);
        // pilihan sumber boleh dihapus walau masih dipakai: foreign key-nya
        // SET NULL, jadi catatannya tidak ikut menahan baris master
        $terhalang = $pakai > 0 && $nama !== 'sumber';
        $alasan    = $terhalang
            ? 'Masih dipakai ' . $pakai . ' catatan, tidak bisa dihapus'
            : 'Hapus pilihan ' . $label . ($tersembunyi > 0 ? ' (melepas ' . $tersembunyi . ' catatan yang sudah dihapus)' : '');

        $opsi .= '<div class="cod-baris">'
            . '<button type="button" class="dropdown-item cod-opsi" data-label="' . esc($label, 'attr') . '">' . esc($label) . '</button>';

        if ($bolehBaru && $boleh_hapus) {
            $opsi .= '<button type="button" class="dropdown-item cod-hapus-pilihan"'
                . ($terhalang ? ' disabled' : ' data-id="' . $idPilih . '"')
                . ' data-label="' . esc($label, 'attr') . '"'
                . ' title="' . esc($alasan, 'attr') . '"'
                . ' aria-label="Hapus pilihan ' . esc($label, 'attr') . '"><i class="fal fa-trash-alt"></i></button>';
        }

        $opsi .= '</div>';
    }

    $salah = $pesan !== '' ? ' is-invalid' : '';

    $baru = $bolehBaru
        ? '<div class="dropdown-divider"></div>'
            . '<button type="button" class="dropdown-item d-flex align-items-center gap-2 cod-pilih-baru"><i class="fal fa-pencil"></i> Tambah pilihan</button>'
        : '';

    return '<div class="dropdown cod-pilih">'
        . '<button type="button" id="' . esc($id, 'attr') . '" class="form-control text-start d-flex align-items-center justify-content-between gap-2 cod-pilih-tombol' . $salah . '"'
        . ' data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false" aria-haspopup="listbox">'
        . '<span class="cod-pilih-label text-truncate' . ($aktif === '' ? ' cod-kosong' : '') . '">' . esc($aktif === '' ? $kosong : $aktif) . '</span>'
        . '<i class="fal fa-chevron-down"></i></button>'
        . '<div class="dropdown-menu w-100 cod-pilih-menu shadow-sm" role="listbox">' . $opsi . $baru
        . '</div>'
        . '<input type="hidden" name="' . esc($nama, 'attr') . '" value="' . esc($aktif, 'attr') . '" data-kosong="' . esc($kosong, 'attr') . '">'
        . '</div>'
        . ($pesan !== '' ? '<div class="invalid-feedback d-block">' . esc($pesan) . '</div>' : '');
};
?>
<?= $this->extend('template/main') ?>

<?= $this->section('css') ?>
<style>
    /* daftar pilihan bisa panjang (keperluan sudah belasan), jadi menunya ikut
       digulir alih-alih mendorong layar */
    .cod-pilih-menu {
        max-height: 17rem;
        overflow-y: auto;
    }

    .cod-pilih .cod-pilih-tombol {
        background-color: #ffffff;
    }

    .cod-pilih .cod-pilih-label {
        min-width: 0;
    }

    .cod-pilih .cod-pilih-label.cod-kosong {
        color: #98a2b3;
    }

    .cod-pilih-tombol > i {
        flex: 0 0 auto;
    }

    .cod-pilih-baru {
        color: #b81c16;
        font-weight: 600;
    }

    /* satu baris dropdown: labelnya memanjang, tong sampahnya di kanan */
    .cod-baris {
        display: flex;
        align-items: stretch;
    }

    .cod-baris .cod-opsi {
        flex: 1 1 auto;
        min-width: 0;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    /* tongnya ikut class dropdown-item, sementara style.css memaksa color,
       padding dan border-radius dropdown-item dengan !important, jadi nilai
       di bawah juga harus !important supaya sempat menang; width: auto lepas
       dari lebar penuh Bootstrap agar labelnya tidak terdesak */
    .cod-baris .cod-hapus-pilihan {
        flex: 0 0 auto;
        width: auto;
        padding: 0.45rem 0.7rem !important;
        color: #b81c16 !important;
    }

    .cod-baris .cod-hapus-pilihan:hover {
        background-color: #fdf2f2 !important;
        color: #b81c16 !important;
    }

    /* pilihan yang masih dipakai catatan: tongnya tetap ada supaya daftarnya tidak
       bergeser, tapi matikan supaya tidak ada klik yang berujung ditolak diam-diam */
    .cod-baris .cod-hapus-pilihan:disabled {
        color: #d0d5dd !important;
        background-color: transparent !important;
        cursor: not-allowed;
    }

    /* baris filter: kolomnya dibagi rata (col-lg) supaya barisnya penuh dan
       kedua kotak tanggal bisa duduk di samping periode. Flex item tidak mau
       menyusut di bawah lebar isi, dan input tanggal punya lebar tetap ~123px,
       jadi min-width-nya dilepas di sini */
    .cod-saring > div,
    .cod-saring input[type="date"] {
        min-width: 0;
    }

    /* pesan hasil hapus pilihan: diam di bawah sampai dipanggil, lalu muncul
       dan dihilangkan lagi oleh JavaScript setelah 3 detik */
    .cod-pesan {
        position: fixed;
        left: 50%;
        bottom: 1.5rem;
        transform: translateX(-50%);
        z-index: 1080;
        max-width: min(28rem, calc(100vw - 2rem));
        padding: 0.6rem 1rem;
        border-radius: 0.5rem;
        background-color: #1f2937;
        color: #ffffff;
        font-size: 0.9rem;
        box-shadow: 0 0.4rem 1rem rgb(31 41 55 / 0.25);
        opacity: 0;
        visibility: hidden;
        transition: opacity 0.25s ease;
    }

    .cod-pesan-tampil {
        opacity: 1;
        visibility: visible;
    }

    .cod-pesan-gagal {
        background-color: #b81c16;
    }

    /* konfirmasi hapus pilihan: cuma satu kalimat dan dua tombol, jadi jangan
       ikut sebesar modal form catatan. style.css memaksa padding, garis dan
       latar modal-header/modal-footer dengan !important, jadi nilainya di sini
       juga harus !important supaya benar-benar berlaku */
    .modal-hapus-pilihan {
        max-width: 21rem;
    }

    .modal-hapus-pilihan .modal-content {
        border-color: #f3d3d1 !important;
    }

    .modal-hapus-pilihan .modal-header {
        padding: 0.7rem 0.95rem !important;
        border-bottom: 0 !important;
        background-color: #fff7f7 !important;
    }

    .modal-hapus-pilihan .modal-title {
        display: flex;
        align-items: center;
        gap: 0.45rem;
        font-size: 0.95rem !important;
        color: #b81c16 !important;
    }

    .modal-hapus-pilihan .modal-body {
        padding: 0.85rem 0.95rem 0.2rem !important;
    }

    .modal-hapus-pilihan .modal-footer {
        padding: 0.85rem 0.95rem !important;
        border-top: 0 !important;
        background-color: transparent !important;
        gap: 0.45rem;
    }

    /* catatan di kartu dipotong tiga baris; teks lengkapnya dibacakan pop-up */
    .cod-catatan {
        display: -webkit-box;
        -webkit-line-clamp: 3;
        line-clamp: 3;
        -webkit-box-orient: vertical;
        overflow: hidden;
        cursor: pointer;
    }

    /* Tombol Sunting dan Hapus di kartu COD: kanan-kiri mengisi lebar kartu di
       layar HP, bukan bertumpuk. Hapus duduk di dalam <form> sendiri, jadi
       wadah formnya dilucuti (display:contents) supaya tombolnya ikut jadi
       anggota baris aksi — selektornya tetap harus menyebut di dalam form,
       karena display:contents hanya menghapus kotaknya di layar, bukan tempat
       tombolnya di DOM. Keterangan "dicatat oleh ..." turun ke barisnya sendiri
       dan tidak perlu lagi didorong ke tepi kanan. */
    @media (max-width: 575.98px) {
        .kartu-invoice .aksi > form {
            display: contents !important;
        }

        .kartu-invoice .aksi > .btn,
        .kartu-invoice .aksi > form > .btn {
            flex: 1 1 0;
        }

        .kartu-invoice .aksi > span {
            margin-left: 0 !important;
        }
    }

    /* formnya dua kolom, jadi pop-upnya cukup sedang: lebih lapang dari modal
       standar tetapi tidak sebesar modal-lg yang membuat layar penuh */
    .modal-cod {
        max-width: 40rem;
    }

    /* Form catatannya sembilan isian sehingga lebih tinggi dari layar. Selain
       isian yang pendek dipasangkan satu baris (col-6 di markup) dan isiannya
       digulir di dalam pop-up (modal-dialog-scrollable), ruang yang ada dipakai
       lebih rapat: label ditempel ke fieldnya, jarak antar isian, padding body,
       dan jarak pop-up ke tepi di layar sempit ikut dikecilkan. Bootstrap dan
       style.css mengunci .mb-3 serta .form-label dengan !important, jadi
       pengaturannya ikut !important. */
    .modal-cod .modal-body {
        padding: 0.8rem 0.95rem;
    }

    .modal-cod .modal-body > .row > div {
        margin-bottom: 0.5rem !important;
    }

    .modal-cod .modal-body .form-label {
        margin-bottom: 0.15rem !important;
    }

    /* padding kiri-kanan bawaan Bootstrap memakan hampir seperempat lebar
       kolom yang dibagi dua, jadi hanya isian di kolom berpasangan yang dirapatkan */
    .modal-cod .modal-body > .row > .col-6 .form-control {
        padding-left: 0.55rem;
        padding-right: 0.55rem;
    }

    @media (max-width: 767.98px) {
        .modal-cod {
            --bs-modal-margin: 0.7rem;
        }

        /* .modal-dialog-scrollable membuat dialognya setinggi layar, jadi popusnya
           selalu terlihat "memenuhi layar". Yang dibatasi bukan dialognya (dialog
           tetap full-height supaya modal-dialog-centered masih menengahkan isinya)
           tapi kartunya: kepala + kaki tetap terbaca dan isian sisanya digulir di
           dalam body, yang memang sudah overflow-y:auto. */
        .modal-cod.modal-dialog .modal-content {
            max-height: 72vh;
        }
    }

    /* di ponsel kecil dua isian berbagi satu kolom, jadi parit antar kolom dan
       padding kiri-kanan fieldnya ditipiskan; tanpa ini teks pilihan kepotong
       cuma karena beberapa piksel */
    @media (max-width: 400px) {
        .modal-cod .modal-body > .row {
            --bs-gutter-x: 0.5rem;
        }

        .modal-cod .modal-body > .row > .col-6 .form-control {
            padding-left: 0.45rem;
            padding-right: 0.45rem;
        }
    }

    /* Toolbar transaksi: tab status di kiri, aksi Cari & Tambah di kanan */
    .toolbar-transaksi {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 0.4rem 0.5rem;
    }
    .toolbar-transaksi__tab {
        display: flex;
        align-items: center;
        gap: 0.35rem;
        flex: 1 1 auto;
        min-width: 0;
        /* pil yang tidak muat digeser lewat tombol panah, jadi wadahnya selalu bisa
           digulir mendatar di semua lebar: tombol panahnya sendiri muncul hanya
           kalau scrollWidth lebih besar dari clientWidth, dan itu hanya bisa
           diketahui kalau overflow-x-nya terpasang sejak awal, bukan di layar
           sempit saja. Gulir rodanya ditutup karena sudah ada tombolnya. */
        overflow-x: auto;
        scrollbar-width: none;
        overscroll-behavior-x: contain;
    }
    .toolbar-transaksi__tab::-webkit-scrollbar {
        display: none;
    }

    /* Tombol geser strip pil, sama seperti strip tab di halaman semua transaksi. */
    .toolbar-transaksi__geser {
        display: flex;
        align-items: center;
        gap: 0.35rem;
        flex: 1 1 auto;
        min-width: 0;
    }
    .geser-tab {
        display: none;
        flex: 0 0 auto;
        width: 1.9rem;
        height: 1.9rem;
        align-items: center;
        justify-content: center;
        padding: 0;
        border: 1px solid #eaecf0;
        border-radius: 999px;
        background: #ffffff;
        color: #344054;
        box-shadow: 0 1px 2px rgba(16, 24, 40, 0.06);
        font-size: 0.8rem;
        line-height: 1;
    }
    .toolbar-transaksi__geser.bisa-geser .geser-tab {
        display: inline-flex;
    }
    .geser-tab:disabled {
        opacity: 0.35;
        box-shadow: none;
    }
    .toolbar-transaksi__aksi {
        display: flex;
        align-items: center;
        gap: 0.35rem;
        flex: 0 1 auto;
        margin-left: auto;
    }
    .toolbar-transaksi .btn {
        padding: 0.42rem 0.62rem;
        gap: 0.3rem;
        white-space: nowrap;
    }
    .toolbar-transaksi .badge {
        padding: 0.2rem 0.45rem !important;
    }

    /* Kartu ringkasan status: satu kartu per status yang sekaligus jadi tombol
       penyaring. Kartunya disusun rata tepi dan sejajar tinggi (h-100 lewat
       kelas Bootstrap di markup), jadi label di atas dan angka di bawah duduk
       rapi apa pun panjang teksnya. Nama status yang kepanjangan dipotong dengan
       elipsis, bukan melebarkan kartunya. Kartu aktif ikut menggelap seperti pil
       navigasi di atasnya supaya jelas status mana yang sedang dibuka. */
    .cod-kartu {
        display: flex;
        flex-direction: column;
        justify-content: center;
        gap: 0.1rem;
        height: 100%;
        padding: 0.7rem 0.9rem;
        border: 1px solid #eaecf0;
        border-radius: 0.85rem;
        background: #ffffff;
        text-decoration: none;
        box-shadow: 0 1px 2px rgba(16, 24, 40, 0.04);
        transition: border-color .15s ease, box-shadow .15s ease, background-color .15s ease, transform .05s ease;
    }
    .cod-kartu:hover {
        border-color: #d0d5dd;
        box-shadow: 0 3px 8px rgba(16, 24, 40, 0.08);
    }
    .cod-kartu:active {
        transform: translateY(1px);
    }
    .cod-kartu__label {
        font-size: 0.8rem;
        font-weight: 600;
        color: #667085;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .cod-kartu__angka {
        font-size: 1.4rem;
        font-weight: 700;
        color: #101828;
        line-height: 1.15;
    }
    .cod-kartu.aktif {
        background: #101828;
        border-color: #101828;
    }
    .cod-kartu.aktif .cod-kartu__label {
        color: rgba(255, 255, 255, 0.72);
    }
    .cod-kartu.aktif .cod-kartu__angka {
        color: #ffffff;
    }

    @media (max-width: 767.98px) {
        /* yang turun ke baris penuh adalah pembungkusnya, bukan strip pilnya:
           strip tetap berbagi baris dengan kedua tombol panah */
        .toolbar-transaksi__geser {
            flex: 1 1 100%;
            min-width: 0;
        }
        .toolbar-transaksi__tab {
            padding-bottom: 0.15rem;
        }
        .toolbar-transaksi__tab .btn {
            flex: 0 0 auto;
        }
        .toolbar-transaksi__aksi {
            flex: 1 1 100%;
            margin-left: 0;
        }
        /* tombolnya ikut melebar ke tepi kartu; dulu ia menggantung seukuran
           tulisannya saja sehingga baris di bawah strip pil terlihat kosong */
        .toolbar-transaksi__aksi > .btn {
            width: 100%;
        }
    }
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?= $this->include('admin/navbar') ?>

<div class="container-xxl">

    <h1 class="h3 mt-5">Pencatatan COD</h1>

    <div class="toolbar-transaksi mb-4">
        <div class="toolbar-transaksi__geser">
            <button type="button" class="geser-tab" data-geser="-1" aria-label="Geser daftar status ke kiri" title="Geser daftar status ke kiri"><i class="fal fa-chevron-left"></i></button>

            <div class="toolbar-transaksi__tab">
            <?php
            $kueri_lain = array_filter([
                'cari'      => $f['cari'] ?: null,
                'cs'        => $f['cs'] ?: null,
                'keperluan' => $f['keperluan'] ?: null,
                'dari'      => $f['dari'] ?: null,
                'sampai'    => $f['sampai'] ?: null,
                'periode'   => $f['periode'] ?: null,
            ]);
            $link_semua = site_url('admin/cod') . ($kueri_lain !== [] ? '?' . http_build_query($kueri_lain) : '');
            ?>
            <a href="<?= $link_semua ?>" class="btn rounded-pill <?= empty($f['status']) ? 'btn-primary' : 'btn-outline-secondary' ?>" title="Semua catatan">
                Semua
            </a>
            <?php foreach ($pilihan['status'] as $s) : ?>
                <?php
                $aktif = (int) $f['status'] === (int) $s['id'];
                $link_tab = site_url('admin/cod') . '?' . http_build_query(array_merge($kueri_lain, ['status' => $s['id']]));
                ?>
                <a href="<?= $link_tab ?>" class="btn rounded-pill <?= $aktif ? 'btn-primary' : 'btn-outline-secondary' ?>" title="Status <?= esc($s['label']) ?>">
                    <?= esc($s['label']) ?>
                </a>
            <?php endforeach; ?>
            </div>

            <button type="button" class="geser-tab" data-geser="1" aria-label="Geser daftar status ke kanan" title="Geser daftar status ke kanan"><i class="fal fa-chevron-right"></i></button>
        </div>

        <div class="toolbar-transaksi__aksi">
            <button type="button" class="btn rounded-pill border-0 bg-brand-500 font-semibold text-white hover:bg-brand-600" data-bs-toggle="modal" data-bs-target="#modalTambahCod" title="Tambah pencatatan COD baru">
                <i class="fal fa-plus"></i> Tambah Pencatatan COD
            </button>
        </div>
    </div>

    <!-- Kartu ringkasan status: satu kartu per status, sekaligus tombol penyaring.
         Angkanya pindah ke sini, jadi bar pil di atas tetap jadi navigasi tanpa
         jumlah. Menekan kartu memfilter daftar ke status itu; "Semua" membuka
         kembali seluruh catatan. $kueri_lain & $link_semua sudah dihitung di bar
         pil di atas, jadi filter lain (cari, cs, keperluan, tanggal) ikut terbawa. -->
    <div class="row g-2 g-md-3 mb-4 cod-status-kartu">
        <div class="col-6 col-md-4 col-lg-2">
            <a href="<?= $link_semua ?>" class="cod-kartu <?= empty($f['status']) ? 'aktif' : '' ?>" title="Semua catatan">
                <span class="cod-kartu__label">Semua</span>
                <span class="cod-kartu__angka"><?= (int) $total_semua ?></span>
            </a>
        </div>
        <?php foreach ($pilihan['status'] as $s) : ?>
            <?php
            $kartu_aktif = (int) $f['status'] === (int) $s['id'];
            $kartu_link  = site_url('admin/cod') . '?' . http_build_query(array_merge($kueri_lain, ['status' => $s['id']]));
            ?>
            <div class="col-6 col-md-4 col-lg-2">
                <a href="<?= $kartu_link ?>" class="cod-kartu <?= $kartu_aktif ? 'aktif' : '' ?>" title="Status <?= esc($s['label']) ?>">
                    <span class="cod-kartu__label"><?= esc($s['label']) ?></span>
                    <span class="cod-kartu__angka"><?= (int) ($status_counts[$s['id']] ?? 0) ?></span>
                </a>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Filter dicari langsung di bawah pil status, bukan di dalam popus lagi.
         Status tidak diulang di sini: pil persis di atasnya sudah jadi tombol
         filter status. Kolom carinya satu kotak saja — ikon pencariannya
         duduk di dalam field dan sekaligus jadi tombol kirim (.cari-merged). -->
    <?= form_open('admin/cod', ['method' => 'get', 'class' => 'row g-2 align-items-center mb-4 cod-saring']) ?>
        <?= form_hidden('status', (string) $f['status']) ?>

        <?php $tanggal_kustom = $f['periode'] === 'kustom' || ($f['dari'] !== '' && ! in_array($f['periode'], ['hari_ini', 'kemarin', '7_hari', 'bulan_ini'], true)); ?>

        <div class="col-12 col-lg-3">
            <div class="cari-merged">
                <button type="submit" class="cari-kirim" aria-label="Cari"><i class="fal fa-search"></i></button>
                <input type="text" class="form-control form-control-sm" name="cari" id="filter_cari"
                    placeholder="Ketik nama, no HP, atau isi catatan..." value="<?= esc($f['cari']) ?>" autocomplete="off">
            </div>
        </div>

        <div class="col-6 col-lg">
            <select class="form-select form-select-sm" name="cs" id="filter_cs" onchange="this.form.submit()" aria-label="Customer Service">
                <option value="">Semua CS</option>
                <?php foreach ($pilihan['cs'] as $c) : ?>
                    <option value="<?= $c['id'] ?>" <?= (string) $f['cs'] === (string) $c['id'] ? 'selected' : '' ?>>
                        <?= esc($c['label']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-6 col-lg">
            <select class="form-select form-select-sm" name="keperluan" id="filter_keperluan" onchange="this.form.submit()" aria-label="Keperluan">
                <option value="">Semua Keperluan</option>
                <?php foreach ($pilihan['keperluan'] as $k) : ?>
                    <option value="<?= $k['id'] ?>" <?= (string) $f['keperluan'] === (string) $k['id'] ? 'selected' : '' ?>>
                        <?= esc($k['label']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-12 col-lg">
            <select class="form-select form-select-sm" name="periode" id="filter_periode" aria-label="Periode tanggal">
                <option value="" <?= $f['periode'] === '' && $f['dari'] === '' ? 'selected' : '' ?>>Semua Tanggal</option>
                <option value="hari_ini" <?= $f['periode'] === 'hari_ini' ? 'selected' : '' ?>>Hari Ini</option>
                <option value="kemarin" <?= $f['periode'] === 'kemarin' ? 'selected' : '' ?>>Kemarin</option>
                <option value="7_hari" <?= $f['periode'] === '7_hari' ? 'selected' : '' ?>>7 Hari Terakhir</option>
                <option value="bulan_ini" <?= $f['periode'] === 'bulan_ini' ? 'selected' : '' ?>>Bulan Ini</option>
                <option value="kustom" <?= $tanggal_kustom ? 'selected' : '' ?>>Pilih Tanggal...</option>
            </select>
        </div>

        <!-- kedua kotak tanggal duduk di baris yang sama, di samping pilihan
             periode; sisanya ikut menyusut karena kolomnya dibagi rata -->
        <div class="col-6 col-lg cod-tanggal-kustom <?= $tanggal_kustom ? '' : 'd-none' ?>">
            <input type="date" class="form-control form-control-sm" name="dari" id="filter_dari"
                value="<?= esc($f['dari']) ?>" max="<?= $hari_ini ?>" aria-label="Dari tanggal">
        </div>
        <div class="col-6 col-lg cod-tanggal-kustom <?= $tanggal_kustom ? '' : 'd-none' ?>">
            <input type="date" class="form-control form-control-sm" name="sampai" id="filter_sampai"
                value="<?= esc($f['sampai']) ?>" max="<?= $hari_ini ?>" aria-label="Sampai tanggal">
        </div>
    <?= form_close() ?>

    <?php if ($ada_filter_modal) : ?>
        <div class="mb-4 d-flex align-items-center justify-content-between rounded-2xl border border-ink-200 bg-[#fcfdfe] px-3.5 py-2.5 text-xs text-ink-600">
            <div>
                <i class="fal fa-filter text-primary me-1.5"></i>
                Filter aktif: <strong><?= esc($ringkasan_filter) ?></strong> &middot; Ditemukan <strong><?= $total ?></strong> pencatatan
            </div>
            <a href="<?= site_url('admin/cod') . ($f['status'] ? '?status=' . $f['status'] : '') ?>" class="text-danger fw-semibold text-decoration-none">
                <i class="fal fa-times-circle me-1"></i>Hapus Filter
            </a>
        </div>
    <?php endif; ?>

    <?php if ($sukses !== null) : ?>
        <div class="alert alert-success alert-dismissible fade show d-flex align-items-center gap-3 cod-flash" role="alert">
            <i class="fal fa-check-circle fs-5 flex-shrink-0"></i>
            <div class="flex-grow-1"><?= esc($sukses) ?></div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if ($gagal !== null) : ?>
        <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center gap-3 cod-flash" role="alert">
            <i class="fal fa-exclamation-circle fs-5 flex-shrink-0"></i>
            <div class="flex-grow-1"><?= esc($gagal) ?></div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if ($catatan === []) : ?>
        <div class="mb-4 rounded-2xl border border-dashed border-ink-200 bg-[#fcfdfe] px-4 py-6 text-center text-ink-500">
            <i class="fal fa-inbox block text-[1.35rem] text-ink-400 mb-2"></i>
            <?php if ($ada_filter) : ?>
                Tidak ada pencatatan COD yang cocok dengan filter yang dipilih.<br>
                <a href="<?= site_url('admin/cod') ?>" class="btn btn-sm btn-outline-primary mt-2">
                    <i class="fal fa-rotate-left me-1"></i> Reset Filter
                </a>
            <?php else : ?>
                Belum ada pencatatan COD. Tekan <strong>Tambah Pencatatan COD</strong> untuk mengisi yang pertama.
            <?php endif; ?>
        </div>
    <?php else : ?>
        <div class="row gx-3 gy-3">
            <?php foreach ($catatan as $catat) : ?>
                <?php
                $status   = (string) ($catat['status_transaksi'] ?? '');
                $keperluan = (string) ($catat['keperluan'] ?? '');
                $judul    = trim((string) $catat['nama_customer']);
                ?>
                <div class="col-12">
                    <div class="kartu-invoice h-100">
                        <div class="p-3 lg:p-4">
                            <div class="flex flex-wrap items-center gap-x-3 gap-y-2">
                                <span class="inline-flex h-[2.6rem] w-[2.6rem] shrink-0 items-center justify-center rounded-[0.9rem] border border-brand-100 bg-brand-50 text-lg text-brand-500">
                                    <i class="fal fa-truck-fast"></i>
                                </span>

                                <div class="min-w-0 grow">
                                    <h5 class="text-[1.15rem] font-extrabold tracking-[-0.01em] mb-0"><?= esc($judul) ?></h5>
                                    <p class="text-ink-500 text-xs mb-0">
                                        <?= esc($tanggal_panjang($catat['tanggal'] ?? null)) ?>
                                        <?php if ($jam_ringkas($catat['jam'] ?? null) !== '') : ?>
                                            &middot; <?= esc($jam_ringkas($catat['jam'] ?? null)) ?>
                                        <?php endif; ?>
                                    </p>
                                </div>

                                <?php if ($status !== '') : ?>
                                    <span class="rounded-full px-3 py-1 text-xs font-semibold <?= $pil_status($status) ?>"><?= esc($status) ?></span>
                                <?php endif; ?>

                                <?php if ($keperluan !== '') : ?>
                                    <span class="rounded-full border border-ink-200 bg-ink-50 px-3 py-1 text-xs font-semibold text-ink-700">
                                        <i class="fal fa-tag me-1 text-ink-400"></i><?= esc($keperluan) ?>
                                    </span>
                                <?php endif; ?>
                            </div>

                            <div class="row g-1 mt-2 text-sm text-ink-600">
                                <div class="col-12 col-sm-7">
                                    <div class="flex min-w-0 items-center gap-1 [&>.fal]:text-[0.85rem] [&>.fal]:text-ink-400">
                                        <i class="fal fa-mobile-screen"></i>
                                        <span class="text-ink-500">No. Hp:</span> <?= esc($catat['no_hp']) ?>
                                    </div>
                                    <!-- nama CS duduk di kolom yang sama, persis di bawah
                                         nomor HP; dulu ia didorong ke tepi kanan kartu
                                         sehingga terlihat menggantung sendiri -->
                                    <div class="flex min-w-0 items-center gap-1 [&>.fal]:text-[0.85rem] [&>.fal]:text-ink-400">
                                        <i class="fal fa-headset"></i>
                                        <span class="text-ink-500">Cs Yang Handle:</span>
                                        <?= ($catat['cs'] ?? '') !== '' ? esc($catat['cs']) : '<span class="text-ink-400">belum diisi</span>' ?>
                                    </div>
                                </div>
                                <div class="col-12 col-sm-5">
                                    <!-- sumber info sengaja dipalang ke tepi kanan kartu: ia
                                         penanda terakhir baris ini, jadi duduknya sejajar dengan
                                         tepi kanan catatan dan tombol aksi di bawahnya. Varian
                                         sm- dipakai karena di layar kecil kolomnya full-width,
                                         di sana rata kiri tetap lebih pas -->
                                    <div class="flex min-w-0 items-center gap-1 justify-content-sm-end [&>.fal]:text-[0.85rem] [&>.fal]:text-ink-400">
                                        <i class="fal fa-share-from-square"></i>
                                        <span class="text-ink-500">Sumber Info:</span>
                                        <?= ($catat['sumber'] ?? '') !== '' ? esc($catat['sumber']) : '<span class="text-ink-400">belum diisi</span>' ?>
                                    </div>
                                </div>
                            </div>

                            <div class="blok mt-3 rounded-2xl border border-ink-200 bg-[#fcfdfe] px-[1.15rem] py-4">
                                <h6 class="mb-2 flex items-center gap-1.5 text-[0.72rem] font-bold uppercase tracking-[0.09em] text-ink-500 [&_.fal]:text-[0.95rem] [&_.fal]:text-ink-400">
                                    <i class="fal fa-align-left"></i>
                                    Catatan
                                </h6>
                                <p class="cod-catatan mb-0 whitespace-pre-line break-words text-[0.95rem] text-ink-800"
                                    title="Klik untuk membaca penuh"><?= esc($catat['catatan']) ?></p>
                            </div>

                            <hr class="my-2 border-0 border-t border-ink-200 opacity-100" />

                            <div class="aksi flex flex-wrap items-center gap-1">
                                <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#modalSuntingCod" data-cod="<?= $isi_kartu($catat) ?>">
                                    <i class="fal fa-pencil"></i> Sunting
                                </button>
                                <?= form_open('admin/cod/hapus', ['class' => 'd-inline']) ?>
                                <?= form_hidden('id_cod', (string) $catat['id_cod']) ?>
                                <button type="submit" class="btn btn-outline-danger"
                                    title="Hapus catatan <?= esc($judul, 'attr') ?>"
                                    onclick="return confirm('<?= esc($peringatan_hapus($catat), 'js') ?>')">
                                    <i class="fal fa-trash-alt"></i> Hapus
                                </button>
                                <?= form_close() ?>
                                <span class="ms-auto text-xs text-ink-400">
                                    <?php if (($catat['pencatat'] ?? '') !== '') : ?>
                                        dicatat oleh <?= esc($catat['pencatat']) ?>
                                    <?php endif; ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if ($halaman > 1) : ?>
            <div class="mt-4">
                <?= \Config\Services::pager()->makeLinks($page, $per_halaman, $total, 'front_full') ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<!-- Form bersama di luar modal. Tombol tongnya sendiri ikut di dalam dropdown
     yang berada di dalam <form> catatan, dan <form> tidak boleh bersarang, jadi
     yang ada cuma satu form tersembunyi ini: JS mengisi nilainya lalu
     mengirimnya. -->
<?= form_open('admin/cod/hapus-pilihan', ['id' => 'formHapusPilihan', 'class' => 'd-none']) ?>
<?= form_hidden('id', '0') ?>
<?= form_close() ?>
<?= $this->endSection() ?>

<?= $this->section('modal') ?>
<!-- Tambah catatan -->
<div class="modal fade" id="modalTambahCod" tabindex="-1" aria-labelledby="modalTambahCodLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-cod">
        <?= form_open('admin/cod/simpan', ['class' => 'modal-content form-kartu']) ?>
        <div class="modal-header">
            <h5 class="modal-title" id="modalTambahCodLabel">Tambah Pencatatan COD</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
            <div class="row gx-3">
                <div class="col-12 col-sm-6 mb-3">
                    <?= form_label('Nama Customer', 'nama_customer_tambah', ['class' => 'form-label']) ?>
                    <?= form_input([
                        'class'       => 'form-control' . ($err_tambah('nama_customer') ? ' is-invalid' : ''),
                        'name'        => 'nama_customer',
                        'id'          => 'nama_customer_tambah',
                        'placeholder' => 'Nama yang datang',
                        'required'    => '',
                        'maxlength'   => 100,
                        'value'       => set_value('nama_customer'),
                    ]) ?>
                    <?php if ($err_tambah('nama_customer')) : ?>
                        <div class="invalid-feedback d-block"><?= esc($err_tambah('nama_customer')) ?></div>
                    <?php endif; ?>
                </div>

                <div class="col-12 col-sm-6 mb-3">
                    <?= form_label('Nomor HP', 'no_hp_tambah', ['class' => 'form-label']) ?>
                    <div class="cari-merged">
                        <i class="fal fa-phone cari-ikon"></i>
                        <?= form_input([
                            'class'       => 'form-control' . ($err_tambah('no_hp') ? ' is-invalid' : ''),
                            'name'        => 'no_hp',
                            'id'          => 'no_hp_tambah',
                            'type'        => 'text',
                            'inputmode'   => 'numeric',
                            'placeholder' => '081234567890',
                            'required'    => '',
                            'maxlength'   => 13,
                            'value'       => set_value('no_hp'),
                        ]) ?>
                    </div>
                    <?php if ($err_tambah('no_hp')) : ?>
                        <div class="invalid-feedback d-block"><?= esc($err_tambah('no_hp')) ?></div>
                    <?php endif; ?>
                </div>

                <div class="col-6 mb-3">
                    <?= form_label('Tanggal', 'tanggal_tambah', ['class' => 'form-label']) ?>
                    <?= form_input([
                        'class'    => 'form-control' . ($err_tambah('tanggal') ? ' is-invalid' : ''),
                        'name'     => 'tanggal',
                        'id'       => 'tanggal_tambah',
                        'type'     => 'date',
                        'required' => '',
                        'max'      => $hari_ini,
                        'value'    => set_value('tanggal', $hari_ini),
                    ]) ?>
                    <?php if ($err_tambah('tanggal')) : ?>
                        <div class="invalid-feedback d-block"><?= esc($err_tambah('tanggal')) ?></div>
                    <?php endif; ?>
                </div>

                <div class="col-6 mb-3">
                    <?= form_label('Jam Datang', 'jam_tambah', ['class' => 'form-label']) ?>
                    <?= form_input([
                        'class'    => 'form-control' . ($err_tambah('jam') ? ' is-invalid' : ''),
                        'name'     => 'jam',
                        'id'       => 'jam_tambah',
                        'type'     => 'time',
                        'required' => '',
                        'value'    => set_value('jam'),
                    ]) ?>
                    <?php if ($err_tambah('jam')) : ?>
                        <div class="invalid-feedback d-block"><?= esc($err_tambah('jam')) ?></div>
                    <?php endif; ?>
                </div>

                <div class="col-6 mb-3">
                    <?= form_label('Keperluan', 'keperluan_tambah', ['class' => 'form-label']) ?>
                    <?= $kotak_pilihan('keperluan', 'keperluan_tambah', $pilihan['keperluan'], (string) set_value('keperluan'), (string) $err_tambah('keperluan'), 'Pilih') ?>
                </div>

                <div class="col-6 mb-3">
                    <?= form_label('Status Transaksi', 'status_tambah', ['class' => 'form-label']) ?>
                    <?= $kotak_pilihan('status', 'status_tambah', $pilihan['status'], (string) set_value('status'), (string) $err_tambah('status'), 'Pilih', $boleh_status_baru) ?>
                </div>

                <div class="col-12 col-sm-6 mb-3">
                    <?= form_label('Nama CS yang Handle', 'cs_tambah', ['class' => 'form-label']) ?>
                    <?= $kotak_pilihan('cs', 'cs_tambah', $pilihan['cs'], (string) set_value('cs'), (string) $err_tambah('cs'), 'Pilih nama CS') ?>
                </div>

                <div class="col-12 col-sm-6 mb-3">
                    <?= form_label('Sumber Informasi', 'sumber_tambah', ['class' => 'form-label']) ?>
                    <?= form_input([
                        'class'       => 'form-control' . ($err_tambah('sumber') ? ' is-invalid' : ''),
                        'name'        => 'sumber',
                        'id'          => 'sumber_tambah',
                        'placeholder' => 'Dari mana customer ini tahu tentang toko',
                        'required'    => '',
                        'maxlength'   => 80,
                        'value'       => set_value('sumber'),
                    ]) ?>
                    <?php if ($err_tambah('sumber')) : ?>
                        <div class="invalid-feedback d-block"><?= esc($err_tambah('sumber')) ?></div>
                    <?php endif; ?>
                </div>

                <div class="col-12 mb-1">
                    <?= form_label('Catatan', 'catatan_tambah', ['class' => 'form-label']) ?>
                    <?= form_textarea([
                        'class'       => 'form-control' . ($err_tambah('catatan') ? ' is-invalid' : ''),
                        'name'        => 'catatan',
                        'id'          => 'catatan_tambah',
                        'rows'        => 3,
                        'placeholder' => 'Yang dibicarakan, yang dijanjikan, atau alasan cancel',
                        'required'    => '',
                        'maxlength'   => 5000,
                    ]) ?>
                    <?php if ($err_tambah('catatan')) : ?>
                        <div class="invalid-feedback d-block"><?= esc($err_tambah('catatan')) ?></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
            <button type="submit" class="btn btn-dark">Simpan</button>
        </div>
        <?= form_close() ?>
    </div>
</div>

<!-- Sunting catatan -->
<div class="modal fade" id="modalSuntingCod" tabindex="-1" aria-labelledby="modalSuntingCodLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-cod">
        <?= form_open('admin/cod/sunting', ['class' => 'modal-content form-kartu']) ?>
        <?= form_hidden('id_cod', (string) set_value('id_cod')) ?>
        <div class="modal-header">
            <h5 class="modal-title" id="modalSuntingCodLabel">Sunting Pencatatan COD</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
            <div class="row gx-3">
                <div class="col-12 col-sm-6 mb-3">
                    <?= form_label('Nama Customer', 'nama_customer_sunting', ['class' => 'form-label']) ?>
                    <?= form_input([
                        'class'       => 'form-control' . ($err_sunting('nama_customer') ? ' is-invalid' : ''),
                        'name'        => 'nama_customer',
                        'id'          => 'nama_customer_sunting',
                        'placeholder' => 'Nama yang datang',
                        'required'    => '',
                        'maxlength'   => 100,
                        'value'       => $gagal_sunting ? set_value('nama_customer') : '',
                    ]) ?>
                    <?php if ($err_sunting('nama_customer')) : ?>
                        <div class="invalid-feedback d-block"><?= esc($err_sunting('nama_customer')) ?></div>
                    <?php endif; ?>
                </div>

                <div class="col-12 col-sm-6 mb-3">
                    <?= form_label('Nomor HP', 'no_hp_sunting', ['class' => 'form-label']) ?>
                    <div class="cari-merged">
                        <i class="fal fa-phone cari-ikon"></i>
                        <?= form_input([
                            'class'       => 'form-control' . ($err_sunting('no_hp') ? ' is-invalid' : ''),
                            'name'        => 'no_hp',
                            'id'          => 'no_hp_sunting',
                            'type'        => 'text',
                            'inputmode'   => 'numeric',
                            'placeholder' => '081234567890',
                            'required'    => '',
                            'maxlength'   => 13,
                            'value'       => $gagal_sunting ? set_value('no_hp') : '',
                        ]) ?>
                    </div>
                    <?php if ($err_sunting('no_hp')) : ?>
                        <div class="invalid-feedback d-block"><?= esc($err_sunting('no_hp')) ?></div>
                    <?php endif; ?>
                </div>

                <div class="col-6 mb-3">
                    <?= form_label('Tanggal', 'tanggal_sunting', ['class' => 'form-label']) ?>
                    <?= form_input([
                        'class'    => 'form-control' . ($err_sunting('tanggal') ? ' is-invalid' : ''),
                        'name'     => 'tanggal',
                        'id'       => 'tanggal_sunting',
                        'type'     => 'date',
                        'required' => '',
                        'max'      => $hari_ini,
                        'value'    => $gagal_sunting ? set_value('tanggal') : '',
                    ]) ?>
                    <?php if ($err_sunting('tanggal')) : ?>
                        <div class="invalid-feedback d-block"><?= esc($err_sunting('tanggal')) ?></div>
                    <?php endif; ?>
                </div>

                <div class="col-6 mb-3">
                    <?= form_label('Jam Datang', 'jam_sunting', ['class' => 'form-label']) ?>
                    <?= form_input([
                        'class'    => 'form-control' . ($err_sunting('jam') ? ' is-invalid' : ''),
                        'name'     => 'jam',
                        'id'       => 'jam_sunting',
                        'type'     => 'time',
                        'required' => '',
                        'value'    => $gagal_sunting ? set_value('jam') : '',
                    ]) ?>
                    <?php if ($err_sunting('jam')) : ?>
                        <div class="invalid-feedback d-block"><?= esc($err_sunting('jam')) ?></div>
                    <?php endif; ?>
                </div>

                <div class="col-6 mb-3">
                    <?= form_label('Keperluan', 'keperluan_sunting', ['class' => 'form-label']) ?>
                    <?= $kotak_pilihan('keperluan', 'keperluan_sunting', $pilihan['keperluan'], $gagal_sunting ? (string) set_value('keperluan') : '', (string) $err_sunting('keperluan'), 'Pilih') ?>
                </div>

                <div class="col-6 mb-3">
                    <?= form_label('Status Transaksi', 'status_sunting', ['class' => 'form-label']) ?>
                    <?= $kotak_pilihan('status', 'status_sunting', $pilihan['status'], $gagal_sunting ? (string) set_value('status') : '', (string) $err_sunting('status'), 'Pilih', $boleh_status_baru) ?>
                </div>

                <div class="col-12 col-sm-6 mb-3">
                    <?= form_label('Nama CS yang Handle', 'cs_sunting', ['class' => 'form-label']) ?>
                    <?= $kotak_pilihan('cs', 'cs_sunting', $pilihan['cs'], $gagal_sunting ? (string) set_value('cs') : '', (string) $err_sunting('cs'), 'Pilih nama CS') ?>
                </div>

                <div class="col-12 col-sm-6 mb-3">
                    <?= form_label('Sumber Informasi', 'sumber_sunting', ['class' => 'form-label']) ?>
                    <?= form_input([
                        'class'       => 'form-control' . ($err_sunting('sumber') ? ' is-invalid' : ''),
                        'name'        => 'sumber',
                        'id'          => 'sumber_sunting',
                        'placeholder' => 'Dari mana customer ini tahu tentang toko',
                        'required'    => '',
                        'maxlength'   => 80,
                        'value'       => $gagal_sunting ? set_value('sumber') : '',
                    ]) ?>
                    <?php if ($err_sunting('sumber')) : ?>
                        <div class="invalid-feedback d-block"><?= esc($err_sunting('sumber')) ?></div>
                    <?php endif; ?>
                </div>

                <div class="col-12 mb-1">
                    <?= form_label('Catatan', 'catatan_sunting', ['class' => 'form-label']) ?>
                    <?= form_textarea([
                        'class'      => 'form-control' . ($err_sunting('catatan') ? ' is-invalid' : ''),
                        'name'       => 'catatan',
                        'id'         => 'catatan_sunting',
                        'rows'       => 3,
                        'required'   => '',
                        'maxlength' => 5000,
                    ]) ?>
                    <?php if ($err_sunting('catatan')) : ?>
                        <div class="invalid-feedback d-block"><?= esc($err_sunting('catatan')) ?></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
            <button type="submit" class="btn btn-dark">Simpan Perubahan</button>
        </div>
        <?= form_close() ?>
    </div>
</div>

<!-- Popus tambah pilihan; satu-satunya dipakai semua dropdown supaya daftar
     pilihan tidak memanjang ke bawah -->
<div class="modal fade" id="modalPilihanBaru" tabindex="-1" aria-labelledby="modalPilihanBaruLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content form-kartu">
            <div class="modal-header">
                <h5 class="modal-title" id="modalPilihanBaruLabel">Tambah Pilihan Baru</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <?= form_label('Nama pilihan', 'pilihanBaru', ['class' => 'form-label']) ?>
                <span class="d-block small text-muted mb-2" id="pilihanBaruKolom"></span>
                <?= form_input([
                    'class'       => 'form-control',
                    'id'          => 'pilihanBaru',
                    'type'        => 'text',
                    'maxlength'   => 80,
                    'placeholder' => 'Tulis nama pilihan baru',
                    'autocomplete' => 'off',
                ]) ?>
                <div class="form-text">Pilihan baru ikut tersimpan setelah catatan disimpan.</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-dark" id="pilihanBaruSimpan">Simpan</button>
            </div>
        </div>
    </div>
</div>
<!-- Catatan penuh: kartu hanya menampilkan tiga baris pertamanya, jadi isi yang
     panjang dibaca di sini. scrollable supaya catatan 5000 karakter tidak
     mendorong layar. -->
<div class="modal fade" id="modalCatatanPenuh" tabindex="-1" aria-labelledby="modalCatatanPenuhLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-cod">
        <div class="modal-content form-kartu">
            <div class="modal-header">
                <h5 class="modal-title" id="modalCatatanPenuhLabel">Catatan lengkap</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-0 whitespace-pre-line break-words text-[0.95rem] text-ink-800" id="catatanPenuhIsi"></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>
<!-- Konfirmasi hapus pilihan. Dipakai alih-alih window.confirm() karena peramban
     boleh memblok dialog itu (Chrome menawarkan "Prevent this page from creating
     additional dialogs" di dalam kotaknya sendiri); kalau sudah diblok, klik tong
     sampah tampak mati dan pilihan tidak terhapus tanpa pesan apa pun. -->
<div class="modal fade" id="modalHapusPilihan" tabindex="-1" aria-labelledby="modalHapusPilihanLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-hapus-pilihan">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title" id="modalHapusPilihanLabel"><i class="fal fa-trash-alt"></i> Hapus Pilihan</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-0 text-[0.88rem] text-ink-600" id="hapusPilihanIsi"></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-sm btn-danger" id="hapusPilihanYa">Hapus</button>
            </div>
        </div>
    </div>
</div>
<!-- Pemberitahuan hapus pilihan. Tetapkan posisinya di luar modal supaya tetap
     terbaca setelah pop-up konfirmasi ditutup, dan hilang sendiri dalam 3 detik. -->
<div class="cod-pesan" id="hapusPilihanPesan" role="status" aria-live="polite"></div>
<?= $this->endSection() ?>

<?= $this->section('js') ?>
<?php
// Modal yang harus terbuka lagi setelah gagal menyimpan dibaca dari isian lama,
// sama seperti halaman Produk: pengguna tidak perlu mengetik ulang semuanya.
$buka_tambah  = $gagal_tambah ? 'true' : 'false';
$buka_sunting = $gagal_sunting ? 'true' : 'false';
$isi_lama     = $gagal_sunting ? json_encode([
    'id'        => (int) set_value('id_cod'),
    'tanggal'   => (string) set_value('tanggal'),
    'jam'       => (string) set_value('jam'),
    'nama'      => (string) set_value('nama_customer'),
    'hp'        => (string) set_value('no_hp'),
    'keperluan' => (string) set_value('keperluan'),
    'status'    => (string) set_value('status'),
    'catatan'   => (string) set_value('catatan'),
    'cs'        => (string) set_value('cs'),
    'sumber'    => (string) set_value('sumber'),
], JSON_THROW_ON_ERROR) : 'null';

// fetch hapus pilihan mengirim token CSRF yang sama dengan isian form
// tersembunyinya, jadi rute dan filternya tidak perlu dua jalur periksa
$nama_token  = csrf_token();
$nilai_token = csrf_hash();

$js = <<< JS
    $(function() {
        var modalTambah = document.getElementById('modalTambahCod');
        var modalSunting = document.getElementById('modalSuntingCod');
        var bukaTambah = {$buka_tambah};
        var bukaSunting = {$buka_sunting};
        var suntingLama = {$isi_lama};

        var bidang = {
            id: modalSunting.querySelector('[name="id_cod"]'),
            tanggal: modalSunting.querySelector('[name="tanggal"]'),
            jam: modalSunting.querySelector('[name="jam"]'),
            nama: modalSunting.querySelector('[name="nama_customer"]'),
            hp: modalSunting.querySelector('[name="no_hp"]'),
            keperluan: modalSunting.querySelector('[name="keperluan"]'),
            status: modalSunting.querySelector('[name="status"]'),
            catatan: modalSunting.querySelector('[name="catatan"]'),
            cs: modalSunting.querySelector('[name="cs"]'),
            sumber: modalSunting.querySelector('[name="sumber"]')
        };

        // ---- dropdown pilihan: nilainya tetap hidden field, tulisannya di tombol
        var modalPilihan = document.getElementById('modalPilihanBaru');
        var kolomPilihan = document.getElementById('pilihanBaru');
        var judulPilihan = document.getElementById('pilihanBaruKolom');
        var kotakSasaran  = null;

        // ---- form tersembunyi untuk aksi hapus pilihan (admin + superadmin),
        //      supaya tidak perlu <form> di dalam <form> catatan
        var formPilihan = document.getElementById('formHapusPilihan');
        var modalCatatan = document.getElementById('modalCatatanPenuh');
        var modalHapus   = document.getElementById('modalHapusPilihan');
        var isiHapus     = document.getElementById('hapusPilihanIsi');
        var kotakPesan   = document.getElementById('hapusPilihanPesan');
        var waktuPesan   = null;
        var sasaranHapus = null;
        var namaToken    = '{$nama_token}';
        var nilaiToken   = '{$nilai_token}';

        function kolomNilai(kotak) {
            return kotak.querySelector('input[type="hidden"]');
        }

        function tambahOpsi(kotak, isi) {
            var baru = document.createElement('button');

            baru.type = 'button';
            baru.className = 'dropdown-item cod-opsi';
            baru.setAttribute('data-label', isi);
            baru.textContent = isi;
            kotak.querySelector('.dropdown-divider').before(baru);

            return baru;
        }

        function adaOpsi(kotak, isi) {
            var semua = kotak.querySelectorAll('.cod-opsi');

            for (var i = 0; i < semua.length; i++) {
                if (semua[i].getAttribute('data-label') === isi) {
                    return semua[i];
                }
            }

            return null;
        }

        function tetapkan(kotak, isi, tutup) {
            var label = kotak.querySelector('.cod-pilih-label');

            if (isi !== '' && ! adaOpsi(kotak, isi)) {
                tambahOpsi(kotak, isi);
            }

            kolomNilai(kotak).value = isi;
            label.textContent = isi === '' ? kolomNilai(kotak).getAttribute('data-kosong') : isi;
            label.classList.toggle('cod-kosong', isi === '');

            if (tutup) {
                bootstrap.Dropdown.getOrCreateInstance(kotak.querySelector('.cod-pilih-tombol')).hide();
            }
        }

        // label tombol harus ikut nilai hidden field: form.reset() mengembalikan
        // hidden field ke bawaan HTML tapi tidak menyentuh tulisan di tombolnya
        function sinkronPilih(dalam) {
            var semua = dalam.querySelectorAll('.cod-pilih');

            for (var i = 0; i < semua.length; i++) {
                tetapkan(semua[i], kolomNilai(semua[i]).value, false);
            }
        }

        // form.reset() saja tidak cukup di sini: nilai yang tadi dipilih lewat
        // JS ikut tinggal di hidden field, jadi dropdown dimatikan dari nol
        function kosongkanPilih(dalam) {
            var semua = dalam.querySelectorAll('.cod-pilih');

            for (var i = 0; i < semua.length; i++) {
                tetapkan(semua[i], '', false);
            }
        }

        function namaKolom(kotak) {
            var judul = kotak.parentElement.querySelector('.form-label');

            return judul ? judul.textContent : '';
        }

        // popus, bukan kolom ketik di dalam dropdown, supaya daftarnya tidak
        // memanjang ke bawah setiap kali ada pilihan baru
        function bukaPopus(kotak) {
            kotakSasaran = kotak;
            judulPilihan.textContent = namaKolom(kotak);
            kolomPilihan.value = '';

            bootstrap.Dropdown.getOrCreateInstance(kotak.querySelector('.cod-pilih-tombol')).hide();
            bootstrap.Modal.getOrCreateInstance(modalPilihan).show();
        }

        function simpanPopus() {
            var isi = (kolomPilihan.value || '').replace(/\s+/g, ' ').trim();

            if (isi === '') {
                kolomPilihan.focus();

                return;
            }

            if (kotakSasaran) {
                tetapkan(kotakSasaran, isi, false);
            }

            bootstrap.Modal.getOrCreateInstance(modalPilihan).hide();
        }

        kolomPilihan.addEventListener('keydown', function (event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                simpanPopus();
            }
        });

        document.getElementById('pilihanBaruSimpan').addEventListener('click', simpanPopus);

        // Bootstrap memindahkan fokus ke elemen modalnya sendiri, jadi kolom
        // ketiknya baru bisa diisi setelah popus benar-benar terbuka
        modalPilihan.addEventListener('shown.bs.modal', function () {
            kolomPilihan.focus();
        });

        modalPilihan.addEventListener('hidden.bs.modal', function () {
            // Bootstrap ikut melepas modal-open ketika popus ini ditutup padahal
            // modal catatannya masih terbuka, yang membuat layar tidak bisa digulir
            if (document.querySelector('.modal.show') && ! document.body.classList.contains('modal-open')) {
                document.body.classList.add('modal-open');
            }
        });

        // satu pendengar untuk semua dropdown: isinya dibuat JS juga, jadi
        // delegation lebih murah daripada memasang ulang tiap pilihan baru
        document.addEventListener('click', function (event) {
            if (! event.target.closest) {
                return;
            }

            // tong di kanan tiap pilihan dirender untuk admin dan superadmin,
            // dan dimatikan untuk yang masih dipakai catatan. Jawabannya dibaca
            // lewat fetch supaya pilihan hilang di tempat, tanpa memuat ulang
            // halaman dan tanpa keluar dari popus konfirmasinya.
            var buang = event.target.closest('.cod-hapus-pilihan');

            if (buang) {
                sasaranHapus = buang;
                isiHapus.textContent = 'Hapus "' + buang.getAttribute('data-label') + '" dari daftar?';

                bootstrap.Dropdown.getOrCreateInstance(buang.closest('.cod-pilih').querySelector('.cod-pilih-tombol')).hide();
                bootstrap.Modal.getOrCreateInstance(modalHapus).show();

                return;
            }

            var kotak = event.target.closest('.cod-pilih');

            if (! kotak) {
                return;
            }

            if (event.target.closest('.cod-opsi')) {
                tetapkan(kotak, event.target.closest('.cod-opsi').getAttribute('data-label'), true);

                return;
            }

            if (event.target.closest('.cod-pilih-baru')) {
                bukaPopus(kotak);
            }
        });

        // pesan kecil yang hilang sendiri; hasil hapus pilihan dibacakan di sini
        // supaya tidak perlu memuat ulang halaman hanya untuk membaca jawabannya
        function pesan3Detik(isi, salah) {
            kotakPesan.textContent = isi;
            kotakPesan.classList.toggle('cod-pesan-gagal', !! salah);
            kotakPesan.classList.add('cod-pesan-tampil');

            if (waktuPesan) {
                window.clearTimeout(waktuPesan);
            }

            waktuPesan = window.setTimeout(function () {
                kotakPesan.classList.remove('cod-pesan-tampil');
            }, 3000);
        }

        // banner "berhasil ..." dari simpan/hapus catatan ikut hilang sendiri
        // dalam 3 detik, supaya tidak menggantung di atas daftar
        document.querySelectorAll('.cod-flash').forEach(function (banner) {
            window.setTimeout(function () {
                if (banner.isConnected) {
                    bootstrap.Alert.getOrCreateInstance(banner).close();
                }
            }, 3000);
        });

        // satu pilihan bisa muncul di dua dropdown (popus tambah dan popus sunting)
        // dan di kartu mana pun tidak ada jejaknya, jadi semua barisnya ikut
        // dibuang; kalau pilihan itu sedang terpilih di salah satu form, formnya
        // dikosongkan supaya tidak mengirim label yang sudah tidak ada
        function buangBaris(id, label) {
            var semua = document.querySelectorAll('.cod-hapus-pilihan[data-id="' + id + '"]');

            for (var i = 0; i < semua.length; i++) {
                var kotak = semua[i].closest('.cod-pilih');
                var baris = semua[i].closest('.cod-baris');

                if (kotak && kolomNilai(kotak).value === label) {
                    tetapkan(kotak, '', false);
                }

                if (baris && baris.parentElement) {
                    baris.parentElement.removeChild(baris);
                }
            }
        }

        document.getElementById('hapusPilihanYa').addEventListener('click', function () {
            if (! sasaranHapus) {
                return;
            }

            var tombol = sasaranHapus;
            var kotak  = tombol.closest('.cod-pilih');
            var isian  = new FormData();

            isian.append(namaToken, nilaiToken);
            isian.append('id', tombol.getAttribute('data-id'));
            this.disabled = true;

            // popus konfirmasi harus ikut hilang setelah jawabannya diterima —
            // kalau tidak, layarnya masih menutupi daftar yang baru saja berubah.
            // Untuk hasil berhasil, dropdownnya dibuka lagi begitu popus ini
            // benar-benar tertutup, jadi menghapus satu pilihan tidak membuat
            // pengguna keluar masuk popus catatan
            var tutup = function (bukaLagi) {
                var induk = bukaLagi && kotak ? kotak.closest('.modal') : null;

                if (induk && induk.classList.contains('show')) {
                    modalHapus.addEventListener('hidden.bs.modal', function () {
                        bootstrap.Dropdown.getOrCreateInstance(kotak.querySelector('.cod-pilih-tombol')).show();
                    }, { once: true });
                }

                bootstrap.Modal.getOrCreateInstance(modalHapus).hide();
            };

            // jalurnya diambil dari action form tapi hanya pathname-nya, jadi
            // permintaannya selalu ke asal halaman ini dibuka (localhost tidak
            // ikut dilempar ke host tunnel di .env)
            fetch(new URL(formPilihan.getAttribute('action'), window.location.href).pathname, {
                method: 'post',
                body: isian,
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            }).then(function (jawab) {
                return jawab.json().catch(function () {
                    return { ok: false, pesan: 'Jawaban server tidak bisa dibaca.' };
                });
            }).then(function (hasil) {
                this.disabled = false;

                if (! hasil.ok) {
                    pesan3Detik(hasil.pesan || 'Pilihan tidak jadi dihapus.', true);
                    tutup(false);

                    return;
                }

                buangBaris(tombol.getAttribute('data-id'), tombol.getAttribute('data-label'));
                pesan3Detik(hasil.pesan || ('Pilihan "' + tombol.getAttribute('data-label') + '" dihapus.'), false);
                tutup(true);
            }.bind(this)).catch(function () {
                this.disabled = false;
                pesan3Detik('Penghapusan gagal: tidak terhubung ke server.', true);
                tutup(false);
            }.bind(this));
        });

        // sama seperti popus tambah pilihan: modal ini dibuka di atas modal
        // catatan, jadi modal-open harus dipasang ulang supaya layar tetap bisa
        // digulir ketika hapus dibatalkan
        modalHapus.addEventListener('hidden.bs.modal', function () {
            sasaranHapus = null;

            if (document.querySelector('.modal.show') && ! document.body.classList.contains('modal-open')) {
                document.body.classList.add('modal-open');
            }
        });

        // dua kolom ini dibersihkan saat diketik, bukan ditunggu sampai Simpan:
        // nomor HP cuma angka (maksimal 13 digit, sudah dibatasi maxlength) dan
        // nama customer cuma huruf, karena keduanya pasti ditolak validasi server
        function tahanIsian(pilih, buang) {
            var semua = document.querySelectorAll(pilih);

            for (var i = 0; i < semua.length; i++) {
                semua[i].addEventListener('input', function (event) {
                    var bersih = event.target.value.replace(buang, '');

                    if (bersih !== event.target.value) {
                        event.target.value = bersih;
                    }
                });
            }
        }

        tahanIsian('input[name="no_hp"]', /\D/g);
        tahanIsian('input[name="nama_customer"]', /[^\p{L}\s]/gu);

        function isiForm(data) {
            bidang.id.value = data.id;
            bidang.tanggal.value = data.tanggal;
            bidang.jam.value = data.jam;
            bidang.nama.value = data.nama;
            bidang.hp.value = data.hp;
            bidang.keperluan.value = data.keperluan;
            bidang.status.value = data.status;
            bidang.catatan.value = data.catatan;
            bidang.cs.value = data.cs;
            bidang.sumber.value = data.sumber;

            sinkronPilih(modalSunting);
        }

        modalSunting.addEventListener('show.bs.modal', function (event) {
            // sunting yang gagal disimpan: nilai yang tadi diketik yang dipulihkan,
            // bukan nilai lama yang masih tertinggal di kartu
            if (suntingLama) {
                isiForm(suntingLama);
                suntingLama = null;

                return;
            }

            var tombol = event.relatedTarget;

            if (tombol && tombol.hasAttribute('data-cod')) {
                isiForm(JSON.parse(tombol.getAttribute('data-cod')));
            }
        });

        // ditutup = mulai dari nol. Tanpa ini, isian percobaan sebelumnya masih
        // tertinggal di dalam popup dan tombol Tambah berikutnya membuka form yang
        // sudah terisi
        modalTambah.addEventListener('hidden.bs.modal', function () {
            var form = modalTambah.querySelector('form');

            // reset() mengembalikan tiap kontrol ke keadaan bawaan HTML, termasuk
            // tanggal hari ini yang ikut ter-render sebagai value
            if (form) {
                form.reset();
                kosongkanPilih(modalTambah);
            }

            var salah = modalTambah.querySelectorAll('.is-invalid');

            for (var i = 0; i < salah.length; i++) {
                salah[i].classList.remove('is-invalid');
            }
        });

        // kartu cuma menampilkan tiga baris pertama catatannya, jadi teks penuh
        // dibuka di popus tersendiri
        var catatan = document.querySelectorAll('.cod-catatan');

        for (var n = 0; n < catatan.length; n++) {
            catatan[n].addEventListener('click', function () {
                document.getElementById('catatanPenuhIsi').textContent = this.textContent;
                bootstrap.Modal.getOrCreateInstance(modalCatatan).show();
            });
        }

        // Filter periode: "Pilih Tanggal..." cuma membuka dua kotak tanggalnya,
        // pilihan lain langsung dipakai karena filternya sekarang duduk di
        // halaman, bukan di dalam popus lagi
        var selPeriode  = document.getElementById('filter_periode');
        var kotakTanggal = document.querySelectorAll('.cod-tanggal-kustom');
        var isiDari     = document.getElementById('filter_dari');
        var isiSampai   = document.getElementById('filter_sampai');

        function tampilTanggal(tampil) {
            for (var t = 0; t < kotakTanggal.length; t++) {
                kotakTanggal[t].classList.toggle('d-none', ! tampil);
            }
        }

        if (selPeriode) {
            selPeriode.addEventListener('change', function () {
                if (this.value === 'kustom') {
                    tampilTanggal(true);
                    if (isiDari) isiDari.focus();

                    return;
                }

                tampilTanggal(false);
                if (isiDari) isiDari.value = '';
                if (isiSampai) isiSampai.value = '';
                this.form.submit();
            });
        }

        // Batasi semua input tanggal agar tidak melebihi hari ini / tahun ini
        var batasHariIni = '{$hari_ini}';
        ['tanggal_tambah', 'tanggal_sunting', 'filter_dari', 'filter_sampai'].forEach(function (id) {
            var el = document.getElementById(id);
            if (! el) return;
            var batasi = function () {
                if (el.value && el.value > batasHariIni) {
                    el.value = batasHariIni;
                }
            };
            el.addEventListener('change', batasi);
            el.addEventListener('input', batasi);
            el.addEventListener('blur', batasi);
        });

        // dipasang setelah batasi() supaya tanggalnya dirapikan dulu, baru
        // filternya jalan
        [isiDari, isiSampai].forEach(function (el) {
            if (! el) return;
            el.addEventListener('change', function () {
                this.form.submit();
            });
        });

        // ---- Tombol geser strip pil status, pola yang sama dengan strip tab di
        //      halaman semua transaksi juragan: panahnya hanya tampil kalau pilnya
        //      memang lebih lebar dari wadahnya (class .bisa-geser dipasang di
        //      sini) dan ikut mati begitu mentok di ujung.
        var wadahPil = document.querySelector('.toolbar-transaksi__geser');

        if (wadahPil) {
            var jalurPil  = wadahPil.querySelector('.toolbar-transaksi__tab');
            var pilKiri   = wadahPil.querySelector('[data-geser="-1"]');
            var pilKanan  = wadahPil.querySelector('[data-geser="1"]');
            var kunciPos  = 'cod_pil_status_pos';

            function ukurPil() {
                wadahPil.classList.toggle('bisa-geser', jalurPil.scrollWidth - jalurPil.clientWidth > 2);
                pilKiri.disabled = jalurPil.scrollLeft <= 2;
                pilKanan.disabled = jalurPil.scrollLeft + jalurPil.clientWidth >= jalurPil.scrollWidth - 2;
            }

            function geserPil(arah) {
                var langkah = Math.max(120, Math.round(jalurPil.clientWidth * 0.75));

                jalurPil.scrollBy({ left: langkah * arah, behavior: 'smooth' });
            }

            // Klik pil memuat halaman ulang dan strip yang sedang digeser jatuh
            // kembali ke nol, sehingga pil yang baru aktif terlihat melompat.
            // Posisi strip disimpan tepat sebelum berpindah lalu dipasang lagi di
            // halaman berikutnya, jadi strip tinggal di tempatnya.
            jalurPil.addEventListener('click', function (event) {
                if (event.target.closest('a.btn')) {
                    try { window.sessionStorage.setItem(kunciPos, String(jalurPil.scrollLeft)); } catch (err) {}
                }
            });

            function pulihkanPil() {
                var simpanan = null;

                try {
                    simpanan = window.sessionStorage.getItem(kunciPos);
                    window.sessionStorage.removeItem(kunciPos);
                } catch (err) {}

                if (simpanan === null || isNaN(parseFloat(simpanan))) {
                    return;
                }

                jalurPil.scrollLeft = parseFloat(simpanan);
            }

            // hanya digeser kalau pil yang sedang aktif benar-benar tidak terlihat;
            // halaman yang dimuat ulang tidak pernah menggeser strip yang masih pas
            function tampilkanPilAktif() {
                var aktif = jalurPil.querySelector('.btn-primary');

                if (! aktif) {
                    return;
                }

                var wadah = jalurPil.getBoundingClientRect();
                var pil   = aktif.getBoundingClientRect();

                if (pil.left >= wadah.left + 8 && pil.right <= wadah.right - 8) {
                    return;
                }

                var tengah = (pil.left - wadah.left) + jalurPil.scrollLeft + pil.width / 2;
                var maks   = jalurPil.scrollWidth - jalurPil.clientWidth;

                jalurPil.scrollLeft = Math.max(0, Math.min(tengah - jalurPil.clientWidth / 2, maks));
            }

            pilKiri.addEventListener('click', function () { geserPil(-1); });
            pilKanan.addEventListener('click', function () { geserPil(1); });
            jalurPil.addEventListener('scroll', ukurPil, { passive: true });
            window.addEventListener('resize', ukurPil);
            window.addEventListener('load', ukurPil);

            // urutannya penting: ukurPil() dulu supaya kedua panah ikut tampil dan
            // memakan tempatnya sendiri, baru posisi disimpan dipasang. Kalau tidak,
            // strip masih terhitung selebar penuh sehingga scrollLeft yang dipulihkan
            // ikut dipotong ke batas yang salah (180 -> 150 di layar 390px).
            ukurPil();
            pulihkanPil();
            tampilkanPilAktif();
            ukurPil();
        }

        if (bukaTambah) {
            bootstrap.Modal.getOrCreateInstance(modalTambah).show();
        }

        if (bukaSunting) {
            bootstrap.Modal.getOrCreateInstance(modalSunting).show();
        }
    });
JS;

echo '<script>' . (new Tholu\Packer\Packer($js, 'Normal', true, false, true))->pack() . '</script>';
?>
<?= $this->endSection() ?>
