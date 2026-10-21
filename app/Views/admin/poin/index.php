<?php
/**
 * Laporan akumulasi poin gaji (khusus Superadmin).
 *
 * @var array<string, mixed>          $f         Filter aktif: awal, akhir, juragan, user
 * @var list<array<string, mixed>>    $akumulasi Rekap poin per pegawai
 * @var array<string, int>            $ringkas   Total poin, unit, nota, pegawai
 * @var list<array<string, mixed>>    $rincian   Pemberian poin per item (drill-down)
 * @var int                           $total     Jumlah baris rincian (untuk pager)
 * @var list<array<string, mixed>>    $pegawai   Pilihan pegawai pada filter
 * @var array<int, string>            $juragans  Pilihan toko pada filter
 * @var int                           $limit     Baris rincian per halaman
 * @var int                           $page      Nomor halaman rincian aktif
 */

$pager = \Config\Services::pager();

$rupiah  = static fn (int $n): string => number_format($n, 0, ',', '.');
$tanggal = static fn ($ts): string => $ts ? date('d/m/Y', (int) $ts) : '-';
?>
<?= $this->extend('template/main') ?>

<?= $this->section('css') ?>
<style>
    /* Tab laporan poin memakai .nav-pills yang sudah distyle global di style.css.
       Di layar paling sempit strip pill dibuat bisa digulir mendatar supaya tidak
       melengkung ke baris kedua dan memakan tempat konten. */
    #poinTabs {
        flex-wrap: nowrap;
        overflow-x: auto;
        overflow-y: hidden;
        padding-bottom: 0.25rem;
        scrollbar-width: thin;
    }
    #poinTabs .nav-link { white-space: nowrap; }
    #poinTabs .nav-link i { margin-right: 0.35rem; }
    @media (min-width: 576px) {
        #poinTabs { flex-wrap: wrap; overflow: visible; }
    }

    /* Baris filter "telanjang" tanpa kartu, meniru .cod-saring di Pencatatan COD:
       kolom dibagi rata dan input tanggal boleh menyusut di bawah lebar isinya,
       jadi tidak ada padding card-body yang berlebihan di sekelilingnya. */
    .poin-saring > div,
    .poin-saring input[type="date"] {
        min-width: 0;
    }
    .poin-saring .form-label { letter-spacing: 0.01em; }
</style>
<?php $this->endSection() ?>

<?= $this->section('content') ?>
<?= $this->include('admin/navbar') ?>

<div class="container-xxl">
    <h1 class="h3 mt-5">Akumulasi Poin Gaji</h1>
    <p class="text-muted small mb-4">
        Rekap poin yang terkumpul dari penjualan produk yang sudah <strong>Lunas</strong>. Poin diberikan
        kepada pembuat nota; angka di sini dipakai sebagai dasar perhitungan gaji.
    </p>

    <div class="row gx-2 gy-3 mb-4">
        <?php
        $ringkasan = [
            ['Total poin', $rupiah((int) ($ringkas['poin'] ?? 0)), 'fa-coins'],
            ['Unit berpoin', $rupiah((int) ($ringkas['unit'] ?? 0)), 'fa-boxes-stacked'],
            ['Nota lunas', (string) (int) ($ringkas['nota'] ?? 0), 'fa-receipt'],
            ['Pegawai tercatat', (string) (int) ($ringkas['pegawai'] ?? 0), 'fa-users'],
        ];

        foreach ($ringkasan as [$label, $nilai, $ikon]) { ?>
            <div class="col-6 col-md-3">
                <div class="h-100 rounded-3 border border-ink-200 bg-white px-3 py-2">
                    <div class="text-muted small text-uppercase text-truncate">
                        <i class="fal <?= $ikon ?>"></i> <?= esc($label) ?>
                    </div>
                    <div class="fs-5 fw-bold"><?= esc($nilai) ?></div>
                </div>
            </div>
        <?php } ?>
    </div>

    <?= form_open('admin/poin', ['method' => 'get', 'class' => 'row g-2 align-items-end mb-4 poin-saring']) ?>
    <div class="col-6 col-md-4 col-xl">
        <?= form_label('Dari tanggal', 'awal', ['class' => 'form-label small mb-1']) ?>
        <?= form_input(['type' => 'date', 'class' => 'form-control form-control-sm', 'name' => 'awal', 'value' => $f['awal']]) ?>
    </div>
    <div class="col-6 col-md-4 col-xl">
        <?= form_label('Sampai tanggal', 'akhir', ['class' => 'form-label small mb-1']) ?>
        <?= form_input(['type' => 'date', 'class' => 'form-control form-control-sm', 'name' => 'akhir', 'value' => $f['akhir']]) ?>
    </div>
    <div class="col-6 col-md-4 col-xl">
        <?= form_label('Toko', 'juragan', ['class' => 'form-label small mb-1']) ?>
        <?= form_dropdown([
            'class'    => 'form-select form-select-sm',
            'name'     => 'juragan',
            'options'  => [0 => 'Semua toko'] + $juragans,
            'selected' => $f['juragan'],
        ]) ?>
    </div>
    <div class="col-6 col-md-4 col-xl">
        <?= form_label('Pegawai', 'user', ['class' => 'form-label small mb-1']) ?>
        <?= form_dropdown([
            'class'    => 'form-select form-select-sm',
            'name'     => 'user',
            'options'  => [0 => 'Semua pegawai'] + array_column($pegawai, 'nama', 'user_id'),
            'selected' => $f['user'],
        ]) ?>
    </div>
    <div class="col-6 col-md-4 col-xl">
        <button class="btn btn-sm btn-dark w-100" type="submit"><i class="fal fa-filter me-1"></i>Terapkan</button>
    </div>
    <?= form_close() ?>

    <div class="card mb-4">
        <div class="card-header bg-white border-bottom py-2 px-2 px-sm-3">
            <ul class="nav nav-pills" id="poinTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="btn-tab-rekap" data-bs-toggle="pill" data-bs-target="#tab-rekap" type="button" role="tab" aria-controls="tab-rekap" aria-selected="true">
                        <i class="fal fa-trophy"></i>Rekap per Pegawai
                        <span class="badge bg-light text-dark border ms-1"><?= count($akumulasi) ?></span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="btn-tab-rincian" data-bs-toggle="pill" data-bs-target="#tab-rincian" type="button" role="tab" aria-controls="tab-rincian" aria-selected="false">
                        <i class="fal fa-list"></i>Rincian Pemberian Poin
                        <span class="badge bg-light text-dark border ms-1"><?= (int) $total ?></span>
                    </button>
                </li>
            </ul>
        </div>
        <div class="card-body p-2 p-sm-3">
            <div class="tab-content">
                <!-- Tab 1: Rekap per pegawai -->
                <div class="tab-pane fade show active" id="tab-rekap" role="tabpanel" aria-labelledby="btn-tab-rekap">
                    <?php if ($akumulasi === []) { ?>
                        <div class="text-center text-muted py-4">
                            <i class="fal fa-coins fa-2x mb-2 d-block"></i>
                            Belum ada poin pada periode/filter ini.
                        </div>
                    <?php } else { ?>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover align-middle mb-0">
                                <thead>
                                    <tr class="small text-muted">
                                        <th>Pegawai</th>
                                        <th class="text-end">Nota</th>
                                        <th class="text-end">Unit</th>
                                        <th class="text-end">Poin</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($akumulasi as $a) { ?>
                                        <tr>
                                            <td><?= esc($a['nama']) ?></td>
                                            <td class="text-end"><?= (int) $a['nota'] ?></td>
                                            <td class="text-end"><?= $rupiah((int) $a['unit']) ?></td>
                                            <td class="text-end fw-bold"><?= $rupiah((int) $a['poin']) ?></td>
                                        </tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                        </div>
                    <?php } ?>
                </div>

                <!-- Tab 2: Rincian pemberian poin -->
                <div class="tab-pane fade" id="tab-rincian" role="tabpanel" aria-labelledby="btn-tab-rincian">
                    <?php if ($rincian === []) { ?>
                        <div class="text-center text-muted py-4">
                            <i class="fal fa-inbox fa-2x mb-2 d-block"></i>
                            Tidak ada transaksi berpoin yang cocok.
                        </div>
                    <?php } else { ?>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead>
                                    <tr class="small text-muted">
                                        <th>Tanggal</th>
                                        <th class="d-none d-md-table-cell">Nota</th>
                                        <th>Produk</th>
                                        <th class="d-none d-lg-table-cell">Pegawai</th>
                                        <th class="text-end">Qty</th>
                                        <th class="text-end">Poin</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($rincian as $r) {
                                        $ukuran = (string) ($r['ukuran'] ?? '');
                                        ?>
                                        <tr>
                                            <td class="text-nowrap small"><?= $tanggal($r['created_at']) ?></td>
                                            <td class="small d-none d-md-table-cell"><?= esc($r['seri'] ?? ('#' . (int) $r['invoice_id'])) ?></td>
                                            <td>
                                                <span class="fw-semibold"><?= esc($r['kode']) ?></span>
                                                <?php if ($ukuran !== '') { ?><span class="text-muted small">/ <?= esc($ukuran) ?></span><?php } ?>
                                                <div class="text-muted small">
                                                    <span class="d-lg-none"><i class="fal fa-user me-1"></i><?= esc($r['nama']) ?></span>
                                                    <?= esc($r['nama_juragan'] ?? '') ?>
                                                </div>
                                            </td>
                                            <td class="small d-none d-lg-table-cell"><?= esc($r['nama']) ?></td>
                                            <td class="text-end"><?= (int) $r['qty'] ?></td>
                                            <td class="text-end fw-bold"><?= $rupiah((int) $r['poin_total']) ?></td>
                                        </tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                        </div>

                        <div class="mt-3 d-flex justify-content-center justify-content-md-between flex-wrap gap-2">
                            <span class="small text-muted">Halaman <?= (int) $page ?> &middot; <?= (int) $total ?> pemberian poin</span>
                            <?= $pager->makeLinks($page, $limit, $total, 'front_full') ?>
                        </div>
                    <?php } ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('js') ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var tabs = document.getElementById('poinTabs');
    if (!tabs || !window.bootstrap) {
        return;
    }

    // Rincian satu-satunya tab yang punya paginasi: saat halaman berubah
    // (page > 1), buka tab rincian lagi supaya pengguna tidak terlempar ke rekap.
    <?php if ($page > 1) { ?>
    var btnRincian = document.getElementById('btn-tab-rincian');
    if (btnRincian) {
        new bootstrap.Tab(btnRincian).show();
    }
    <?php } ?>
});
</script>
<?php $this->endSection() ?>
