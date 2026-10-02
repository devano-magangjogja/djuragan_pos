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

$saring = saring_transaksi();
$tahap  = tahap_produksi();

// nama tahap produksi untuk kolom Status, 0 artinya belum masuk antrean kerja
$label_tahap = static function (int $no) use ($tahap): string {
    return $no > 0 ? ($tahap[$no][1] ?? '-') : 'Belum mulai';
};

// kartu dan panel mengarah ke daftar yang angkanya dihitung dengan klausul yang sama
$tautan_saring = static function (array $pil) use ($sasaran): string {
    return 'admin/invoices/lihat/' . $sasaran . '/' . $pil[0] . ($pil[1] === '' ? '' : '/' . $pil[1]);
};

$tautan_bayar = static function (string $kategori) use ($sasaran): string {
    return 'admin/invoices/lihat/' . $sasaran . '/pembayaran/' . $kategori;
};

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

    <?php if ($nama_juragan !== []) : ?>
        <p class="small text-muted mb-3">
            <?= count($nama_juragan) === 1 ? 'Toko' : count($nama_juragan) . ' toko' ?> yang dipantau:
            <?= esc(implode(', ', $nama_juragan)) ?>
        </p>
    <?php endif; ?>

    <?php
    // kartu utama versi brief: order hari ini sampai siap diambil
    $kartu = [
        [
            'label'  => $saring['pesan-hari-ini']['label'],
            'nilai'  => (string) $saringan['pesan-hari-ini'],
            'ket'    => 'Rp ' . formatRibuan($hari['total']) . ' · ' . $hari['pelanggan'] . ' pelanggan',
            'ikon'   => $saring['pesan-hari-ini']['ikon'],
            'tautan' => $tautan_saring($saring['pesan-hari-ini']['tautan']),
        ],
        [
            'label'  => $saring['aktif']['label'],
            'nilai'  => (string) $saringan['aktif'],
            'ket'    => $saringan['belum-diproses'] . ' belum mulai dikerjakan',
            'ikon'   => $saring['aktif']['ikon'],
            'tautan' => $tautan_saring($saring['aktif']['tautan']),
        ],
        [
            'label'    => $saring['belum-lunas']['label'],
            'nilai'    => (string) $saringan['belum-lunas'],
            'ket'      => 'Sisa tagihan Rp ' . formatRibuan($sisa),
            'ikon'     => $saring['belum-lunas']['ikon'],
            'tautan'   => $tautan_saring($saring['belum-lunas']['tautan']),
            'mendesak' => true,
        ],
        [
            'label'  => $saring['produksi']['label'],
            'nilai'  => (string) $saringan['produksi'],
            'ket'    => 'Antrean kerja berjalan',
            'ikon'   => $saring['produksi']['ikon'],
            'tautan' => $tautan_saring($saring['produksi']['tautan']),
        ],
        [
            'label'    => $saring['deadline-3-hari']['label'],
            'nilai'    => (string) $saringan['deadline-3-hari'],
            'ket'      => $saringan['deadline-terlewat'] . ' sudah terlewat',
            'ikon'     => $saring['deadline-3-hari']['ikon'],
            'tautan'   => $tautan_saring($saring['deadline-3-hari']['tautan']),
            'mendesak' => $saringan['deadline-terlewat'] > 0,
        ],
        [
            'label'  => $saring['siap-diambil']['label'],
            'nilai'  => (string) $saringan['siap-diambil'],
            'ket'    => 'Packing selesai, menunggu pelanggan',
            'ikon'   => $saring['siap-diambil']['ikon'],
            'tautan' => $tautan_saring($saring['siap-diambil']['tautan']),
        ],
    ];
    ?>
    <div class="row gx-2 gy-3 mb-4">
        <?php foreach ($kartu as $k) {
            // angka nol tidak perlu ikut mencolok
            $mendesak = ! empty($k['mendesak']) && (int) $k['nilai'] > 0; ?>
            <div class="col-6 col-md-4 col-lg-2">
                <a class="h-100 d-block rounded-3 border border-ink-200 bg-white px-3 py-2 text-decoration-none"
                    href="<?= site_url($k['tautan']) ?>">
                    <div class="text-muted small text-uppercase">
                        <i class="fal <?= $k['ikon'] ?>"></i> <?= esc($k['label']) ?>
                    </div>
                    <div class="fs-4 fw-bold <?= $mendesak ? 'text-danger' : 'text-ink-900' ?>"><?= esc($k['nilai']) ?></div>
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
                <div class="card-header bg-white d-flex align-items-center justify-content-between">
                    <h2 class="h6 mb-0">Ringkasan Produksi</h2>
                    <?= anchor('admin/invoices/lihat/' . $sasaran . '/dalam-proses', 'Lihat antrean', ['class' => 'btn btn-sm btn-outline-primary']) ?>
                </div>
                <div class="card-body">
                    <div class="row gx-2 gy-2">
                        <?php foreach ($produksi as $p) { ?>
                            <div class="col-6 col-md-3">
                                <div class="rounded-3 border border-ink-200 px-3 py-2">
                                    <div class="small text-uppercase text-muted">
                                        <i class="fal fa-<?= $p['ikon'] ?>"></i> <?= esc($p['label']) ?>
                                    </div>
                                    <div class="fw-bold"><?= (int) $p['jumlah'] ?></div>
                                </div>
                            </div>
                        <?php } ?>
                    </div>
                    <p class="small text-muted mb-0 mt-2">
                        Sebaran tahap terakhir <?= $saringan['aktif'] ?> order aktif;
                        <?= $produksi[0]['jumlah'] ?> di antaranya belum masuk antrean kerja.
                    </p>
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
                                <th scope="col">No Nota</th>
                                <th scope="col">Pelanggan</th>
                                <th scope="col">Produk</th>
                                <th scope="col">Deadline</th>
                                <th scope="col" class="text-end">Total / Sisa</th>
                                <th scope="col">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($transaksi as $t) {
                                $kelas     = status_pembayaran([], $t['status_pembayaran'], 'l_class');
                                $label_bayar = status_pembayaran([], $t['status_pembayaran'], 'label');
                                $dead      = label_deadline($t['deadline'] ?? null); ?>
                                <tr>
                                    <td class="text-nowrap">
                                        <a href="<?= $tautan_transaksi($t['slug'], $t['seri']) ?>" class="text-decoration-none">
                                            <?= esc($t['seri']) ?>
                                        </a>
                                        <div class="small text-muted"><?= esc($t['tanggal_pesan']) ?></div>
                                    </td>
                                    <td>
                                        <?= esc($t['nama_pelanggan'] ?? '—') ?>
                                        <?php if ($jum_juragan > 1) { ?>
                                            <div class="small text-muted"><?= esc($t['nama_juragan']) ?></div>
                                        <?php } ?>
                                    </td>
                                    <td>
                                        <?= $t['produk'] === '' ? '-' : esc($t['produk']) ?>
                                        <?php if ((int) $t['item'] > 1) { ?>
                                            <div class="small text-muted">+<?= (int) $t['item'] - 1 ?> item lain</div>
                                        <?php } ?>
                                    </td>
                                    <td class="text-nowrap">
                                        <?php if ($dead['kelas'] === '') { ?>
                                            <span class="text-muted"><?= esc($dead['teks']) ?></span>
                                        <?php } else { ?>
                                            <span class="badge text-bg-<?= $dead['kelas'] ?>"><?= esc($dead['teks']) ?></span>
                                        <?php } ?>
                                    </td>
                                    <td class="text-end text-nowrap">
                                        Rp <?= formatRibuan((int) $t['total']) ?>
                                        <div class="small <?= (int) $t['sisa'] > 0 ? 'text-danger' : 'text-muted' ?>">
                                            sisa Rp <?= formatRibuan((int) $t['sisa']) ?>
                                        </div>
                                    </td>
                                    <td class="text-nowrap">
                                        <span class="badge text-bg-<?= $kelas ?>"><?= esc($label_bayar) ?></span>
                                        <div class="small text-muted"><?= esc($label_tahap((int) $t['tahap'])) ?></div>
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
                    <?= anchor('admin/invoices/lihat/' . $sasaran . '/semua', 'Lihat semua transaksi', ['class' => 'btn btn-sm btn-outline-primary']) ?>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <?php
            // panel "butuh tindakan hari ini", urut dari pembayaran lalu alur kerja
            $butuh_tindakan = [
                ['fa-money-check-edit', 'Pembayaran belum dicek', (string) $aksi['perlu_dicek'],
                    'status 2 dan 3', $tautan_bayar('perlu-cek'), true],
                ['fa-hourglass-half', 'Transfer menunggu dicek', (string) $aksi['transfer'],
                    'Rp ' . formatRibuan($aksi['nominal']), $tautan_bayar('menunggu'), true],
                ['fa-wallet', 'Belum ada pembayaran', (string) $aksi['belum_bayar'],
                    'status 1', $tautan_bayar('belum'), false],
            ];

            $catatan_panel = [
                'belum-diproses'       => 'status pesanan 1',
                'ukuran-belum-lengkap' => 'rombongan belum ada ukuran',
                'deadline-3-hari'      => 'tenggat hari ini sampai 3 hari',
                'deadline-terlewat'    => 'lewat tenggat, belum diambil',
                'produksi'             => 'antrean kerja berjalan',
                'siap-diambil'         => 'menunggu pelanggan mengambil',
                'belum-lunas'          => 'sisa Rp ' . formatRibuan($sisa),
            ];

            foreach (['belum-diproses', 'ukuran-belum-lengkap', 'deadline-3-hari', 'deadline-terlewat',
                'produksi', 'siap-diambil', 'belum-lunas'] as $slug) {
                $butuh_tindakan[] = [
                    $saring[$slug]['ikon'],
                    $saring[$slug]['label'],
                    (string) $saringan[$slug],
                    $catatan_panel[$slug],
                    $tautan_saring($saring[$slug]['tautan']),
                    $saring[$slug]['mendesak'],
                ];
            }
            ?>
            <div class="card mb-3">
                <div class="card-header bg-white">
                    <h2 class="h6 mb-0">Perlu Tindakan</h2>
                </div>
                <ul class="list-group list-group-flush">
                    <?php foreach ($butuh_tindakan as [$ikon, $label_aksi, $jumlah, $catatan, $tautan, $mendesak]) {
                        $padas = $mendesak && (int) $jumlah > 0; ?>
                        <li class="list-group-item">
                            <a class="d-flex align-items-center text-decoration-none" href="<?= site_url($tautan) ?>">
                                <i class="fal <?= $ikon ?> <?= $padas ? 'text-danger' : 'text-brand-500' ?> me-2"></i>
                                <span class="me-auto">
                                    <span class="d-block"><?= esc($label_aksi) ?></span>
                                    <span class="small text-muted"><?= esc($catatan) ?></span>
                                </span>
                                <span class="fw-bold <?= $padas ? 'text-danger' : '' ?>"><?= esc($jumlah) ?></span>
                            </a>
                        </li>
                    <?php } ?>
                </ul>
            </div>

            <div class="card mb-3">
                <div class="card-header bg-white d-flex align-items-center justify-content-between">
                    <h2 class="h6 mb-0">Stok</h2>
                    <?= anchor('admin/produk', 'Kelola stok', ['class' => 'btn btn-sm btn-outline-primary']) ?>
                </div>
                <ul class="list-group list-group-flush">
                    <?php foreach ([
                        ['Stok menipis', (string) $stok['menipis'], 'ambang ' . $ambangnya . ' unit', 'admin/produk?keadaan=menipis'],
                        ['Varian habis', (string) $stok['habis'], 'dari ' . $stok['varian'] . ' varian tercatat', 'admin/produk?keadaan=habis'],
                    ] as [$label_aksi, $jumlah, $catatan, $tautan]) { ?>
                        <li class="list-group-item">
                            <a class="d-flex align-items-center text-decoration-none" href="<?= site_url($tautan) ?>">
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
            </div>

            <div class="card mb-3">
                <div class="card-body">
                    <h2 class="h6">Ringkasan</h2>
                    <dl class="row small mb-0">
                        <dt class="col-7 text-normal text-muted">Juragan yang dipantau</dt>
                        <dd class="col-5 text-end mb-2"><?= $jum_juragan ?></dd>
                        <dt class="col-7 text-normal text-muted">Order bulan ini</dt>
                        <dd class="col-5 text-end mb-2">
                            <?= $bulan[$bulan_ini]['orderan'] ?>
                            <?php if ($pct_order !== null) { ?>
                                <span class="small">(<?= $pct_order >= 0 ? '↑' : '↓' ?> <?= abs($pct_order) ?>%)</span>
                            <?php } ?>
                        </dd>
                        <dt class="col-7 text-normal text-muted">Uang masuk bulan ini</dt>
                        <dd class="col-5 text-end mb-2">Rp <?= formatRibuan($bulan[$bulan_ini]['dibayar']) ?></dd>
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
