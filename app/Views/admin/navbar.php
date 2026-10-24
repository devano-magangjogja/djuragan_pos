<?= $this->include('template/feedback') ?>
<?php
$di_halaman = trim(uri_string(), '/');
$levelSesi  = session()->get('level') ?? ($_SESSION['level'] ?? '');
$namaSesi   = session()->get('name') ?? ($_SESSION['name'] ?? 'Admin');
$namaInisial = strtoupper(mb_substr($namaSesi, 0, 1));

// Deteksi menu induk aktif
$isDasborAktif   = url_is('admin/dasbor*') || $di_halaman === 'admin';
$isTrxAktif      = url_is('admin/invoices*') || url_is('admin/cod*');
$isProdukAktif   = url_is('admin/produk*') || url_is('admin/poin*');
$isCrmAktif      = url_is('admin/crm*');
$isKpiAktif      = url_is('admin/kpi*');
$isLaporanAktif  = url_is('admin/laporan*');
$isSettingsAktif = url_is('admin/settings*');
$isMasukanAktif  = url_is('admin/masukan*');

// Submenu Transaksi
// 'invoices' tetap satu halaman dengan 'cod': yang pertama dipakai untuk orderan
// yang jadi nota, yang kedua untuk kunjungan customer yang tidak jadi orderan
$transaksiSubmenu = [
    ['admin/invoices/lihat/semua/semua', 'Tracking Transaksi', 'fa-receipt', $di_halaman === 'admin/invoices' || str_starts_with($di_halaman, 'admin/invoices/')],
    ['admin/cod', 'Pencatatan COD', 'fa-truck-fast', str_starts_with($di_halaman, 'admin/cod')],
];

// Submenu Produk: daftar stok selalu ada; laporan poin gaji khusus Superadmin.
$produkSubmenu = [
    ['admin/produk', 'Produk & Stok', 'fa-box-alt', $di_halaman === 'admin/produk' || str_starts_with($di_halaman, 'admin/produk/')],
];

if ($levelSesi === 'superadmin') {
    $produkSubmenu[] = ['admin/poin', 'Laporan Poin', 'fa-coins', str_starts_with($di_halaman, 'admin/poin')];
}

// Submenu KPI: Ringkasan dan Ranking untuk admin & superadmin. Menentukan target
// dan bobot tetap hak Superadmin, jadi menunya tidak dirender untuk yang lain.
$kpiSubmenu = [
    ['admin/kpi', 'Ringkasan', 'fa-chart-pie', $di_halaman === 'admin/kpi'],
    ['admin/kpi/ranking', 'Ranking', 'fa-trophy', str_starts_with($di_halaman, 'admin/kpi/ranking')],
];

if ($levelSesi === 'superadmin') {
    $kpiSubmenu[] = ['admin/kpi/target', 'Target & Bobot', 'fa-bullseye', str_starts_with($di_halaman, 'admin/kpi/target')];
}

// Submenu CRM
$crmSubmenu = [
    ['admin/crm', 'Ringkasan', 'fa-chart-pie', $di_halaman === 'admin/crm'],
    ['admin/crm/pelanggan', 'Data Pelanggan', 'fa-users', str_starts_with($di_halaman, 'admin/crm/pelanggan')],
    ['admin/crm/followup', 'Follow-up', 'fa-tasks', str_starts_with($di_halaman, 'admin/crm/followup')],
    ['admin/crm/tagihan', 'Tagihan', 'fa-file-invoice-dollar', str_starts_with($di_halaman, 'admin/crm/tagihan')],
    ['admin/crm/chat', 'Live Chat WA', 'fa-comments', str_starts_with($di_halaman, 'admin/crm/chat')],
    ['admin/crm/broadcast', 'Broadcast', 'fa-bullhorn', str_starts_with($di_halaman, 'admin/crm/broadcast')],
    ['admin/crm/duplikat', 'Akun Duplikat', 'fa-clone', str_starts_with($di_halaman, 'admin/crm/duplikat')],
    ['admin/crm/log', 'Riwayat Pesan', 'fa-history', str_starts_with($di_halaman, 'admin/crm/log')],
    ['admin/crm/template', 'Template Pesan', 'fa-file-alt', str_starts_with($di_halaman, 'admin/crm/template')],
    ['admin/crm/pengaturan', 'Pengaturan WA', 'fa-sliders-h', str_starts_with($di_halaman, 'admin/crm/pengaturan')],
];

// Submenu Laporan
// 'produk' adalah awalan dari 'produksi', jadi pencocokan harus per segmen penuh
// supaya hanya satu entri yang ikut menyala.
$laporan_aktif = static function (string $segmen) use ($di_halaman): bool {
    return $di_halaman === 'admin/laporan/' . $segmen
        || str_starts_with($di_halaman, 'admin/laporan/' . $segmen . '/');
};
$laporanSubmenu = [
    ['admin/laporan/pesanan', 'Laporan Pesanan', 'fa-file-alt', $laporan_aktif('pesanan') || $di_halaman === 'admin/laporan'],
    ['admin/laporan/pendapatan', 'Laporan Pendapatan', 'fa-wallet', $laporan_aktif('pendapatan')],
    ['admin/laporan/pembayaran', 'Laporan Pembayaran', 'fa-money-check-edit', $laporan_aktif('pembayaran')],
    ['admin/laporan/piutang', 'Laporan Piutang', 'fa-inbox-in', $laporan_aktif('piutang')],
    ['admin/laporan/produksi', 'Laporan Produksi', 'fa-tasks', $laporan_aktif('produksi')],
    ['admin/laporan/produk', 'Laporan Produk', 'fa-box-alt', $laporan_aktif('produk')],
    ['admin/laporan/pelanggan', 'Laporan Customer', 'fa-user', $laporan_aktif('pelanggan')],
];

// Submenu Sistem. Kebersihan kotak notifikasi boleh dipakai Admin; rekening bank,
// juragan, pengguna, dan kunci API tetap khusus Superadmin.
$sistemSubmenu = [];

if (in_array($levelSesi, ['admin', 'superadmin'], true)) {
    $sistemSubmenu[] = [
        'admin/settings/notifikasi',
        'Pengaturan Notifikasi',
        'fa-bell-slash',
        str_starts_with($di_halaman, 'admin/settings/notifikasi'),
    ];
}

if ($levelSesi === 'superadmin') {
    $sistemSubmenu = array_merge($sistemSubmenu, [
        ['admin/settings/bank', 'Atur Bank', 'fa-university', str_starts_with($di_halaman, 'admin/settings/bank')],
        ['admin/settings/juragan', 'Atur Juragan', 'fa-store', str_starts_with($di_halaman, 'admin/settings/juragan')],
        ['admin/settings/pengguna', 'Atur Pengguna', 'fa-users-cog', str_starts_with($di_halaman, 'admin/settings/pengguna')],
        ['admin/settings/kunci-api', 'Kunci API', 'fa-key', str_starts_with($di_halaman, 'admin/settings/kunci-api')],
    ]);
}
?>

<!-- Topbar khusus layar kecil; di desktop lonceng notifikasi melayang sendiri
     (lihat .notif-melayang di template/notif_bar.php), bukan bar penuh. -->
<header class="mobile-topbar d-flex d-lg-none align-items-center justify-content-between px-2 sticky-top">
    <div class="d-flex align-items-center gap-2">
        <button class="btn btn-sm btn-link mobile-topbar-btn p-1 text-decoration-none" id="mobileSidebarToggle" type="button" aria-label="Buka Menu">
            <i class="fal fa-bars fs-5"></i>
        </button>
        <a href="<?= site_url('') ?>" class="d-flex align-items-center gap-2 mobile-topbar-brand text-decoration-none">
            <div class="sidebar-brand-icon" style="width:28px;height:28px;"><?= $this->include('template/merek') ?>
            </div>
            <span class="fw-bold small sidebar-brand-text-label">Djuragan POS</span>
        </a>
    </div>
    <div class="d-flex align-items-center gap-2">
        <a class="mobile-topbar-btn text-decoration-none position-relative p-2" data-notif-toggle="1" href="#notifikasi" role="button" aria-label="Notifikasi" title="Notifikasi">
            <i class="fal fa-bell fs-5"></i>
            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger counter"></span>
        </a>
        <div class="dropdown">
            <button class="btn btn-sm btn-link mobile-topbar-btn p-0 text-decoration-none" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                <div class="sidebar-user-avatar" style="width:28px;height:28px;font-size:0.75rem;">
                    <?= esc($namaInisial) ?>
                </div>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow border-0 mt-2">
                <li class="dropdown-header text-truncate" style="max-width: 200px;">
                    <strong><?= esc($namaSesi) ?></strong>
                    <div class="small text-muted text-uppercase"><?= esc($levelSesi) ?></div>
                </li>
                <li><hr class="dropdown-divider my-1"></li>
                <li><a class="dropdown-item py-2" href="<?= site_url('user/sunting') ?>"><i class="fal fa-user-edit me-2"></i> Ubah Profil</a></li>
                <li><a class="dropdown-item py-2 text-danger" href="<?= site_url('auth/keluar') ?>"><i class="fal fa-sign-out-alt me-2"></i> Keluar</a></li>
            </ul>
        </div>
    </div>
</header>

<!-- Backdrop overlay untuk mobile -->
<div class="sidebar-backdrop" id="sidebarBackdrop"></div>

<!-- Sidebar Utama Admin -->
<aside class="app-sidebar" id="appSidebar">
    <!-- Header Brand -->
    <div class="sidebar-header">
        <a href="<?= site_url('') ?>" class="sidebar-brand">
            <div class="sidebar-brand-icon"><?= $this->include('template/merek') ?>
            </div>
            <div class="sidebar-brand-text">
                <span>Djuragan POS</span>
            </div>
        </a>
        <div class="d-flex align-items-center">
            <!-- Tombol sembunyikan desktop -->
            <button class="btn btn-sm sidebar-close-btn sidebar-collapse-btn p-1" id="desktopSidebarCollapse" type="button" title="Sembunyikan Sidebar">
                <i class="fal fa-bars fs-6"></i>
            </button>
            <!-- Tombol tutup mobile -->
            <button class="btn btn-sm sidebar-close-btn p-1 d-lg-none" id="mobileSidebarClose" type="button" aria-label="Tutup Menu">
                <i class="fal fa-times fs-5"></i>
            </button>
        </div>
    </div>

    <!-- Menu Gulir -->
    <div class="sidebar-menu">
        <div class="sidebar-heading">Menu Utama</div>
        <ul class="sidebar-nav">
            <li class="nav-item">
                <a class="sidebar-link <?= $isDasborAktif ? 'active' : '' ?>" href="<?= site_url('admin/dasbor') ?>">
                    <i class="sidebar-link-icon fal fa-home"></i>
                    <span>Dasbor</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="sidebar-link <?= $isTrxAktif ? 'active' : '' ?>" data-bs-toggle="collapse" href="#submenuTransaksi" role="button" aria-expanded="<?= $isTrxAktif ? 'true' : 'false' ?>" aria-controls="submenuTransaksi">
                    <i class="sidebar-link-icon fal fa-receipt"></i>
                    <span>Transaksi</span>
                    <i class="fal fa-chevron-down sidebar-chevron"></i>
                </a>
                <div class="collapse <?= $isTrxAktif ? 'show' : '' ?>" id="submenuTransaksi">
                    <ul class="sidebar-submenu">
                        <?php foreach ($transaksiSubmenu as [$url, $label, $ikon, $aktif]) : ?>
                            <li class="submenu-item">
                                <a class="sidebar-submenu-link <?= $aktif ? 'active' : '' ?>" href="<?= site_url($url) ?>">
                                    <i class="fal <?= $ikon ?>"></i>
                                    <span><?= esc($label) ?></span>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </li>
            <li class="nav-item">
                <a class="sidebar-link <?= $isProdukAktif ? 'active' : '' ?>" data-bs-toggle="collapse" href="#submenuProduk" role="button" aria-expanded="<?= $isProdukAktif ? 'true' : 'false' ?>" aria-controls="submenuProduk">
                    <i class="sidebar-link-icon fal fa-tshirt"></i>
                    <span>Produk</span>
                    <i class="fal fa-chevron-down sidebar-chevron"></i>
                </a>
                <div class="collapse <?= $isProdukAktif ? 'show' : '' ?>" id="submenuProduk">
                    <ul class="sidebar-submenu">
                        <?php foreach ($produkSubmenu as [$url, $label, $ikon, $aktif]) : ?>
                            <li class="submenu-item">
                                <a class="sidebar-submenu-link <?= $aktif ? 'active' : '' ?>" href="<?= site_url($url) ?>">
                                    <i class="fal <?= $ikon ?>"></i>
                                    <span><?= esc($label) ?></span>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </li>
        </ul>

        <?php if (in_array($levelSesi, ['admin', 'superadmin'], true)) : ?>
            <div class="sidebar-heading mt-2">Hubungan Pelanggan</div>
            <ul class="sidebar-nav">
                <!-- Submenu CRM -->
                <li class="nav-item">
                    <a class="sidebar-link <?= $isCrmAktif ? 'active' : '' ?>" data-bs-toggle="collapse" href="#submenuCrm" role="button" aria-expanded="<?= $isCrmAktif ? 'true' : 'false' ?>" aria-controls="submenuCrm">
                        <i class="sidebar-link-icon fal fa-comments-alt"></i>
                        <span>CRM</span>
                        <i class="fal fa-chevron-down sidebar-chevron"></i>
                    </a>
                    <div class="collapse <?= $isCrmAktif ? 'show' : '' ?>" id="submenuCrm">
                        <ul class="sidebar-submenu">
                            <?php foreach ($crmSubmenu as [$url, $label, $ikon, $aktif]) : ?>
                                <li class="submenu-item">
                                    <a class="sidebar-submenu-link <?= $aktif ? 'active' : '' ?>" href="<?= site_url($url) ?>">
                                        <i class="fal <?= $ikon ?>"></i>
                                        <span><?= esc($label) ?></span>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </li>
            </ul>

            <div class="sidebar-heading mt-2">Kinerja & KPI</div>
            <ul class="sidebar-nav">
                <!-- Submenu KPI -->
                <li class="nav-item">
                    <a class="sidebar-link <?= $isKpiAktif ? 'active' : '' ?>" data-bs-toggle="collapse" href="#submenuKpi" role="button" aria-expanded="<?= $isKpiAktif ? 'true' : 'false' ?>" aria-controls="submenuKpi">
                        <i class="sidebar-link-icon fal fa-bullseye"></i>
                        <span>KPI</span>
                        <i class="fal fa-chevron-down sidebar-chevron"></i>
                    </a>
                    <div class="collapse <?= $isKpiAktif ? 'show' : '' ?>" id="submenuKpi">
                        <ul class="sidebar-submenu">
                            <?php foreach ($kpiSubmenu as [$url, $label, $ikon, $aktif]) : ?>
                                <li class="submenu-item">
                                    <a class="sidebar-submenu-link <?= $aktif ? 'active' : '' ?>" href="<?= site_url($url) ?>">
                                        <i class="fal <?= $ikon ?>"></i>
                                        <span><?= esc($label) ?></span>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </li>
            </ul>
        <?php endif; ?>

        <div class="sidebar-heading mt-2">Laporan & Analisis</div>
        <ul class="sidebar-nav">
            <!-- Submenu Laporan -->
            <li class="nav-item">
                <a class="sidebar-link <?= $isLaporanAktif ? 'active' : '' ?>" data-bs-toggle="collapse" href="#submenuLaporan" role="button" aria-expanded="<?= $isLaporanAktif ? 'true' : 'false' ?>" aria-controls="submenuLaporan">
                    <i class="sidebar-link-icon fal fa-file-alt"></i>
                    <span>Laporan</span>
                    <i class="fal fa-chevron-down sidebar-chevron"></i>
                </a>
                <div class="collapse <?= $isLaporanAktif ? 'show' : '' ?>" id="submenuLaporan">
                    <ul class="sidebar-submenu">
                        <?php foreach ($laporanSubmenu as [$url, $label, $ikon, $aktif]) : ?>
                            <li class="submenu-item">
                                <a class="sidebar-submenu-link <?= $aktif ? 'active' : '' ?>" href="<?= site_url($url) ?>">
                                    <i class="fal <?= $ikon ?>"></i>
                                    <span><?= esc($label) ?></span>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </li>
        </ul>

        <?php if ($sistemSubmenu !== [] || $levelSesi === 'superadmin') : ?>
            <div class="sidebar-heading mt-2">Sistem</div>
            <ul class="sidebar-nav">
                <!-- Submenu Pengaturan -->
                <li class="nav-item">
                    <a class="sidebar-link <?= $isSettingsAktif ? 'active' : '' ?>" data-bs-toggle="collapse" href="#submenuSettings" role="button" aria-expanded="<?= $isSettingsAktif ? 'true' : 'false' ?>" aria-controls="submenuSettings">
                        <i class="sidebar-link-icon fal fa-cogs"></i>
                        <span>Pengaturan</span>
                        <i class="fal fa-chevron-down sidebar-chevron"></i>
                    </a>
                    <div class="collapse <?= $isSettingsAktif ? 'show' : '' ?>" id="submenuSettings">
                        <ul class="sidebar-submenu">
                            <?php foreach ($sistemSubmenu as [$url, $label, $ikon, $aktif]) : ?>
                                <li class="submenu-item">
                                    <a class="sidebar-submenu-link <?= $aktif ? 'active' : '' ?>" href="<?= site_url($url) ?>">
                                        <i class="fal <?= $ikon ?>"></i>
                                        <span><?= esc($label) ?></span>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </li>
                <?php if ($levelSesi === 'superadmin') : ?>
                    <!-- kotak masukan bukan bagian dari pengaturan: menunya berdiri
                         sendiri karena yang membacanya hanya Superadmin, dan duduk
                         paling bawah supaya tidak menumpuk di atas pengaturan -->
                    <li class="nav-item">
                        <a class="sidebar-link <?= $isMasukanAktif ? 'active' : '' ?>" href="<?= site_url('admin/masukan') ?>">
                            <i class="sidebar-link-icon fal fa-headset"></i>
                            <span>Kotak Masukan</span>
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        <?php endif; ?>
    </div>

    <!-- Footer Sidebar: Profil -->
    <div class="sidebar-footer">
        <div class="sidebar-user-card">
            <div class="sidebar-user-avatar">
                <?= esc($namaInisial) ?>
            </div>
            <div class="sidebar-user-info">
                <div class="sidebar-user-name" title="<?= esc($namaSesi) ?>"><?= esc($namaSesi) ?></div>
                <div class="sidebar-user-role"><?= esc($levelSesi ?: 'Admin') ?></div>
            </div>
            <div class="sidebar-user-actions">
                <a class="sidebar-action-btn" href="<?= site_url('user/sunting') ?>" title="Ubah Profil">
                    <i class="fal fa-user-edit"></i>
                </a>
                <a class="sidebar-action-btn btn-logout" href="<?= site_url('auth/keluar') ?>" title="Keluar">
                    <i class="fal fa-sign-out-alt"></i>
                </a>
            </div>
        </div>
    </div>
</aside>

<?= $this->include('template/notif_bar') ?>

<script>
    (function() {
        document.body.classList.add('has-app-sidebar');
        if (localStorage.getItem('djuragan_sidebar_collapsed') === '1') {
            document.body.classList.add('sidebar-collapsed');
        }

        // Mobile sidebar toggle
        var mobToggle = document.getElementById('mobileSidebarToggle');
        var mobClose  = document.getElementById('mobileSidebarClose');
        var backdrop  = document.getElementById('sidebarBackdrop');
        var sidebar   = document.getElementById('appSidebar');

        function openSidebar() {
            if (sidebar) sidebar.classList.add('show');
            if (backdrop) backdrop.classList.add('show');
            document.body.style.overflow = 'hidden';
        }

        function closeSidebar() {
            if (sidebar) sidebar.classList.remove('show');
            if (backdrop) backdrop.classList.remove('show');
            document.body.style.overflow = '';
        }

        if (mobToggle) mobToggle.addEventListener('click', openSidebar);
        if (mobClose) mobClose.addEventListener('click', closeSidebar);
        if (backdrop) backdrop.addEventListener('click', closeSidebar);

        // Desktop collapse toggle — rail ikon tetap terlihat dan melebar sendiri
        // saat disentuh kursor (lihat .sidebar-collapsed di style.css).
        var deskCollapse = document.getElementById('desktopSidebarCollapse');

        if (deskCollapse) {
            deskCollapse.addEventListener('click', function() {
                document.body.classList.toggle('sidebar-collapsed');
                localStorage.setItem('djuragan_sidebar_collapsed',
                    document.body.classList.contains('sidebar-collapsed') ? '1' : '0');
            });
        }
    })();
</script>
