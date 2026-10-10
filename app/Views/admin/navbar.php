<?= $this->include('template/feedback') ?>
<nav class="sticky-top mainnav navbar navbar-expand navbar-dark bg-dark bg-gradient">
    <div class="container-xxl d-flex flex-wrap flex-md-nowrap align-items-center justify-content-between">
        <div class="d-flex align-items-center me-3" id="logo">
            <?= form_button([
                'class'          => 'btn-juragan btn btn-outline-light',
                'content'        => '<i class="fal fa-align-left fa-flip-vertical"></i>',
                'data-bs-target' => '#juragan',
                'data-bs-toggle' => 'offcanvas',
            ]) ?>
            <?= anchor('', 'Djuragan POS', ['class' => 'navbar-brand ms-3']) ?>
        </div>
        <div id="menu" class="order-3 order-md-0 navbar-nav-scroll d-flex justify-content-center">
            <ul class="navbar-nav bd-navbar-nav flex-row py-2 py-md-0">
                <?php
                $di_halaman = trim(uri_string(), '/');

                // menu utama; yang sedang dibuka ditandai .active
                // Tulis Orderan tidak lagi di sini, tombolnya ada di halaman Transaksi
                $levelSesi = $_SESSION['level'] ?? '';
                $menu = [
                    ['admin/dasbor', 'Dasbor', 'fa-home', url_is('admin/dasbor*') || $di_halaman === 'admin'],
                    ['admin/invoices/lihat/semua/semua', 'Transaksi', 'fa-receipt', url_is('admin/invoices*')],
                    ['admin/produk', 'Produk', 'fa-tshirt', url_is('admin/produk*')],
                    ['admin/laporan', 'Laporan', 'fa-file-alt', url_is('admin/laporan*')],
                ];
                // Menu CRM dan KPI hanya untuk admin dan superadmin.
                // KPI berdiri sendiri di menu, tidak menumpang di CRM atau Laporan.
                if (in_array($levelSesi, ['admin', 'superadmin'], true)) {
                    array_splice($menu, 3, 0, [
                        ['admin/crm', 'CRM', 'fa-comments-alt', url_is('admin/crm*')],
                        ['admin/kpi', 'KPI', 'fa-bullseye', url_is('admin/kpi*')],
                    ]);
                }

                foreach ($menu as [$alamat, $label, $ikon, $aktif]) {
                    echo '<li class="nav-item"><a class="nav-link' . ($aktif ? ' active' : '') . '" href="' . site_url($alamat) . '">'
                        . '<i class="fal ' . $ikon . ' d-block d-md-none"></i>'
                        . '<span class="d-none d-md-inline-block">' . $label . '</span></a></li>';
                }
                ?>
            </ul>
        </div>
        <div id="topmenu" class="ms-sm-auto">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <a class="nav-link" id="notif" data-bs-toggle="offcanvas" href="#notifikasi" role="button" aria-controls="notifikasi">
                        <span class="d-none d-md-inline-block">Notifikasi</span> <span class="badge rounded-pill bg-danger counter"></span>
                    </a>
                </li>
                <?php if (($_SESSION['level'] ?? '') === 'superadmin') : ?>
                    <li class="nav-item">
                        <?= anchor('admin/settings', '<i class="fal fa-cogs d-block d-md-none"></i><span class="d-none d-md-inline-block">Pengaturan</span>', ['class' => 'nav-link']) ?>
                    </li>
                <?php endif; ?>
                <li class="nav-item dropdown">
                    <a class="nav-link" href="#" id="dropUser" role="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        <i class="fal fa-user d-block d-md-none"></i>
                        <span class="d-none d-md-inline-block">
                            <?= esc($_SESSION['name']) ?>
                        </span>
                    </a>
                    <div class="dropdown-menu dropdown-menu-end" aria-labelledby="dropUser">
                        <?= anchor('user/sunting', 'Ubah Profil', ['class' => 'dropdown-item']) ?>
                        <?= anchor('auth/keluar', 'Keluar', ['class' => 'dropdown-item']) ?>
                    </div>
                </li>
            </ul>
        </div>
    </div>
</nav>

<?= $this->include('template/juragan_bar') ?>
<?= $this->include('template/notif_bar') ?>
