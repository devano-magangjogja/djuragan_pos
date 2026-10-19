<?php

$pager = \Config\Services::pager();

$rupiah = static fn (int $n): string => number_format($n, 0, ',', '.');
$tanggal = static fn ($ts): string => $ts ? date('d/m/Y', (int) $ts) : '-';
?>
<?= $this->extend('template/main') ?>

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
            <div class="col-6 col-lg">
                <div class="h-100 rounded-3 border border-ink-200 bg-white px-3 py-2">
                    <div class="text-muted small text-uppercase">
                        <i class="fal <?= $ikon ?>"></i> <?= esc($label) ?>
                    </div>
                    <div class="fs-4 fw-bold"><?= esc($nilai) ?></div>
                </div>
            </div>
        <?php } ?>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <?= form_open('admin/poin', ['method' => 'get', 'class' => 'row g-2 align-items-end']) ?>
            <div class="col-6 col-md-3">
                <?= form_label('Dari tanggal', 'awal', ['class' => 'form-label small']) ?>
                <?= form_input(['type' => 'date', 'class' => 'form-control form-control-sm', 'name' => 'awal', 'value' => $f['awal']]) ?>
            </div>
            <div class="col-6 col-md-3">
                <?= form_label('Sampai tanggal', 'akhir', ['class' => 'form-label small']) ?>
                <?= form_input(['type' => 'date', 'class' => 'form-control form-control-sm', 'name' => 'akhir', 'value' => $f['akhir']]) ?>
            </div>
            <div class="col-6 col-md-3">
                <?= form_label('Toko', 'juragan', ['class' => 'form-label small']) ?>
                <?= form_dropdown([
                    'class'    => 'form-select form-select-sm',
                    'name'     => 'juragan',
                    'options'  => [0 => 'Semua toko'] + $juragans,
                    'selected' => $f['juragan'],
                ]) ?>
            </div>
            <div class="col-6 col-md-3">
                <?= form_label('Pegawai', 'user', ['class' => 'form-label small']) ?>
                <?= form_dropdown([
                    'class'    => 'form-select form-select-sm',
                    'name'     => 'user',
                    'options'  => [0 => 'Semua pegawai'] + array_column($pegawai, 'nama', 'user_id'),
                    'selected' => $f['user'],
                ]) ?>
            </div>
            <div class="col-12 col-md-3">
                <button class="btn btn-sm btn-dark w-100" type="submit"><i class="fal fa-filter"></i> Terapkan</button>
            </div>
            <?= form_close() ?>
        </div>
    </div>

    <div class="row gx-3">
        <div class="col-12 col-xl-6 mb-4">
            <div class="card h-100">
                <div class="card-header bg-white fw-semibold"><i class="fal fa-trophy me-1"></i> Rekap per pegawai</div>
                <div class="card-body">
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
            </div>
        </div>

        <div class="col-12 col-xl-6 mb-4">
            <div class="card h-100">
                <div class="card-header bg-white fw-semibold"><i class="fal fa-list me-1"></i> Rincian pemberian poin</div>
                <div class="card-body">
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
                                        <th>Nota</th>
                                        <th>Produk</th>
                                        <th>Pegawai</th>
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
                                            <td class="small"><?= esc($r['seri'] ?? ('#' . (int) $r['invoice_id'])) ?></td>
                                            <td>
                                                <span class="fw-semibold"><?= esc($r['kode']) ?></span>
                                                <?php if ($ukuran !== '') { ?><span class="text-muted small">/ <?= esc($ukuran) ?></span><?php } ?>
                                                <div class="text-muted small"><?= esc($r['nama_juragan'] ?? '') ?></div>
                                            </td>
                                            <td class="small"><?= esc($r['nama']) ?></td>
                                            <td class="text-end"><?= (int) $r['qty'] ?></td>
                                            <td class="text-end fw-bold"><?= $rupiah((int) $r['poin_total']) ?></td>
                                        </tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                        </div>

                        <div class="mt-3">
                            <?= $pager->makeLinks($page, $limit, $total, 'front_full') ?>
                        </div>
                    <?php } ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
