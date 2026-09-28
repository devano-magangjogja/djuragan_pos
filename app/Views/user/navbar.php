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
            <?= anchor('', 'Pesanan Juragan', ['class' => 'navbar-brand ms-3']) ?>
        </div>
        <div id="menu" class="order-3 order-md-0 navbar-nav-scroll d-flex justify-content-center">
            <ul class="navbar-nav bd-navbar-nav flex-row py-2 py-md-0">
                <li class="nav-item">
                    <?= anchor('user/invoices/tulis', 'Tulis Orderan', ['class' => 'nav-link']) ?>
                </li>
                <li class="nav-item">
                    <?= anchor('user/chart', 'Chart', ['class' => 'nav-link']) ?>
                </li>
            </ul>
        </div>
        <div id="topmenu" class="ms-sm-auto">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <a class="nav-link" id="notif" data-bs-toggle="offcanvas" href="#notifikasi" role="button" aria-controls="notifikasi">
                        <span class="d-none d-md-inline-block">Notifikasi</span> <span class="badge rounded-pill bg-danger counter"></span>
                    </a>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link" href="#" id="dropUser" role="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        <i class="fal fa-user d-block d-md-none"></i> <span class="d-none d-md-inline-block">
                            <?= esc($_SESSION['name']) ?></span>
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
