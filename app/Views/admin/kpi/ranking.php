<?php
/*
 * Peringkat karyawan dari poin aktivitas.
 *
 * Tabelnya dibaca dari atas ke bawah: semakin banyak kerja yang tercatat dan
 * semakin besar nilai nota yang dilunasi, semakin tinggi poinnya. Aturan poin
 * dan daftar aktivitas yang tidak dinilai dibuka lewat popup, jadi halaman ini
 * hanya berisi angkanya.
 */

$rp    = static fn ($n): string => 'Rp ' . number_format((float) $n, 0, ',', '.');
$angka = static fn ($n): string => number_format((float) $n, fmod((float) $n, 1.0) === 0.0 ? 0 : 1, ',', '.');
$poin  = static fn ($n): string => ($n <=> 0) > 0 ? '+' . $angka($n) : $angka($n);
$sel   = static fn (int $n): string => $n === 0 ? '&mdash;' : $angka($n);

// kelompok kolom supaya tabel tidak perlu tiga belas kolom sekaligus
$kelompok = [
    'Nota'      => ['nota_dibuat'],
    'Lunas'     => ['nota_lunas'],
    'Nilai'     => ['nilai_lunas'],
    'Produksi'  => ['tahap_produksi', 'foto_progres'],
    'Chat'      => ['balasan_chat', 'sesi_dijawab'],
    'Konversi'  => ['lead_jadi_order'],
    'Lainnya'   => ['tindak_lanjut_selesai', 'aktivitas_crm', 'pesan_manual', 'broadcast_dibuat'],
    'Pengurang' => ['void_salah'],
];

$petunjuk = [
    'Nota'      => 'nota dibuat',
    'Lunas'     => 'nota lunas',
    'Nilai'     => 'nota lunas per Rp 1 juta',
    'Produksi'  => 'tahap produksi dikerjakan + foto progres',
    'Chat'      => 'pesan dibalas + sesi dijawab pertama',
    'Konversi'  => 'lead menjadi order',
    'Lainnya'   => 'tindak lanjut, catatan CRM, pesan manual, broadcast',
    'Pengurang' => 'nota dibatalkan karena kesalahan',
];

$nama_slug = array_column($aturan, 'nama', 'slug');

$maks     = 0.0;
$tercatat = 0;
foreach ($peringkat as $b) {
    $maks = max($maks, (float) $b['total']);
}
foreach ($aturan as $a) {
    $tercatat += (int) $a['tercatat'];
}

$teratas   = $peringkat[0]['total'] ?? 0.0;
$bernilai  = array_filter($peringkat, static fn ($b) => array_sum($b['jumlah']) > 0);
$kosong    = count($peringkat) - count($bernilai);
?>
<?= $this->extend('template/main') ?>

<?= $this->section('content') ?>
<?= $this->include('admin/navbar') ?>

<div class="container-xxl">
    <h1 class="h3 mt-5">Ranking Karyawan</h1>
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb p-0">
            <li class="breadcrumb-item"><?= anchor('admin/dasbor', 'Dasbor') ?></li>
            <li class="breadcrumb-item"><?= anchor('admin/kpi', 'KPI') ?></li>
            <li class="breadcrumb-item active" aria-current="page">Ranking</li>
        </ol>
    </nav>

    <ul class="nav nav-pills flex-wrap gap-1 mb-3">
        <li class="nav-item">
            <a class="nav-link py-1 px-3" href="<?= site_url('admin/kpi') ?>">
                <i class="fal fa-bullseye me-1"></i>Ringkasan
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link py-1 px-3 active" href="<?= site_url('admin/kpi/ranking') ?>">
                <i class="fal fa-trophy me-1"></i>Ranking
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link py-1 px-3" href="<?= site_url('admin/kpi/target') ?>">
                <i class="fal fa-target me-1"></i>Target dan bobot
            </a>
        </li>
    </ul>

    <div class="row gx-2 gy-3 mb-3">
        <?php
        $kartu = [
            ['Karyawan dinilai', $angka(count($peringkat)), 'level admin, superadmin, dan cs yang masih aktif'],
            ['Poin tertinggi', $teratas === 0.0 ? '&mdash;' : $angka($teratas), $peringkat[0]['pegawai'] ?? '&mdash;'],
            ['Aktivitas tercatat', $angka($tercatat), 'jumlah satuan kerja yang masuk rumus poin'],
            ['Belum ada jejak', $angka($kosong), 'poin 0 karena aktivitasnya tidak tercatat atas namanya'],
        ];
        foreach ($kartu as [$label, $nilai, $bawah]) { ?>
            <div class="col-6 col-lg-3">
                <div class="h-100 rounded-3 border border-ink-200 bg-white px-3 py-2">
                    <div class="text-muted small text-uppercase"><?= esc($label) ?></div>
                    <div class="fs-5 fw-bold"><?= $nilai ?></div>
                    <div class="small text-muted"><?= esc($bawah) ?></div>
                </div>
            </div>
        <?php } ?>
    </div>

    <?= $this->include('admin/kpi/saring') ?>

    <div class="card mb-4">
        <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center gap-2">
            <span class="fw-semibold small">
                Peringkat <?= esc($s['awal']) ?> &ndash; <?= esc($s['akhir']) ?>
                <span class="text-muted fw-normal">&middot; <?= esc($peran[$s['peran']]) ?></span>
            </span>
            <a class="small" href="#" data-bs-toggle="modal" data-bs-target="#modalAturanPoin">
                <i class="fal fa-circle-info me-1"></i>Aturan poin
            </a>
        </div>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th scope="col">#</th>
                        <th scope="col">Karyawan</th>
                        <?php foreach (array_keys($kelompok) as $kel) { ?>
                            <th scope="col" class="text-center" title="<?= esc($petunjuk[$kel]) ?>"><?= esc($kel) ?></th>
                        <?php } ?>
                        <th scope="col" class="text-end">Poin</th>
                        <th scope="col" class="text-end">Catatan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($peringkat as $b) { ?>
                        <tr>
                            <td class="fw-semibold">
                                <?= (int) $b['peringkat'] ?>
                                <?php if ((float) $b['total'] > 0 && (int) $b['peringkat'] <= 3) { ?>
                                    <i class="fal fa-trophy text-warning ms-1"></i>
                                <?php } ?>
                            </td>
                            <td>
                                <div class="fw-semibold"><?= esc($b['pegawai']) ?></div>
                                <div class="small text-muted"><?= esc($b['level']) ?></div>
                            </td>
                            <?php foreach ($kelompok as $kel => $slugs) { ?>
                                <?php
                                $satuan = 0;
                                $jumlah = 0.0;
                                $rincian = [];
                                foreach ($slugs as $slug) {
                                    $satuan += (int) $b['jumlah'][$slug];
                                    $jumlah += (float) $b['poin'][$slug];
                                    $rincian[] = $nama_slug[$slug] . ': ' . $angka((int) $b['jumlah'][$slug]);
                                }
                                ?>
                                <td class="text-center" title="<?= esc(implode(', ', $rincian)) ?>">
                                    <div><?= $satuan === 0 ? '&mdash;' : $angka($satuan) ?></div>
                                    <?php if ($jumlah != 0.0) { ?>
                                        <div class="small text-muted"><?= $poin($jumlah) ?></div>
                                    <?php } ?>
                                </td>
                            <?php } ?>
                            <td class="text-end">
                                <div class="fw-semibold <?= (float) $b['total'] < 0 ? 'text-danger' : '' ?>">
                                    <?= $b['tercatat'] === 0 ? '&mdash;' : $angka($b['total']) ?>
                                </div>
                                <?php if ($maks > 0 && (float) $b['total'] > 0) { ?>
                                    <div class="progress mt-1 ms-auto" style="height: 3px; width: 64px;">
                                        <div class="progress-bar" style="width: <?= round((float) $b['total'] / $maks * 100) ?>%"></div>
                                    </div>
                                <?php } ?>
                            </td>
                            <td class="text-end small text-muted">
                                <?php if ($b['tercatat'] === 0) { ?>
                                    tidak ada aktivitas tercatat
                                <?php } elseif ((float) $b['gmv'] > 0) { ?>
                                    nota lunas <?= $rp($b['gmv']) ?>
                                <?php } else { ?>
                                    belum ada nota lunas
                                <?php } ?>
                            </td>
                        </tr>
                    <?php } ?>
                    <?php if ($peringkat === []) { ?>
                        <tr>
                            <td colspan="<?= 4 + count($kelompok) ?>">
                                Tidak ada akun yang dinilai pada saringan ini.
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Popup aturan poin: rumus, sumber data, dan kerja yang tidak bisa dinilai -->
<div class="modal fade" id="modalAturanPoin" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fal fa-circle-info me-2"></i>Aturan Poin</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted">
                    Poin yang sama mendapat peringkat yang sama. Nota yang dibatalkan tetap
                    memberi poin pada tahap produksi dan fotonya karena kerjanya memang terjadi.
                </p>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-4">
                        <thead class="table-light">
                            <tr>
                                <th scope="col">Aktivitas</th>
                                <th scope="col">Sumber data</th>
                                <th scope="col" class="text-end">Poin per satuan</th>
                                <th scope="col" class="text-end">Tercatat</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($aturan as $a) { ?>
                                <tr>
                                    <td>
                                        <div><?= esc($a['nama']) ?></div>
                                        <?php if (isset($a['catatan'])) { ?>
                                            <div class="small text-muted"><?= esc($a['catatan']) ?></div>
                                        <?php } ?>
                                    </td>
                                    <td class="small text-muted"><code><?= esc($a['sumber']) ?></code></td>
                                    <td class="text-end">
                                        <?= $poin((float) $a['poin']) ?>
                                        <span class="small text-muted"><?= esc($a['satuan']) ?></span>
                                    </td>
                                    <td class="text-end">
                                        <?= $sel((int) $a['tercatat']) ?>
                                        <?php if ((int) $a['belum_ternilai'] > 0) { ?>
                                            <div class="small text-muted">
                                                + <?= $angka((int) $a['belum_ternilai']) ?> tanpa pelaku
                                            </div>
                                        <?php } ?>
                                    </td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>

                <h6 class="fw-semibold small">Yang tidak masuk poin</h6>
                <p class="small text-muted mb-2">
                    Dikerjakan lewat halaman yang menyimpan waktunya tetapi tidak menyimpan
                    siapa yang mengerjakan, jadi tidak bisa dinilai per orang:
                </p>
                <ul class="small mb-0">
                    <?php foreach ($luar as $sumber => $kerja) { ?>
                        <li><code><?= esc($sumber) ?></code> &mdash; <?= esc($kerja) ?></li>
                    <?php } ?>
                </ul>
                <p class="small text-muted mt-3 mb-0">
                    Kualitas kerja (SLA pemenuhan, kecepatan balas, ketepatan pembatalan, waktu
                    penyelesaian) dinilai di halaman Ringkasan dan tidak digabung ke poin.
                    Kolom <strong>&mdash;</strong> berarti jejaknya tidak ada di database, bukan
                    berarti orangnya tidak bekerja.
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
