<?php

$nama_hari = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
$nama_bulan = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli',
    'Agustus', 'September', 'Oktober', 'November', 'Desember'];
$bulan_pendek = [1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Ags', 'Sep', 'Okt', 'Nov', 'Des'];

$tanggal_hari_ini = $nama_hari[(int) date('w')] . ', ' . date('j') . ' '
    . $nama_bulan[(int) date('n')] . ' ' . date('Y');

// label bulan "2026-09" jadi "Sep 2026"
$label_bulan = static function (string $bulan) use ($bulan_pendek): string {
    return $bulan_pendek[(int) substr($bulan, 5, 2)] . ' ' . substr($bulan, 0, 4);
};

// perubahan dibanding bulan lalu, null kalau bulan lalu belum ada orderan
$persen = static function (int $sekarang, int $sebelum): ?int {
    return $sebelum > 0 ? (int) round(($sekarang - $sebelum) / $sebelum * 100) : null;
};

$pct_order = $persen($bulan[$bulan_ini]['orderan'], $bulan[$bulan_lalu]['orderan']);
$pct_total = $persen($bulan[$bulan_ini]['total'], $bulan[$bulan_lalu]['total']);

$tautan_transaksi = static function (string $slug, string $seri): string {
    return site_url('admin/invoices/lihat/' . $slug . '/semua?cari[kolom]=faktur&cari[q]=' . urlencode($seri));
};
?>
<?= $this->extend('template/main') ?>

<?= $this->section('content') ?>
<?= $this->include('admin/navbar') ?>

<div class="container-xxl">
    <h1 class="h3 mt-3">Dasbor</h1>
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb p-0">
            <li class="breadcrumb-item active" aria-current="page"><?= esc($tanggal_hari_ini) ?></li>
        </ol>
    </nav>

    <div class="row gx-2 gy-3 mb-4">
        <?php
        $kartu = [
            [
                'label' => 'Order hari ini',
                'nilai' => (string) $hari['orderan'],
                'ket'   => 'Rp ' . formatRibuan($hari['total']) . ' · ' . $hari['pelanggan'] . ' pelanggan',
                'ikon'  => 'fa-calendar-check',
                'tautan' => 'admin/invoices/lihat/semua/semua',
            ],
            [
                'label'   => 'Order bulan ini',
                'nilai'   => (string) $bulan[$bulan_ini]['orderan'] ?? 0,
                'ket'     => $pct_order === null
                    ? 'Belum ada pembanding bulan lalu'
                    : ($pct_order >= 0 ? '↑ ' : '↓ ') . abs($pct_order) . '% dari bulan lalu',
                'ikon'    => 'fa-receipt',
                'tautan'  => 'admin/invoices/lihat/semua/semua',
            ],
            [
                'label'  => 'Uang masuk bulan ini',
                'nilai'  => 'Rp ' . formatRibuan($bulan[$bulan_ini]['dibayar']),
                'ket'    => 'Sisa tagihan Rp ' . formatRibuan($bulan[$bulan_ini]['sisa']),
                'ikon'   => 'fa-wallet',
                'tautan' => 'admin/invoices/lihat/semua/pembayaran/perlu-cek',
            ],
            [
                'label'  => 'Perlu dicek',
                'nilai'  => (string) $aksi['perlu_dicek'],
                'ket'    => $aksi['transfer'] . ' transfer menunggu · Rp ' . formatRibuan($aksi['nominal']),
                'ikon'   => 'fa-money-check-edit',
                'tautan' => 'admin/invoices/lihat/semua/pembayaran/perlu-cek',
            ],
            [
                'label'  => 'Stok menipis',
                'nilai'  => (string) $stok['menipis'],
                'ket'    => $stok['habis'] . ' varian habis dari ' . $stok['varian'] . ' tercatat',
                'ikon'   => 'fa-triangle-exclamation',
                'tautan' => 'admin/produk?keadaan=menipis',
            ],
        ];

        foreach ($kartu as $k) { ?>
            <div class="col-6 col-lg">
                <a class="h-100 d-block rounded-3 border border-ink-200 bg-white px-3 py-2 text-decoration-none"
                    href="<?= site_url($k['tautan']) ?>">
                    <div class="text-muted small text-uppercase">
                        <i class="fal <?= $k['ikon'] ?>"></i> <?= esc($k['label']) ?>
                    </div>
                    <div class="fs-4 fw-bold text-ink-900"><?= esc($k['nilai']) ?></div>
                    <div class="small text-ink-500"><?= esc($k['ket']) ?></div>
                </a>
            </div>
        <?php } ?>
    </div>

    <div class="row gx-3">
        <div class="col-lg-8">
            <div class="card mb-3">
                <div class="card-body">
                    <h2 class="h6 mb-3">Tren <?= count($tren) ?> bulan terakhir</h2>
                    <?php if ($tren === []) { ?>
                        <p class="text-muted mb-0">Belum ada transaksi untuk ditampilkan.</p>
                    <?php } else { ?>
                        <div class="position-relative" style="height: 17rem">
                            <canvas id="trenBulanan"></canvas>
                        </div>
                        <p class="small text-muted mt-2 mb-0">
                            Batang abu-abu = jumlah order (skala kiri), garis merah = nilai transaksi dan
                            biru = uang masuk (skala kanan).
                        </p>
                    <?php } ?>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header bg-white">
                    <h2 class="h6 mb-0">Transaksi terbaru</h2>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="small text-muted text-uppercase">
                            <tr>
                                <th scope="col">Faktur</th>
                                <th scope="col">Pelanggan</th>
                                <th scope="col">Juragan</th>
                                <th scope="col" class="text-end">Total</th>
                                <th scope="col" class="text-end">Sisa</th>
                                <th scope="col">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($transaksi as $t) {
                                $kelas = status_pembayaran([], $t['status_pembayaran'], 'l_class');
                                $label = status_pembayaran([], $t['status_pembayaran'], 'label'); ?>
                                <tr>
                                    <td class="text-nowrap">
                                        <a href="<?= $tautan_transaksi($t['slug'], $t['seri']) ?>" class="text-decoration-none">
                                            <?= esc($t['seri']) ?>
                                        </a>
                                        <div class="small text-muted"><?= esc($t['tanggal_pesan']) ?></div>
                                    </td>
                                    <td><?= esc($t['nama_pelanggan'] ?? '—') ?></td>
                                    <td><?= esc($t['nama_juragan']) ?></td>
                                    <td class="text-end text-nowrap">Rp <?= formatRibuan((int) $t['total']) ?></td>
                                    <td class="text-end text-nowrap <?= (int) $t['sisa'] > 0 ? 'text-danger' : 'text-muted' ?>">
                                        Rp <?= formatRibuan((int) $t['sisa']) ?>
                                    </td>
                                    <td class="text-nowrap">
                                        <span class="badge text-bg-<?= $kelas ?>"><?= esc($label) ?></span>
                                    </td>
                                </tr>
                            <?php } ?>

                            <?php if ($transaksi === []) { ?>
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-5">
                                        <i class="fal fa-inbox fa-3x d-block mb-2"></i>
                                        Belum ada transaksi untuk juraganmu.
                                    </td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
                <div class="card-footer bg-white text-end">
                    <?= anchor('admin/invoices/lihat/semua/semua', 'Lihat semua transaksi', ['class' => 'btn btn-sm btn-outline-primary']) ?>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card mb-3">
                <div class="card-header bg-white">
                    <h2 class="h6 mb-0">Menunggu tindakan</h2>
                </div>
                <ul class="list-group list-group-flush">
                    <?php
                    $daftar_aksi = [
                        ['fa-money-check-edit', 'Transfer belum dicek', (string) $aksi['transfer'],
                            'Rp ' . formatRibuan($aksi['nominal']), 'admin/invoices/lihat/semua/pembayaran/menunggu'],
                        ['fa-hourglass-half', 'Tagihan menunggu konfirmasi', (string) $aksi['perlu_dicek'],
                            'status 2 dan 3', 'admin/invoices/lihat/semua/pembayaran/perlu-cek'],
                        ['fa-inbox', 'Orderan belum diproses', (string) $aksi['belum_proses'],
                            'status pesanan 1', 'admin/invoices/lihat/semua/belum-proses/semua'],
                        ['fa-shipping-fast', 'Sudah diproses, belum dikirim', (string) $aksi['belum_terkirim'],
                            'cek progres pengiriman', 'admin/invoices/lihat/semua/dalam-proses'],
                        ['fa-wallet', 'Pembayaran dicicil', (string) $aksi['dicicil'],
                            'sisa tagihan masih jalan', 'admin/invoices/lihat/semua/pembayaran/dicicil'],
                        ['fa-triangle-exclamation', 'Stok menipis', (string) $stok['menipis'],
                            'ambang ' . $ambangnya . ' unit', 'admin/produk?keadaan=menipis'],
                        ['fa-circle-xmark', 'Stok habis', (string) $stok['habis'],
                            'butuh tambah barang', 'admin/produk?keadaan=habis'],
                    ];

                    foreach ($daftar_aksi as [$ikon, $label_aksi, $jumlah, $catatan, $tautan]) { ?>
                        <li class="list-group-item">
                            <a class="d-flex align-items-center text-decoration-none" href="<?= site_url($tautan) ?>">
                                <i class="fal <?= $ikon ?> text-brand-500 me-2"></i>
                                <span class="me-auto">
                                    <span class="d-block"><?= esc($label_aksi) ?></span>
                                    <span class="small text-muted"><?= esc($catatan) ?></span>
                                </span>
                                <span class="fw-bold"><?= esc($jumlah) ?></span>
                            </a>
                        </li>
                    <?php } ?>
                </ul>
            </div>

            <div class="card mb-3">
                <div class="card-header bg-white">
                    <h2 class="h6 mb-0">Produk terlaris 3 bulan</h2>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <tbody>
                            <?php foreach ($produk as $p) { ?>
                                <tr>
                                    <td><?= esc($p['produk']) ?></td>
                                    <td class="text-end text-nowrap"><?= (int) $p['qty'] ?> pcs</td>
                                    <td class="text-end text-nowrap">Rp <?= formatRibuan((int) $p['nilai']) ?></td>
                                </tr>
                            <?php } ?>

                            <?php if ($produk === []) { ?>
                                <tr>
                                    <td class="text-center text-muted py-4">Belum ada penjualan dalam 3 bulan ini.</td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
                <div class="card-footer bg-white text-end">
                    <?= anchor('admin/produk', 'Kelola stok', ['class' => 'btn btn-sm btn-outline-primary']) ?>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-body">
                    <h2 class="h6">Ringkasan</h2>
                    <dl class="row small mb-0">
                        <dt class="col-7 text-normal text-muted">Juragan yang dipantau</dt>
                        <dd class="col-5 text-end mb-2"><?= $jum_juragan ?></dd>
                        <dt class="col-7 text-normal text-muted">Varian stok tercatat</dt>
                        <dd class="col-5 text-end mb-2"><?= $stok['varian'] ?></dd>
                        <dt class="col-7 text-normal text-muted">Nilai stok saat ini</dt>
                        <dd class="col-5 text-end mb-0">Rp <?= formatRibuan($stok['nilai']) ?></dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('js') ?>
<script defer src="https://kit.fontawesome.com/22a9d93fa2.js" data-auto-replace-svg="nest" crossorigin="anonymous"></script>
<script defer src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.9.3/Chart.min.js"></script>
<?= $this->include('template/common_js') ?>
<?php
$label_tren = json_encode(array_map($label_bulan, array_column($tren, 'bulan')));
$order_tren = json_encode(array_map('intval', array_column($tren, 'orderan')));
$total_tren = json_encode(array_map('intval', array_column($tren, 'total')));
$dibayar_tren = json_encode(array_map('intval', array_column($tren, 'dibayar')));

$js = <<< JS
    \$(function() {
        'use strict';

        var kanvas = document.getElementById('trenBulanan');

        if (kanvas && typeof Chart !== 'undefined') {
            new Chart(kanvas.getContext('2d'), {
                type: 'bar',
                data: {
                    labels: {$label_tren},
                    datasets: [{
                        type: 'line',
                        label: 'Nilai transaksi (Rp)',
                        data: {$total_tren},
                        borderColor: '#da251d',
                        backgroundColor: '#da251d',
                        yAxisID: 'y-axis-1',
                        fill: false
                    }, {
                        label: 'Dibayar (Rp)',
                        data: {$dibayar_tren},
                        backgroundColor: '#0ea5e9',
                        yAxisID: 'y-axis-1'
                    }, {
                        label: 'Jumlah order',
                        data: {$order_tren},
                        backgroundColor: '#e2e8f0',
                        yAxisID: 'y-axis-0'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        yAxes: [{
                            id: 'y-axis-0',
                            position: 'left',
                            ticks: { beginAtZero: true, precision: 0 },
                            gridLines: { display: false }
                        }, {
                            id: 'y-axis-1',
                            position: 'right',
                            ticks: {
                                beginAtZero: true,
                                callback: function (nilai) {
                                    return nilai >= 1000000 ? Math.round(nilai / 100000) / 10 + ' jt' : nilai;
                                }
                            }
                        }]
                    },
                    legend: { position: 'bottom' }
                }
            });
        }
    });
    JS;

$packer    = new Tholu\Packer\Packer($js, 'Normal', true, false, true);
$packed_js = $packer->pack();
echo '<script>' . $packed_js . '</script>';
?>
<?= $this->endSection() ?>
