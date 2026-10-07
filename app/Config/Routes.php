<?php

namespace Config;

// Create a new instance of our RouteCollection class.
$routes = Services::routes();

// Load the system's routing file first, so that the app and ENVIRONMENT
// can override as needed.
if (is_file(SYSTEMPATH . 'Config/Routes.php')) {
    require SYSTEMPATH . 'Config/Routes.php';
}

/*
 * --------------------------------------------------------------------
 * Router Setup
 * --------------------------------------------------------------------
 */
$routes->setDefaultNamespace('App\Controllers');
$routes->setDefaultController('Auth');
$routes->setDefaultMethod('index');
$routes->setTranslateURIDashes(false);
// pesan 404 sengaja tidak diteruskan ke sini: override selalu menerima pesan apa adanya
// (CodeIgniter::display404errors memanggil $override($e->getMessage())), termasuk teks
// internal router seperti "Can't find a route for ...", yang tidak layak dibaca tamu
$routes->set404Override(static fn () => view('errors/html/error_page'));
// The Auto Routing (Legacy) is very dangerous. It is easy to create vulnerable apps
// where controller filters or CSRF protection are bypassed.
// If you don't want to define all routes, please use the Auto Routing (Improved).
// Set `$autoRoutesImproved` to true in `app/Config/Feature.php` and set the following to true.
//$routes->setAutoRoute(false);

/*
 * --------------------------------------------------------------------
 * Route Definitions
 * --------------------------------------------------------------------
 */
$routes->addPlaceholder('juragan', '[a-z0-9]{40}|semua');
$routes->addPlaceholder('tab', 'semua|pembayaran|cek-bayar|dalam-proses|belum-proses|selesai|saring');

// We get a performance increase by specifying the default
// route since we don't have to scan directories.
$routes->group('auth', ['filter' => 'auth'], static function ($routes) {
    $routes->match(['get', 'post'], 'index', 'Auth::index', ['as' => 'hal.index']);
    $routes->match(['get', 'post'], 'daftar', 'Auth::daftar');
    $routes->match(['get', 'post'], 'lupa', 'Auth::lupa');
    $routes->addRedirect('/', 'hal.index');
    $routes->get('reset', 'Auth::reset');
    $routes->post('simpan-sandi-baru', 'Auth::simpanSandiBaru');
});

$routes->get('auth/keluar', 'Auth::keluar', ['filter' => 'auth:admin,superadmin,user']);

// profil milik pengguna yang sedang login, bisa diakses semua role aktif
$routes->group('user', ['filter' => 'auth:admin,superadmin,user'], static function ($routes) {
    $routes->get('sunting', 'Profil::sunting');
    $routes->post('sunting', 'Profil::simpan');
});

// admin
$routes->group('admin', static function ($routes) {
    $routes->group('invoices', ['filter' => 'auth:admin,superadmin'], static function ($routes) {
        $routes->get('/', 'Admin\Invoices::lihat', ['as' => 'hal.admin']);
        $routes->get('tulis', 'Admin\Invoices::tulis');
        $routes->get('sunting/(:any)', 'Admin\Invoices::sunting/$1');
        $routes->get('lihat/(:juragan)', 'Admin\Invoices::lihat/$1');
        $routes->get('lihat/(:juragan)/(:tab)', 'Admin\Invoices::lihat/$1/$2');
        $routes->get('lihat/(:juragan)/(:tab)/(:segment)', 'Admin\Invoices::lihat/$1/$2/$3');
        $routes->get('info_pembayaran', 'Admin\Invoices::info_pembayaran');
        $routes->get('detail_status/(:any)', 'Admin\Invoices::detail_status/$1');
        $routes->post('save', 'Admin\Invoices::save');
        $routes->post('update', 'Admin\Invoices::update');
        $routes->post('simpan_pembayaran', 'Admin\Invoices::simpan_pembayaran');
        $routes->post('save_progress', 'Admin\Invoices::save_progress');
        $routes->post('update_bayar', 'Admin\Invoices::update_bayar');
        $routes->post('hapus_orderan', 'Admin\Invoices::hapus_orderan');
    });

    // dasbor: ringkasan transaksi, pembayaran, dan stok
    $routes->get('dasbor', 'Admin\Dasbor::index', ['filter' => 'auth:admin,superadmin', 'as' => 'hal.dasbor']);

    // pantau stok & tambah barang
    $routes->group('produk', ['filter' => 'auth:admin,superadmin'], static function ($routes) {
        $routes->get('/', 'Admin\Produk::index');
        $routes->post('save', 'Admin\Produk::simpan');
        $routes->post('update', 'Admin\Produk::perbarui');
        $routes->post('hapus', 'Admin\Produk::hapus');
    });

    // laporan per jenis: pesanan, pendapatan, pembayaran, piutang, produksi, produk, pelanggan
    $routes->group('laporan', ['filter' => 'auth:admin,superadmin'], static function ($routes) {
        $routes->get('/', 'Admin\Laporan::index', ['as' => 'hal.laporan']);
        $routes->get('(:segment)', 'Admin\Laporan::index/$1');
    });

    $routes->group('settings', ['filter' => 'auth:superadmin'], static function ($routes) {
        $routes->get('/', 'Admin\Settings::index');

        // form "Tambah Juragan" memposting ke alamat ini sejak lama, rutenya yang belum dipasang
        $routes->post('save_juragan', 'Admin\Settings\Juragan::save_juragan');

        $routes->group('bank', static function ($routes) {
            $routes->get('/', 'Admin\Settings\Bank::index');
            $routes->post('save', 'Admin\Settings\Bank::simpan');
            $routes->post('update', 'Admin\Settings\Bank::perbarui');
        });

        $routes->group('juragan', static function ($routes) {
            $routes->get('/', 'Admin\Settings\Juragan::index');
            $routes->post('update', 'Admin\Settings\Juragan::update');
            // satu toko dipegang satu Admin dan satu CS
            $routes->post('pengelola', 'Admin\Settings\Juragan::pengelola');
        });

        $routes->get('pengguna', 'Admin\Settings\Pengguna::pengguna');
        $routes->post('save_pengguna', 'Admin\Settings\Pengguna::save_pengguna');
        $routes->post('update_pengguna', 'Admin\Settings\Pengguna::update_pengguna');
    });

    // halaman depan admin = dasbor, daftar invoice sekarang lewat menu Transaksi
    $routes->addRedirect('/', 'hal.dasbor');
});

// user
$routes->group('user', ['filter' => 'auth:user'], static function ($routes) {
    $routes->get('chart', 'User\Chart::index');
    $routes->group('invoices', static function ($routes) {
        $routes->get('/', 'User\Invoices::lihat', ['as' => 'hal.user']);
        $routes->get('tulis', 'User\Invoices::tulis');
        $routes->get('lihat/([a-z0-9]{40})', 'User\Invoices::lihat/$1');
        $routes->get('lihat/([a-z0-9]{40})/(:tab)', 'User\Invoices::lihat/$1/$2');
        $routes->get('lihat/([a-z0-9]{40})/(:tab)/(:segment)', 'User\Invoices::lihat/$1/$2/$3');
        $routes->get('info_pembayaran', 'User\Invoices::info_pembayaran');
        $routes->post('simpan_pembayaran', 'User\Invoices::simpan_pembayaran');
        $routes->post('save', 'User\Invoices::save');
    });
    $routes->addRedirect('/', 'hal.user');
});

// seluruh endpoint API hanya dipanggil jQuery dari halaman yang sudah login,
// jadi tamu tidak boleh masuk; filter grup bersarang digabung, bukan ditimpa
$routes->group('api', ['filter' => 'auth:admin,superadmin,user'], static function ($routes) {
    $routes->get('invoice/counter_tab/(:num)', 'Api\Invoice::counter_tab/$1');

    $routes->group('juragan', static function ($routes) {
        $routes->add('(:segment)', 'Api\Juragan::$1');
    });

    $routes->group('notifikasi', static function ($routes) {
        $routes->add('(:segment)', 'Api\Notifikasi::$1');
    });

    // daftar akun + surel + login_terakhir hanya dipakai halaman
    // Pengaturan > Pengguna, yang memang khusus superadmin
    $routes->group('pengguna', ['filter' => 'auth:superadmin'], static function ($routes) {
        $routes->add('(:segment)', 'Api\Pengguna::$1');
    });

    $routes->group('pengiriman', static function ($routes) {
        $routes->add('(:segment)', 'Api\Pengiriman::$1');
        $routes->post('photos', 'Api\Pengiriman::create');
    });
});

$routes->addRedirect('/', 'hal.index');

// tiga lookup RajaOngkir memakai kunci berbayar dan hanya dipakai form
// tulis/sunting orderan (admin & CS), jadi tidak boleh dipanggil tamu
$routes->get('rajaongkir/kecamatan', 'Rajaongkir::kecamatan', ['filter' => 'auth:admin,superadmin,user']);
$routes->get('rajaongkir/kota', 'Rajaongkir::kota', ['filter' => 'auth:admin,superadmin,user']);
$routes->get('rajaongkir/provinsi', 'Rajaongkir::provinsi', ['filter' => 'auth:admin,superadmin,user']);
$routes->get('download/invoice/(:any)', 'Download::invoice/$1', ['filter' => 'auth:admin,superadmin,user']);
// satu nota yang sama dicetak jadi dua dokumen: invoice untuk pelanggan (memuat uang),
// lembar kerja untuk penjahit (hanya spesifikasi jahit, tanpa harga)
$routes->get('download/penjahit/(:any)', 'Download::penjahit/$1', ['filter' => 'auth:admin,superadmin,user']);
$routes->get('pelanggan/cari', 'Pelanggan::cari', ['filter' => 'auth:admin,superadmin,user']);
$routes->get('pelanggan/data/(:num)', 'Pelanggan::data/$1', ['filter' => 'auth:admin,superadmin,user']);
$routes->post('pelanggan/baru', 'Pelanggan::baru', ['filter' => 'auth:admin,superadmin,user']);

$routes->post('kirim-masukan', 'Masukan::kirim', ['filter' => 'auth:admin,superadmin,user']);

/*
 * --------------------------------------------------------------------
 * Additional Routing
 * --------------------------------------------------------------------
 *
 * There will often be times that you need additional routing and you
 * need it to be able to override any defaults in this file. Environment
 * based routes is one such time. require() additional route files here
 * to make that happen.
 *
 * You will have access to the $routes object within that file without
 * needing to reload it.
 */
if (is_file(APPPATH . 'Config/' . ENVIRONMENT . '/Routes.php')) {
    require APPPATH . 'Config/' . ENVIRONMENT . '/Routes.php';
}
