<?php
/**
 * Variabel di bawah ini diisi dari Kpi::index() lewat view('admin/kpi/index', $data).
 * CodeIgniter memanggil extract() di dalam system/View/View.php, jadi analyzers tidak
 * bisa menelusuri asal variabelnya; blok @var ini hanya memberi tahu Intelephense.
 *
 * @var array<string, mixed>                        $s          Saringan: awal, akhir, periode, peran, user_id, juragan_id, juragan_ids
 * @var array<string, array<string, mixed>>         $kartu      Kartu indikator: kunci => [label, nilai, satuan, contoh, tersedia, ...]
 * @var array<string, array<string, mixed>>         $target     Target per indikator: kunci => [target, arah, bobot, aktif]
 * @var list<array<string, mixed>>                  $tren      Baris tren: baki, order, gmv, sla_jam
 * @var array<int, string>                          $pegawai    Pilihan karyawan: id => "nama (level)"
 * @var array<int, string>                          $juragans   Pilihan toko: id => nama
 * @var array<string, string>                       $periode    Label periode: kunci => teks
 * @var array<string, string>                       $peran      Label peran: kunci => teks
 * @var list<string>                                $indikator  Daftar kunci indikator yang punya halaman detail
 * @var array<string, mixed>                        $rekap      Rekap per karyawan: baris (list) dan jumlah (total orang)
 * @var int                                         $batas      Jumlah baris rekap per halaman
 * @var int                                         $halaman    Nomor halaman berbasis nol
 * @var string                                      $banding    Indikator yang dipilih untuk grafik perbandingan
 * @var list<array<string, mixed>>                  $pembanding Data grafik perbandingan: pegawai, nilai
 */

/*
 * Ringkasan KPI karyawan.
 *
 * Semua angka datang dari KpiModel; halaman ini hanya memformat. Kartu yang
 * angkanya belum bisa dihitung tetap tampil, dengan keterangan kenapa kosong,
 * supaya pembaca laporan tahu bedanya "nol" dan "belum dicatat".
 */

$rp = static fn ($n): string => 'Rp ' . number_format((int) $n, 0, ',', '.');

$label_satuan = ['jam' => ' jam', 'menit' => ' menit', 'orang' => ' orang', 'nota' => ' nota'];

// angka desimal hanya ditampilkan kalau memang ada desimalnya: 906 nota bukan 906,0 nota
$angka = static fn ($n): string => number_format((float) $n, fmod((float) $n, 1.0) === 0.0 ? 0 : 1, ',', '.');

$tampil = static function (array $k) use ($rp, $angka, $label_satuan): string {
    if ($k['nilai'] === null) {
        return '&mdash;';
    }

    if ($k['satuan'] === 'rupiah') {
        return $rp($k['nilai']);
    }

    if ($k['satuan'] === '%') {
        return number_format((float) $k['nilai'], 1, ',', '.') . '%';
    }

    return $angka($k['nilai']) . ($label_satuan[$k['satuan']] ?? '');
};

// target ditulis beserta arah indikatornya: "lebih rendah" berarti angka kecil yang bagus
$target_tampil = static function (array $t) use ($rp, $angka, $label_satuan): string {
    $nilai = $t['satuan'] === 'rupiah'
        ? $rp((float) $t['target'])
        : ($t['satuan'] === '%'
            ? number_format((float) $t['target'], 1, ',', '.') . '%'
            : $angka((float) $t['target']) . ($label_satuan[$t['satuan']] ?? ' ' . $t['satuan']));

    return ($t['arah'] === 'lebih_rendah' ? 'maks ' : 'min ') . $nilai;
};

$status = static function (string $kunci, array $k, array $target): array {
    if (! isset($target[$kunci]) || $target[$kunci]['target'] === null || $k['nilai'] === null) {
        return ['class' => '', 'teks' => isset($target[$kunci]) ? 'target belum diisi' : 'tanpa target'];
    }

    $tersentuh = $target[$kunci]['arah'] === 'lebih_rendah'
        ? (float) $k['nilai'] <= (float) $target[$kunci]['target']
        : (float) $k['nilai'] >= (float) $target[$kunci]['target'];

    return $tersentuh
        ? ['class' => 'text-success', 'teks' => 'target tercapai']
        : ['class' => 'text-danger', 'teks' => 'target belum tercapai'];
};

// tautan internal halaman: filter aktif selalu ikut dibawa
$t = static function (array $ubah = []) use ($s): string {
    $q = array_merge([
        'awal'    => $s['awal'],
        'akhir'   => $s['akhir'],
        'periode' => $s['periode'],
        'peran'   => $s['peran'],
        'pegawai' => $s['user_id'],
        'juragan' => $s['juragan_id'],
    ], $ubah);

    return site_url('admin/kpi') . '?' . http_build_query(array_filter(
        $q,
        static fn ($v) => $v !== null && $v !== '' && $v !== 0
    ));
};

$tautan_detail = static function (string $indikator) use ($s): string {
    $q = array_filter([
        'awal'    => $s['awal'],
        'akhir'   => $s['akhir'],
        'periode' => $s['periode'],
        'peran'   => $s['peran'],
        'pegawai' => $s['user_id'],
        'juragan' => $s['juragan_id'],
    ], static fn ($v) => $v !== null && $v !== '' && $v !== 0);

    return site_url('admin/kpi/detail/' . $indikator) . '?' . http_build_query($q);
};

$total_halaman = max(1, (int) ceil($rekap['jumlah'] / $batas));
$nomor         = $halaman + 1;
$awal_judul    = $rekap['jumlah'] === 0 ? 0 : $halaman * $batas + 1;
$akhir_judul   = min($rekap['jumlah'], $halaman * $batas + $batas);

// isian grafik disiapkan di PHP: yang masuk ke JS hanya angka dan nama pegawai
$label_tren    = json_encode(array_column($tren, 'baki'));
$order_tren    = json_encode(array_map('intval', array_column($tren, 'order')));
$gmv_tren      = json_encode(array_map('intval', array_column($tren, 'gmv')));
$sla_tren      = json_encode(array_map(static fn ($b) => $b['sla_jam'], $tren));
$nama_banding  = json_encode(array_column($pembanding, 'pegawai'));
$nilai_banding = json_encode(array_map(static fn ($p) => $p['nilai'], $pembanding));

$judul_banding = [
    'gmv_cs'              => 'GMV per karyawan (Rp)',
    'order_diproses'      => 'Pesanan diproses per karyawan (nota)',
    'sla_fulfillment'     => 'Rata-rata SLA fulfillment per karyawan (jam, makin kecil makin baik)',
    'error_void_rate'     => 'Nota dibatalkan karena kesalahan per karyawan (jumlah)',
    'first_response_time' => 'Rata-rata first response time per karyawan (menit, makin kecil makin baik)',
    'konversi_lead'       => 'Lead menjadi order per karyawan (jumlah)',
    'resolution_time'     => 'Rata-rata waktu penyelesaian tindak lanjut (jam, makin kecil makin baik)',
];

// penjelasan singkat per kartu, hanya muncul saat ikonnya ditahan
$tooltip = [
    'pegawai_dinilai'       => 'Akun Customer Service yang masih aktif.',
    'order_diproses'        => 'Semua nota yang dibuat orang ini pada periode terpilih, sudah lunas maupun belum.',
    'sla_fulfillment'       => 'Diukur dari pembayaran pertama yang tercatat sampai nota masuk packing atau kirim. Tanggal yang urutannya tidak masuk akal tidak ikut dihitung.',
    'return_error_rate'     => 'Butuh pencatatan retur beserta alasannya. Kalau form retur belum dipakai, angkanya memang belum ada.',
    'kecepatan_master_data' => 'Butuh waktu mulai input dan waktu stok masuk. Keduanya belum pernah direkam untuk data lama.',
    'error_void_rate'       => 'Nota dibatalkan dengan alasan salah input; yang tanpa alasan tidak dianggap kesalahan. Nama pelakunya baru dicatat sejak 9 Oktober 2026.',
    'first_response_time'   => 'Dari pesan masuk pelanggan sampai balasan pertama manusia. Balasan otomatis dan pesan yang gagal kirim tidak dihitung. Satu percakapan dihitung satu kali.',
    'konversi_lead'         => 'Chat baru dari pelanggan yang belum pernah order, lalu jadi order dalam 14 hari.',
    'resolution_time'       => 'Dari tindak lanjut dibuat sampai ditandai selesai. Yang masih menunggu tidak dihitung.',
    'gmv_cs'                => 'Hanya nota yang sudah lunas dan tidak dibatalkan, termasuk ongkir dan potongan biaya. Nota dari akun yang sudah nonaktif tidak ikut, jadi angkanya bisa beda dengan halaman Laporan.',
];
?>
<?= $this->extend('template/main') ?>

<?= $this->section('css') ?>
<style>
/* Kolom pilih indikator memakan sisa lebar barisnya supaya tombol "Ganti" tetap
   muat tanpa memaksa teks opsi terpotong lebih awal. Judulnya sendiri sudah naik
   ke baris di atasnya, jadi tidak ada lagi perebutan lebar antara judul dan kotak. */
.kpi-banding .form-select-sm {
    flex: 1 1 auto;
    min-width: 0;
}
@media (max-width: 767.98px) {
    /* Angka kartu ikut turun satu langkah di ponsel: fs-5 = 20px bikin nilai
       terpanjang ("Rp 187.766.650" = 132px) patah jadi dua baris di kotak isi
       yang lebarnya cuma 118px, sementara angka lain satu baris sehingga
       tingginya tidak rata. 1rem = 16px -> 106px, satu baris. .fs-5 Bootstrap
       memakai !important, jadi aturan ini ikut !important. Desktop tetap 20px. */
    .kpi-angka {
        font-size: 1rem !important;
    }
    /* Kanvas grafik ikut melebar: .card-body dikunci 1,25rem oleh style.css, jadi
       40px dari lebar kartu habis hanya sebagai jarak pandang. Legenda grafik
       "Tren" membutuhkan tiap piksel itu supaya tiga nama seri (masing-masing
       106/112/78px) muat satu baris di layar 360px. */
    .card-body.kpi-kanvas {
        padding-left: 0.5rem !important;
        padding-right: 0.5rem !important;
    }
}
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?= $this->include('admin/navbar') ?>

<div class="container-xxl">
    <h1 class="h3 mt-5">KPI Karyawan</h1>

    <p class="text-muted small mb-3">
        Semua angka dihitung dari catatan kerja yang tersimpan di database. Tanda
        <strong>&mdash;</strong> artinya datanya belum pernah tercatat, bukan bernilai nol.
        Tahan judul kartu untuk melihat cara angkanya didapat.
    </p>

    <?= $this->include('admin/kpi/saring') ?>

    <div class="row gx-2 gy-3 mb-4">
        <?php foreach ($kartu as $kunci => $k) { ?>
            <?php $st = $status($kunci, $k, $target); ?>
            <div class="col-6 col-lg-4 col-xl-3">
                <div class="h-100 rounded-3 border border-ink-200 bg-white px-3 py-2">
                    <div class="text-muted small text-uppercase" title="<?= esc($tooltip[$kunci] ?? 'Angka dihitung dari catatan yang tersimpan di database.') ?>">
                        <?= esc($k['label']) ?>
                    </div>
                    <div class="fs-5 fw-bold kpi-angka <?= ($k['tersedia'] ?? true) ? '' : 'text-muted' ?>">
                        <?= $tampil($k) ?>
                    </div>
                    <?php if (($k['tersedia'] ?? true) === false) { ?>
                        <div class="small text-muted"><?= esc($k['alasan']) ?></div>
                    <?php } else { ?>
                        <div class="small">
                            <span class="<?= $st['class'] ?>"><?= esc($st['teks']) ?></span>
                            <?php if (isset($target[$kunci]) && $target[$kunci]['target'] !== null) { ?>
                                &middot; <?= esc($target_tampil($target[$kunci])) ?>
                            <?php } ?>
                        </div>
                        <?php if (isset($k['median']) && $k['median'] !== null) { ?>
                            <div class="small text-muted">
                                setengahnya di bawah <?= $angka($k['median']) . ' ' . esc($k['satuan']) ?>
                            </div>
                        <?php } ?>
                        <?php if (isset($k['rincian'])) { ?>
                            <div class="small text-muted"><?= esc($k['rincian']) ?></div>
                        <?php } elseif ((int) $k['contoh'] > 0) { ?>
                            <div class="small text-muted">
                                dihitung dari <?= (int) $k['contoh'] ?> <?= esc($k['contoh_label'] ?? 'catatan') ?><?php
                                if (isset($k['buang']) && (int) $k['buang'] > 0) {
                                    echo ', ' . (int) $k['buang'] . ' lainnya tidak ikut dihitung';
                                } ?>
                            </div>
                        <?php } ?>
                    <?php } ?>
                    <?php if (in_array($kunci, $indikator, true)) { ?>
                        <div class="mt-1">
                            <a class="small" href="<?= $tautan_detail($kunci) ?>">lihat rinciannya &rarr;</a>
                        </div>
                    <?php } ?>
                </div>
            </div>
        <?php } ?>
    </div>

    <div class="row gx-3 gy-3 mb-4">
        <div class="col-12 col-xl-7">
            <div class="card h-100">
                <div class="card-header bg-white fw-semibold small">
                    Tren periode <?= esc($s['awal']) ?> &ndash; <?= esc($s['akhir']) ?> (<?= esc($periode[$s['periode']]) ?>)
                </div>
                <div class="card-body kpi-kanvas" style="height: 320px;">
                    <canvas id="trenKpi"></canvas>
                </div>
            </div>
        </div>
        <div class="col-12 col-xl-5">
            <div class="card h-100">
                <div class="card-header bg-white">
                    <div class="fw-semibold small mb-2"><?= esc($judul_banding[$banding] ?? 'Perbandingan karyawan') ?></div>
                    <?= form_open('admin/kpi', ['method' => 'get', 'class' => 'd-flex align-items-center gap-1 kpi-banding']) ?>
                    <?php foreach (['awal' => $s['awal'], 'akhir' => $s['akhir'], 'periode' => $s['periode'],
                        'peran' => $s['peran'], 'pegawai' => $s['user_id'], 'juragan' => $s['juragan_id']] as $nama => $nilai) { ?>
                        <?= form_input(['type' => 'hidden', 'name' => $nama, 'value' => $nilai]) ?>
                    <?php } ?>
                    <?= form_dropdown([
                        'class'    => 'form-select form-select-sm',
                        'name'     => 'banding',
                        'options'  => array_combine(
                            $indikator,
                            array_map(static fn ($i) => $judul_banding[$i] ?? $i, $indikator)
                        ),
                        'selected' => $banding,
                    ]) ?>
                    <?= form_submit(['class' => 'btn btn-sm btn-outline-primary', 'value' => 'Ganti']) ?>
                    <?= form_close(); ?>
                </div>
                <div class="card-body kpi-kanvas" style="height: 320px;">
                    <canvas id="bandingKpi"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
            <span class="fw-semibold small">
                Rekap per karyawan &mdash; <?= $awal_judul ?> sampai <?= $akhir_judul ?> dari <?= (int) $rekap['jumlah'] ?> orang
            </span>
            <span class="text-muted small">
                tanda &mdash; artinya belum ada catatan untuk orang ini
            </span>
        </div>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th scope="col">Karyawan</th>
                        <th scope="col">Role</th>
                        <th scope="col" class="text-end">Order</th>
                        <th scope="col" class="text-end">GMV</th>
                        <th scope="col" class="text-end" title="nota lunas (status pembayaran 5 atau 6)">Lunas</th>
                        <th scope="col" class="text-end" title="rata-rata jam dari pembayaran terverifikasi ke packing atau kirim">SLA (jam)</th>
                        <th scope="col" class="text-end" title="nota dihapus karena alasan kesalahan input">Void salah</th>
                        <th scope="col" class="text-end" title="menit sampai balasan manusia pertama per sesi chat">FRT (menit)</th>
                        <th scope="col" class="text-end" title="lead baru yang menjadi order dalam 14 hari">Lead jadi</th>
                        <th scope="col" class="text-end" title="jam dari tindak lanjut dibuat sampai diselesaikan">Selesai (jam)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($rekap['baris'] === []) { ?>
                        <tr>
                            <td colspan="10" class="text-center text-muted py-4">
                                Tidak ada karyawan dengan data pada saringan ini.
                            </td>
                        </tr>
                    <?php } ?>
                    <?php foreach ($rekap['baris'] as $b) { ?>
                        <tr>
                            <td>
                                <a href="<?= $t(['pegawai' => (int) $b['user_id']]) ?>"><?= esc($b['pegawai']) ?></a>
                            </td>
                            <td class="small"><?= esc($b['level']) ?></td>
                            <td class="text-end"><?= (int) $b['jumlah_order'] ?></td>
                            <td class="text-end text-nowrap"><?= $rp($b['gmv']) ?></td>
                            <td class="text-end"><?= (int) $b['lunas'] ?></td>
                            <td class="text-end"><?= $b['sla_jam'] === null ? '&mdash;' : number_format((float) $b['sla_jam'], 1, ',', '.') ?></td>
                            <td class="text-end">
                                <?= (int) $b['void_salah'] ?>
                                <?php if ($b['void_persen'] !== null) { ?>
                                    <span class="text-muted small">(<?= number_format((float) $b['void_persen'], 1, ',', '.') ?>%)</span>
                                <?php } ?>
                            </td>
                            <td class="text-end"><?= $b['frt_menit'] === null ? '&mdash;' : number_format((float) $b['frt_menit'], 1, ',', '.') ?></td>
                            <td class="text-end"><?= (int) $b['lead_jadi'] ?> / <?= (int) $b['lead'] ?></td>
                            <td class="text-end"><?= $b['resolusi'] === null ? '&mdash;' : number_format((float) $b['resolusi'], 1, ',', '.') ?></td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
        <?php if ($total_halaman > 1) { ?>
            <div class="card-footer bg-white">
                <nav aria-label="Halaman rekap">
                    <ul class="pagination pagination-sm mb-0">
                        <?php for ($i = 1; $i <= $total_halaman; $i++) { ?>
                            <?php if ($i === 1 || $i === $total_halaman || abs($i - $nomor) <= 2) { ?>
                                <li class="page-item <?= $i === $nomor ? 'active' : '' ?>">
                                    <a class="page-link" href="<?= $t(['halaman' => $i]) ?>"><?= $i ?></a>
                                </li>
                            <?php } elseif (abs($i - $nomor) === 3) { ?>
                                <li class="page-item disabled"><span class="page-link">&hellip;</span></li>
                            <?php } ?>
                        <?php } ?>
                    </ul>
                </nav>
            </div>
        <?php } ?>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('js') ?>
<script defer src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.9.3/Chart.min.js"></script>
<?php
// Legenda grafik "Tren" di ponsel: tiga nama seri lengkap + kotak warna default
// 40px butuh ±333px sedangkan kanvas di 360px cuma 279px, jadi Chart.js
// menumpuknya jadi tiga baris. Ruangnya dibeli dari bentuk, bukan dari kata:
// kotak warna 8px, jarak antar butir 8px, teks 9,5px, plus padding kiri-kanan kartu
// grafik 1,25rem -> 0,5rem (lihat .kpi-kanvas di blok css). Jauhnya warna pertama
// dari tepi kartu = padding kartu + sisa ruang di dalam kanvas: 8 + 14 = 22px di
// layar 360px (sebelumnya 4 + 9 = 13px dan ditolak: "terlalu mepet"), 8 + 29 = 37px
// di 390px. Ketiganya tetap satu baris di 360/390/430px. Desktop: teks legenda
// dinaikkan 12px -> 14px atas permintaan ("yng di dekstop ukurannya dibuat lebih
// gede"), kotak 40px + jarak 10px tetap; terukur masih satu baris di 1280/1366/
// 1440/1600/1920px dengan sisa ruang 13/38/60/94/146px di kiri.
// Di 320px masih dua baris: butuh kanvas 283px sedangkan yang ada 263px. NAMA SERI TIDAK BOLEH DIPENDEKKAN — user menolak
// "Nilai transaksi"/"SLA (jam)"/"Order": "penulisanya disesuaikan dengan yang
// asli jangan engganti nama". Desktop dibiarkan apa adanya (40/10/12).
$js = <<< JS
    \$(function() {
        'use strict';

        var sempit = window.innerWidth < 768;

        var kanvas = document.getElementById('trenKpi');

        if (kanvas && typeof Chart !== 'undefined') {
            new Chart(kanvas.getContext('2d'), {
                type: 'bar',
                data: {
                    labels: {$label_tren},
                    datasets: [{
                        label: 'Nilai transaksi (Rp)',
                        type: 'line',
                        data: {$gmv_tren},
                        borderColor: '#da251d',
                        backgroundColor: '#da251d',
                        fill: false,
                        yAxisID: 'y-axis-1'
                    }, {
                        label: 'SLA fulfillment (jam)',
                        type: 'line',
                        data: {$sla_tren},
                        borderColor: '#0ea5e9',
                        backgroundColor: '#0ea5e9',
                        fill: false,
                        steppedLine: true,
                        yAxisID: 'y-axis-2'
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
                    legend: {
                        labels: {
                            boxWidth: sempit ? 8 : 40,
                            padding: sempit ? 8 : 10,
                            fontSize: sempit ? 9.5 : 14
                        }
                    },
                    scales: {
                        yAxes: [{
                            id: 'y-axis-0',
                            position: 'left',
                            ticks: { beginAtZero: true }
                        }, {
                            id: 'y-axis-1',
                            position: 'right',
                            gridLines: { drawOnChartArea: false },
                            ticks: { beginAtZero: true, callback: function (v) { return 'Rp ' + (v / 1000000).toFixed(0) + ' jt'; } }
                        }, {
                            id: 'y-axis-2',
                            display: false,
                            ticks: { beginAtZero: true }
                        }]
                    }
                }
            });
        }

        var kanvasBanding = document.getElementById('bandingKpi');

        if (kanvasBanding && typeof Chart !== 'undefined') {
            new Chart(kanvasBanding.getContext('2d'), {
                type: 'bar',
                data: {
                    labels: {$nama_banding},
                    datasets: [{
                        data: {$nilai_banding},
                        backgroundColor: '#0ea5e9'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    legend: { display: false },
                    scales: {
                        yAxes: [{ ticks: { beginAtZero: true } }],
                        xAxes: [{ ticks: { autoSkip: false, maxRotation: 60, minRotation: 60 } }]
                    }
                }
            });
        }
    });
JS;
?>
<script><?= $js ?></script>
<?= $this->endSection() ?>
