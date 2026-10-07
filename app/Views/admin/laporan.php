<?php

use App\Models\LaporanModel;

// laporan dibaca angka per rupiah, bukan pendek seperti kartu dasbor: selisih
// Masuk dan Tercatat sering hanya beberapa ratus ribu dan tidak boleh terbaca sama
$rp = static fn ($n): string => 'Rp ' . number_format((int) $n, 0, ',', '.');

// dua kolom uang selalu diberi penjelasan lewat title, jadi tidak ada yang
// menebak-nebak sendiri bedanya
$th_masuk    = '<th scope="col" class="text-end" title="Hanya transfer yang sudah dicek. Angka ini sama dengan kartu Transaksi dan Dasbor.">Masuk</th>';
$th_tercatat = '<th scope="col" class="text-end" title="Semua catatan pembayaran, cara hitung lama. Bedanya dengan Masuk adalah transfer yang belum dicek atau dinyatakan tidak ada.">Tercatat</th>';

// filter ikut dibawa saat berpindah laporan dan saat mengunduh, jadi membandingkan
// dua laporan tidak perlu mengisi ulang
$param = static function () use ($f): array {
    return array_filter([
        'awal'    => $f['awal'],
        'akhir'   => $f['akhir'],
        'juragan' => $f['juragan'] > 0 ? $f['juragan'] : null,
        'kode'    => $f['kode'] !== '' ? $f['kode'] : null,
        'tahap'   => $f['tahap'] >= 0 ? $f['tahap'] : null,
        'bayar'   => $f['bayar'] !== '' ? $f['bayar'] : null,
        'nama'    => $f['nama'] !== '' ? $f['nama'] : null,
    ], static fn ($v) => $v !== null && $v !== '');
};

$tautan = static function (string $jenis) use ($param): string {
    return site_url('admin/laporan/' . $jenis) . '?' . http_build_query($param());
};

$tautan_unduh = static function (string $format) use ($param, $jenis): string {
    return site_url('admin/laporan/' . $jenis . '/unduh/' . $format) . '?' . http_build_query($param());
};

$tautan_orderan = static function (string $slug, string $seri): string {
    return site_url('admin/invoices/lihat/' . $slug . '/semua?cari[kolom]=faktur&cari[q]=' . urlencode($seri));
};

// orderan lama boleh tidak punya tenggat atau nama: ditulis "-", bukan dibiarkan kosong
$kosong = static fn ($v): string => ($v === null || $v === '') ? '&mdash;' : esc((string) $v);

$tanggal = static function ($v): string {
    $stamped = ($v === null || $v === '') ? false : strtotime((string) $v);

    return $stamped === false ? '&mdash;' : date('j M Y', $stamped);
};

$label_tahap = static function (int $no) use ($tahap): string {
    return $no > 0 ? ($tahap[$no][1] ?? '-') : 'Belum mulai';
};

$label_bayar  = static fn ($status): string => status_pembayaran([], (int) $status, 'label');
$warna_bayar = static fn ($status): string => status_pembayaran([], (int) $status, 'l_class');

$r         = $hasil['ringkas'];
$baris     = $hasil['baris'];
$terpotong = count($baris) >= LaporanModel::KAPASITAS;

$kartu = LaporanModel::kartu($jenis, $r);
?>
<?= $this->extend('template/main') ?>

<?= $this->section('content') ?>
<?= $this->include('admin/navbar') ?>

<div class="container-xxl">
    <h1 class="h3 mt-5"><?= esc($pil_jenis[$jenis]['label']) ?></h1>
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb p-0">
            <li class="breadcrumb-item"><?= anchor('admin/dasbor', 'Dasbor') ?></li>
            <li class="breadcrumb-item active" aria-current="page">Laporan</li>
        </ol>
    </nav>

    <ul class="nav nav-pills flex-wrap gap-1 mb-2">
        <?php foreach ($pil_jenis as $slug => $pil) { ?>
            <li class="nav-item">
                <a class="nav-link py-1 px-3 <?= $slug === $jenis ? 'active' : '' ?>" href="<?= $tautan($slug) ?>">
                    <i class="fal <?= $pil['ikon'] ?> me-1"></i><?= esc($pil['label']) ?>
                </a>
            </li>
        <?php } ?>
    </ul>

    <p class="text-muted small mb-3"><?= esc($pil_jenis[$jenis]['catatan']) ?></p>

    <div class="row gx-2 gy-3 mb-3">
        <?php foreach ($kartu as [$label, $nilai, $ikon]) { ?>
            <div class="col-6 col-lg">
                <div class="h-100 rounded-3 border border-ink-200 bg-white px-3 py-2">
                    <div class="text-muted small text-uppercase">
                        <i class="fal <?= $ikon ?>"></i> <?= esc($label) ?>
                    </div>
                    <div class="fs-5 fw-bold"><?= esc($nilai) ?></div>
                </div>
            </div>
        <?php } ?>
    </div>

    <div class="card mb-3">
        <div class="card-body py-3">
            <?= form_open('admin/laporan/' . $jenis, ['method' => 'get', 'class' => 'row g-2 align-items-end']) ?>
            <div class="col-6 col-md-2">
                <?= form_label('Tanggal awal', 'awal', ['class' => 'form-label small']) ?>
                <?= form_input(['class' => 'form-control form-control-sm', 'type' => 'date', 'name' => 'awal', 'value' => $f['awal']]) ?>
            </div>
            <div class="col-6 col-md-2">
                <?= form_label('Tanggal akhir', 'akhir', ['class' => 'form-label small']) ?>
                <?= form_input(['class' => 'form-control form-control-sm', 'type' => 'date', 'name' => 'akhir', 'value' => $f['akhir']]) ?>
            </div>
            <div class="col-6 col-md-2">
                <?= form_label('Juragan', 'juragan', ['class' => 'form-label small']) ?>
                <?= form_dropdown([
                    'class'    => 'form-select form-select-sm',
                    'name'     => 'juragan',
                    'options'  => [0 => 'Semua juragan'] + $juragans,
                    'selected' => $f['juragan'],
                ]) ?>
            </div>
            <div class="col-6 col-md-2">
                <?= form_label('Produk', 'kode', ['class' => 'form-label small']) ?>
                <?= form_input([
                    'class'       => 'form-control form-control-sm',
                    'name'        => 'kode',
                    'list'        => 'daftar-kode',
                    'placeholder' => 'kode produk',
                    'value'       => $f['kode'],
                ]) ?>
                <datalist id="daftar-kode">
                    <?php foreach ($kode as $k) { ?>
                        <option value="<?= esc($k) ?>"></option>
                    <?php } ?>
                </datalist>
            </div>
            <div class="col-6 col-md-2">
                <?= form_label('Status produksi', 'tahap', ['class' => 'form-label small']) ?>
                <?= form_dropdown([
                    'class'    => 'form-select form-select-sm',
                    'name'     => 'tahap',
                    'options'  => [-1 => 'Semua tahap', 0 => 'Belum mulai'] + array_map(static fn ($t) => $t[1], $tahap),
                    'selected' => $f['tahap'],
                ]) ?>
            </div>
            <div class="col-6 col-md-2">
                <?= form_label('Status pembayaran', 'bayar', ['class' => 'form-label small']) ?>
                <?= form_dropdown([
                    'class'    => 'form-select form-select-sm',
                    'name'     => 'bayar',
                    'options'  => ['' => 'Semua'] + array_map(static fn ($k) => $k['label'], $kategori),
                    'selected' => $f['bayar'],
                ]) ?>
            </div>
            <div class="col-6 col-md-3">
                <?= form_label('Customer', 'nama', ['class' => 'form-label small']) ?>
                <?= form_input([
                    'class'       => 'form-control form-control-sm',
                    'name'        => 'nama',
                    'placeholder' => 'nama pelanggan',
                    'value'       => $f['nama'],
                ]) ?>
            </div>
            <div class="col-auto">
                <button class="btn btn-sm btn-dark" type="submit"><i class="fal fa-search"></i> Tampilkan</button>
            </div>
            <div class="col-auto">
                <a class="btn btn-sm btn-outline-secondary" href="<?= site_url('admin/laporan/' . $jenis) ?>"
                    title="Bulan ini, tanpa filter lain"><i class="fal fa-undo"></i> Reset</a>
            </div>
            <div class="col-md-auto ms-md-auto text-nowrap">
                <a class="btn btn-sm btn-outline-success" href="<?= $tautan_unduh('xlsx') ?>"
                    title="Unduh ringkasan dan daftar notanya dalam Excel"><i class="fal fa-file-excel"></i> Excel</a>
                <a class="btn btn-sm btn-outline-danger" href="<?= $tautan_unduh('pdf') ?>"
                    title="Unduh laporan yang sama dalam PDF"><i class="fal fa-file-pdf"></i> PDF</a>
            </div>
            <?= form_close() ?>
        </div>
    </div>

    <?php if ($pil_jenis[$jenis]['uang']) { ?>
        <p class="text-muted small mb-3">
            Dua kolom uang: <strong>Masuk</strong> hanya menghitung transfer yang sudah dicek, sama seperti kartu
            Transaksi dan Dasbor. <strong>Tercatat</strong> menghitung semua catatan pembayaran, cara hitung lama yang
            dipakai laporan sebelumnya. Bedanya adalah transfer yang belum dicek atau dinyatakan tidak ada.
        </p>
    <?php } ?>

    <div class="card mb-4">
        <div class="card-body">
            <?php if ($terpotong) { ?>
                <div class="alert alert-warning py-2 small">
                    Baru <?= LaporanModel::KAPASITAS ?> baris teratas yang ditampilkan. Persempit rentang tanggalnya
                    supaya totalnya ikut terbaca lengkap.
                </div>
            <?php } ?>

            <?php if ($baris === []) { ?>
                <div class="text-center py-5 text-muted">
                    <i class="fal fa-file-alt fa-3x mb-2 d-block"></i>
                    Belum ada orderan pada rentang ini. Lebarkan tanggalnya, orderan lama tetap ikut dihitung.
                </div>

            <?php } elseif ($jenis === 'pesanan') { ?>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <caption class="text-muted small">
                            Satu baris per <?= $hasil['periode'] ?>. Orderan dihitung dari tanggal pesan.
                        </caption>
                        <thead>
                            <tr>
                                <th scope="col">Periode</th>
                                <th scope="col" class="text-end">Orderan</th>
                                <th scope="col" class="text-end">Customer</th>
                                <th scope="col" class="text-end">Nilai orderan</th>
                                <?= $th_masuk ?>
                                <?= $th_tercatat ?>
                                <th scope="col" class="text-end">Sisa</th>
                                <th scope="col" class="text-end">Lunas</th>
                                <th scope="col" class="text-end">Terkirim</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($baris as $b) { ?>
                                <tr>
                                    <td class="text-nowrap"><?= esc($b['periode']) ?></td>
                                    <td class="text-end"><?= (int) $b['orderan'] ?></td>
                                    <td class="text-end"><?= (int) $b['pelanggan'] ?></td>
                                    <td class="text-end"><?= $rp($b['tagihan']) ?></td>
                                    <td class="text-end"><?= $rp($b['masuk']) ?></td>
                                    <td class="text-end"><?= $rp($b['tercatat']) ?></td>
                                    <td class="text-end"><?= $rp($b['sisa']) ?></td>
                                    <td class="text-end"><?= (int) $b['lunas'] ?></td>
                                    <td class="text-end"><?= (int) $b['terkirim'] ?></td>
                                </tr>
                            <?php } ?>
                        </tbody>
                        <tfoot>
                            <tr class="fw-semibold">
                                <td>Total</td>
                                <td class="text-end"><?= (int) $r['orderan'] ?></td>
                                <td class="text-end"><?= (int) $r['pelanggan'] ?></td>
                                <td class="text-end"><?= $rp($r['tagihan']) ?></td>
                                <td class="text-end"><?= $rp($r['masuk']) ?></td>
                                <td class="text-end"><?= $rp($r['tercatat']) ?></td>
                                <td class="text-end"><?= $rp($r['sisa']) ?></td>
                                <td class="text-end"><?= (int) $r['lunas'] ?></td>
                                <td class="text-end"><?= (int) $r['terkirim'] ?></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

            <?php } elseif ($jenis === 'pendapatan') { ?>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <caption class="text-muted small">
                            Per <?= esc($hasil['periode']) ?> berdasarkan tanggal pesan. Nilai orderan = produk + biaya tambahan.
                        </caption>
                        <thead>
                            <tr>
                                <th scope="col">Bulan</th>
                                <th scope="col" class="text-end">Orderan</th>
                                <th scope="col" class="text-end">Nilai orderan</th>
                                <?= $th_masuk ?>
                                <?= $th_tercatat ?>
                                <th scope="col" class="text-end">Sisa</th>
                                <th scope="col" class="text-end" title="Dana masuk dibagi nilai orderan.">Terbayar</th>
                                <th scope="col" class="text-end">Lunas</th>
                                <th scope="col" class="text-end">Belum lunas</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($baris as $b) {
                                $persen = (int) $b['tagihan'] > 0 ? (int) round($b['masuk'] / $b['tagihan'] * 100) : null; ?>
                                <tr>
                                    <td class="text-nowrap"><?= esc($b['periode']) ?></td>
                                    <td class="text-end"><?= (int) $b['orderan'] ?></td>
                                    <td class="text-end"><?= $rp($b['tagihan']) ?></td>
                                    <td class="text-end"><?= $rp($b['masuk']) ?></td>
                                    <td class="text-end"><?= $rp($b['tercatat']) ?></td>
                                    <td class="text-end"><?= $rp($b['sisa']) ?></td>
                                    <td class="text-end"><?= $persen === null ? '&mdash;' : $persen . '%' ?></td>
                                    <td class="text-end"><?= (int) $b['lunas'] ?></td>
                                    <td class="text-end"><?= (int) $b['belum_lunas'] ?></td>
                                </tr>
                            <?php } ?>
                        </tbody>
                        <tfoot>
                            <tr class="fw-semibold">
                                <td>Total</td>
                                <td class="text-end"><?= (int) $r['orderan'] ?></td>
                                <td class="text-end"><?= $rp($r['tagihan']) ?></td>
                                <td class="text-end"><?= $rp($r['masuk']) ?></td>
                                <td class="text-end"><?= $rp($r['tercatat']) ?></td>
                                <td class="text-end"><?= $rp($r['sisa']) ?></td>
                                <td class="text-end">&nbsp;</td>
                                <td class="text-end"><?= (int) $r['lunas'] ?></td>
                                <td class="text-end">&nbsp;</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

            <?php } elseif ($jenis === 'pembayaran') { ?>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <caption class="text-muted small">
                            Baris per transfer, diurut dari yang terbaru. Tanggal di sini adalah tanggal transfer,
                            jadi pembayaran orderan lama yang baru dicek tetap muncul.
                        </caption>
                        <thead>
                            <tr>
                                <th scope="col">Transfer</th>
                                <th scope="col">Faktur</th>
                                <th scope="col">Customer</th>
                                <?php if ($f['juragan'] === 0) { ?>
                                    <th scope="col">Juragan</th>
                                <?php } ?>
                                <th scope="col" class="text-end">Nominal</th>
                                <th scope="col">Dana</th>
                                <th scope="col">Dicek</th>
                                <th scope="col">Status orderan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($baris as $b) {
                                $cek = label_bayar_cek($b['status']); ?>
                                <tr>
                                    <td class="text-nowrap"><?= $tanggal($b['tanggal_pembayaran']) ?></td>
                                    <td>
                                        <a href="<?= $tautan_orderan((string) $b['slug'], (string) $b['seri']) ?>">
                                            <?= esc($b['seri']) ?>
                                        </a>
                                    </td>
                                    <td><?= $kosong($b['nama_pelanggan'] ?? null) ?></td>
                                    <?php if ($f['juragan'] === 0) { ?>
                                        <td class="small"><?= $kosong($b['nama_juragan'] ?? null) ?></td>
                                    <?php } ?>
                                    <td class="text-end"><?= $rp($b['total_pembayaran']) ?></td>
                                    <td class="small">
                                        <?= $kosong($b['nama_bank'] ?? null) ?>
                                        <?php if (! empty($b['atas_nama'])) { ?>
                                            <span class="text-muted">&middot; a/n <?= esc($b['atas_nama']) ?></span>
                                        <?php } ?>
                                    </td>
                                    <td>
                                        <span class="badge rounded-pill bg-<?= $cek['kelas'] ?>"><?= esc($cek['teks']) ?></span>
                                        <?php if (! empty($b['tanggal_cek'])) { ?>
                                            <div class="text-muted small"><?= $tanggal($b['tanggal_cek']) ?></div>
                                        <?php } ?>
                                    </td>
                                    <td>
                                        <span class="badge rounded-pill bg-<?= $warna_bayar($b['status_pembayaran']) ?>">
                                            <?= esc($label_bayar($b['status_pembayaran'])) ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php } ?>
                        </tbody>
                        <tfoot>
                            <tr class="fw-semibold">
                                <td colspan="<?= $f['juragan'] === 0 ? 4 : 3 ?>">Total</td>
                                <td class="text-end"><?= $rp($r['tercatat']) ?></td>
                                <td colspan="3" class="small text-muted">
                                    termasuk <?= $rp($r['masuk']) ?> yang sudah dicek
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

            <?php } elseif ($jenis === 'piutang') { ?>
                <div class="table-responsive mb-4">
                    <table class="table table-sm align-middle mb-0">
                        <caption class="text-muted small">Umur tunggakan dihitung dari tanggal pesan.</caption>
                        <thead>
                            <tr>
                                <th scope="col">Umur</th>
                                <th scope="col" class="text-end">Orderan</th>
                                <th scope="col" class="text-end">Sisa</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($hasil['umur'] as $u) { ?>
                                <tr>
                                    <td><?= esc($u['keranjang']) ?></td>
                                    <td class="text-end"><?= (int) $u['orderan'] ?></td>
                                    <td class="text-end"><?= $rp($u['sisa']) ?></td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>

                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                            <tr>
                                <th scope="col">Faktur</th>
                                <th scope="col">Customer</th>
                                <?php if ($f['juragan'] === 0) { ?>
                                    <th scope="col">Juragan</th>
                                <?php } ?>
                                <th scope="col">Tanggal pesan</th>
                                <th scope="col">Tenggat</th>
                                <th scope="col" class="text-end">Nilai orderan</th>
                                <th scope="col" class="text-end" title="Hanya transfer yang sudah dicek.">Masuk</th>
                                <th scope="col" class="text-end">Sisa</th>
                                <th scope="col" class="text-end">Umur</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($baris as $b) { ?>
                                <tr>
                                    <td>
                                        <a href="<?= $tautan_orderan((string) $b['slug'], (string) $b['seri']) ?>">
                                            <?= esc($b['seri']) ?>
                                        </a>
                                        <span class="badge rounded-pill bg-<?= $warna_bayar($b['status_pembayaran']) ?>">
                                            <?= esc($label_bayar($b['status_pembayaran'])) ?>
                                        </span>
                                    </td>
                                    <td><?= $kosong($b['nama_pelanggan'] ?? null) ?></td>
                                    <?php if ($f['juragan'] === 0) { ?>
                                        <td class="small"><?= $kosong($b['nama_juragan'] ?? null) ?></td>
                                    <?php } ?>
                                    <td class="text-nowrap"><?= $tanggal($b['tanggal_pesan']) ?></td>
                                    <td class="text-nowrap"><?= $tanggal($b['deadline']) ?></td>
                                    <td class="text-end"><?= $rp($b['tagihan']) ?></td>
                                    <td class="text-end"><?= $rp($b['masuk']) ?></td>
                                    <td class="text-end fw-semibold"><?= $rp($b['sisa']) ?></td>
                                    <td class="text-end"><?= (int) $b['umur'] ?> hari</td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>

            <?php } elseif ($jenis === 'produksi') { ?>
                <div class="table-responsive mb-4">
                    <table class="table table-sm align-middle mb-0">
                        <caption class="text-muted small">
                            Orderan dihitung pada tahap terakhir yang pernah dicatat.
                        </caption>
                        <thead>
                            <tr>
                                <th scope="col">Tahap</th>
                                <th scope="col" class="text-end">Berhenti di sini</th>
                                <th scope="col" class="text-end" title="Orderan yang pernah singgah di tahap ini.">Pernah dikerjakan</th>
                                <th scope="col" class="text-end">Sudah selesai</th>
                                <th scope="col" class="text-end">Nilai orderan</th>
                                <th scope="col" class="text-end">Sisa</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($baris as $b) { ?>
                                <tr>
                                    <td>
                                        <i class="fal fa-<?= esc($b['tahap'] > 0 ? $tahap[$b['tahap']][0] : 'inbox-in') ?> me-1"></i>
                                        <?= esc($label_tahap((int) $b['tahap'])) ?>
                                    </td>
                                    <td class="text-end fw-semibold"><?= (int) $b['orderan'] ?></td>
                                    <td class="text-end"><?= (int) $b['dikerjakan'] ?></td>
                                    <td class="text-end"><?= (int) $b['beres'] ?></td>
                                    <td class="text-end"><?= $rp($b['tagihan']) ?></td>
                                    <td class="text-end"><?= $rp($b['sisa']) ?></td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>

                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <caption class="text-muted small">
                            Lama pengerjaan = tanggal pesan sampai catatan tahap terakhir. Yang belum pernah masuk
                            antrean kerja tidak ikut dihitung.
                        </caption>
                        <thead>
                            <tr>
                                <th scope="col">Lama pengerjaan</th>
                                <th scope="col" class="text-end">Orderan</th>
                                <th scope="col" class="text-end">Rata-rata</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($hasil['lama'] as $l) { ?>
                                <tr>
                                    <td><?= esc($l['keranjang']) ?></td>
                                    <td class="text-end"><?= (int) $l['orderan'] ?></td>
                                    <td class="text-end"><?= (int) $l['rata'] ?> hari</td>
                                </tr>
                            <?php } ?>
                            <?php if ($hasil['lama'] === []) { ?>
                                <tr>
                                    <td colspan="3" class="text-muted">Belum ada orderan yang punya catatan tahap.</td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>

            <?php } elseif ($jenis === 'produk') { ?>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <caption class="text-muted small">
                            Produk dibaca dari kode yang tertulis pada orderan, jadi kode lama yang tidak punya
                            master produk tetap ikut terbaca.
                        </caption>
                        <thead>
                            <tr>
                                <th scope="col">Produk</th>
                                <th scope="col" class="text-end">Orderan</th>
                                <th scope="col" class="text-end">Unit</th>
                                <th scope="col" class="text-end">Nilai</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($baris as $b) { ?>
                                <tr>
                                    <td><?= esc($b['produk']) ?></td>
                                    <td class="text-end"><?= (int) $b['orderan'] ?></td>
                                    <td class="text-end"><?= (int) $b['qty'] ?></td>
                                    <td class="text-end"><?= $rp($b['nilai']) ?></td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>

            <?php } else { ?>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <caption class="text-muted small">
                            Nama pelanggan dibaca apa adanya; data lama belum tentu menggabungkan pelanggan yang sama,
                            jadi satu nama bisa muncul lebih dari satu baris.
                        </caption>
                        <thead>
                            <tr>
                                <th scope="col">Customer</th>
                                <th scope="col" class="text-end">Orderan</th>
                                <th scope="col" class="text-end">Nilai orderan</th>
                                <th scope="col" class="text-end" title="Hanya transfer yang sudah dicek.">Masuk</th>
                                <th scope="col" class="text-end">Sisa</th>
                                <th scope="col" class="text-end">Lunas</th>
                                <th scope="col">Pertama</th>
                                <th scope="col">Terakhir</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($baris as $b) { ?>
                                <tr>
                                    <td><?= esc($b['pelanggan']) ?></td>
                                    <td class="text-end"><?= (int) $b['orderan'] ?></td>
                                    <td class="text-end"><?= $rp($b['tagihan']) ?></td>
                                    <td class="text-end"><?= $rp($b['masuk']) ?></td>
                                    <td class="text-end"><?= $rp($b['sisa']) ?></td>
                                    <td class="text-end"><?= (int) $b['lunas'] ?></td>
                                    <td class="text-nowrap small"><?= $tanggal($b['pertama']) ?></td>
                                    <td class="text-nowrap small"><?= $tanggal($b['terakhir']) ?></td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            <?php } ?>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
