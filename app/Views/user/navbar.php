<?= $this->include('template/feedback') ?>
<?php
$di_halaman  = trim(uri_string(), '/');
$levelSesi   = session()->get('level') ?? ($_SESSION['level'] ?? 'CS');
$namaSesi    = session()->get('name') ?? ($_SESSION['name'] ?? 'Pengguna');
$namaInisial = strtoupper(mb_substr($namaSesi, 0, 1));

$isTrxAktif   = url_is('user/invoices*');
$isChartAktif = url_is('user/chart*');
?>

<!-- Mobile Topbar (< 992px) -->
<header class="mobile-topbar d-flex d-lg-none align-items-center justify-content-between px-3 sticky-top">
    <div class="d-flex align-items-center gap-2">
        <button class="btn btn-sm btn-link mobile-topbar-btn p-1 text-decoration-none" id="mobileSidebarToggle" type="button" aria-label="Buka Menu">
            <i class="fal fa-bars fs-5"></i>
        </button>
        <a href="<?= site_url('') ?>" class="d-flex align-items-center gap-2 mobile-topbar-brand text-decoration-none">
            <div class="sidebar-brand-icon" style="width:28px;height:28px;font-size:0.85rem;">
                <i class="fal fa-layer-group"></i>
            </div>
            <span class="fw-bold small sidebar-brand-text-label">Pesanan Juragan</span>
        </a>
    </div>
    <div class="d-flex align-items-center gap-2">
        <a class="mobile-topbar-btn text-decoration-none position-relative p-2" data-bs-toggle="offcanvas" href="#notifikasi" role="button" aria-controls="notifikasi" title="Notifikasi">
            <i class="fal fa-bell fs-5"></i>
            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger counter" style="font-size: 0.65rem;"></span>
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

<!-- Sidebar Utama CS / User -->
<aside class="app-sidebar" id="appSidebar">
    <!-- Header Brand -->
    <div class="sidebar-header">
        <a href="<?= site_url('') ?>" class="sidebar-brand">
            <div class="sidebar-brand-icon">
                <i class="fal fa-layer-group"></i>
            </div>
            <div class="sidebar-brand-text">
                <span>Pesanan Juragan</span>
                <span class="role-badge"><?= esc(strtoupper($levelSesi ?: 'CS')) ?></span>
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
                <a class="sidebar-link <?= $isTrxAktif ? 'active' : '' ?>" href="<?= site_url('user/invoices') ?>">
                    <i class="sidebar-link-icon fal fa-receipt"></i>
                    <span>Transaksi</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="sidebar-link <?= $isChartAktif ? 'active' : '' ?>" href="<?= site_url('user/chart') ?>">
                    <i class="sidebar-link-icon fal fa-chart-line"></i>
                    <span>Chart</span>
                </a>
            </li>
        </ul>
    </div>

    <!-- Footer Sidebar: Notifikasi & Profil -->
    <div class="sidebar-footer">
        <a class="sidebar-footer-link" id="notif" data-bs-toggle="offcanvas" href="#notifikasi" role="button" aria-controls="notifikasi">
            <i class="fal fa-bell"></i>
            <span>Notifikasi</span>
            <span class="badge rounded-pill bg-danger counter ms-auto"></span>
        </a>

        <div class="sidebar-user-card">
            <div class="sidebar-user-avatar">
                <?= esc($namaInisial) ?>
            </div>
            <div class="sidebar-user-info">
                <div class="sidebar-user-name" title="<?= esc($namaSesi) ?>"><?= esc($namaSesi) ?></div>
                <div class="sidebar-user-role"><?= esc($levelSesi ?: 'CS') ?></div>
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
