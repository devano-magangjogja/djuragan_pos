<?php

$pager = \Config\Services::pager();

// error datang dari flashdata controller: ['kode' => 'pesan', ...]
$err = static fn (string $kolom): ?string => isset($errors[$kolom]) ? (string) $errors[$kolom] : null;

// Hanya modal tambah/ubah yang memakai withInput(), jadi isian lama menandai
// modal mana yang harus dibuka lagi setelah gagal. id_stok hanya ada pada
// kiriman modal ubah.
$pernahIsi   = old('kode') !== null || old('id_stok') !== null;
$dariSunting = old('id_stok') !== null;

$sunting_err = $pernahIsi && $dariSunting;
$tambah_err  = $pernahIsi && ! $dariSunting;

// Kode, harga dan stok memakai nama field yang sama di kedua modal, jadi
// pesannya hanya ditempelkan ke modal yang mengirimkannya.
$err_sunting = static fn (string $kolom): ?string => $sunting_err ? $err($kolom) : null;
$err_tambah  = static fn (string $kolom): ?string => $tambah_err ? $err($kolom) : null;

// badge warna untuk keadaan stok satu varian
$keadaan_baris = static function (int $stok) use ($ambangnya): array {
    if ($stok <= 0) {
        return ['danger', 'Habis'];
    }

    return $stok <= $ambangnya ? ['warning', 'Menipis'] : ['success', 'Aman'];
};

// data varian untuk modal sunting; esc() yang mengemas atributnya
$barang = static fn (array $b): string => (string) esc(json_encode([
    'id'         => (int) $b['id_stok'],
    'kode'       => $b['kode'],
    'ukuran'     => (string) ($b['ukuran'] ?? ''),
    'harga'      => (int) $b['harga'],
    'stok'       => (int) $b['stok'],
    'keterangan' => (string) ($b['keterangan'] ?? ''),
    'juragan'    => $b['nama_juragan'],
]));

// huruf pertama nama barang, dipakai waktu foto produk belum ada
$inisial = static fn (string $kode): string => mb_strtoupper(mb_substr(trim($kode), 0, 2));
?>
<?= $this->extend('template/main') ?>

<?= $this->section('content') ?>
<?= $this->include('admin/navbar') ?>

<div class="container-xxl">
    <h1 class="h3 mt-5">Produk &amp; Stok</h1>
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb p-0">
            <li class="breadcrumb-item">
                <?= anchor('', 'Dasbor') ?>
            </li>
            <li class="breadcrumb-item active" aria-current="page">Produk</li>
        </ol>
    </nav>

    <div class="row gx-2 gy-3 mb-4">
        <?php
        $ringkasan = [
            ['Varian tercatat', (string) $ringkas['varian'], 'fa-shapes'],
            ['Total unit', (string) $ringkas['unit'], 'fa-boxes-stacked'],
            ['Nilai stok', 'Rp ' . formatRibuan($ringkas['nilai']), 'fa-coins'],
            ['Menipis', (string) $ringkas['menipis'], 'fa-triangle-exclamation'],
            ['Habis', (string) $ringkas['habis'], 'fa-circle-xmark'],
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

    <div class="row gx-3">
        <div class="col-12 mb-4">
            <?php if ($sukses !== null) { ?>
                <div class="alert alert-success py-2"><?= esc($sukses) ?></div>
            <?php } ?>
            <?php if ($gagal !== null) { ?>
                <div class="alert alert-danger py-2"><?= esc($gagal) ?></div>
            <?php } ?>

            <div class="card">
                <div class="card-body">
                    <div class="d-flex flex-wrap gap-2 align-items-end mb-3">
                        <?= form_open('admin/produk', ['method' => 'get', 'class' => 'row g-2 align-items-end mb-0 flex-grow-1']) ?>
                        <div class="col-6 col-md-3">
                            <?= form_label('Juragan', 'juragan', ['class' => 'form-label small']) ?>
                            <?= form_dropdown([
                                'class'    => 'form-select form-select-sm',
                                'name'     => 'juragan',
                                'options'  => [0 => 'Semua juragan'] + $juragans,
                                'selected' => $juragan,
                            ]) ?>
                        </div>
                        <div class="col-6 col-md-3">
                            <?= form_label('Keadaan', 'keadaan', ['class' => 'form-label small']) ?>
                            <?= form_dropdown([
                                'class'    => 'form-select form-select-sm',
                                'name'     => 'keadaan',
                                'options'  => [
                                    ''        => 'Semua',
                                    'ada'     => 'Stok aman',
                                    'menipis' => 'Menipis (≤ ' . $ambangnya . ')',
                                    'habis'   => 'Habis',
                                ],
                                'selected' => $keadaan,
                            ]) ?>
                        </div>
                        <div class="col-12 col-md-4">
                            <?= form_label('Cari', 'cari', ['class' => 'form-label small']) ?>
                            <?= form_input([
                                'class'       => 'form-control form-control-sm',
                                'name'        => 'cari',
                                'placeholder' => 'nama barang atau keterangan',
                                'value'       => $cari,
                            ]) ?>
                        </div>
                        <div class="col-auto">
                            <button class="btn btn-sm btn-dark" type="submit"><i class="fal fa-search"></i> Cari</button>
                        </div>
                        <?= form_close() ?>
                        <button class="btn btn-sm btn-primary flex-shrink-0" type="button" data-bs-toggle="modal" data-bs-target="#modalTambahBarang">
                            <i class="fal fa-plus"></i> Tambah Barang
                        </button>
                    </div>

                    <?php if ($katalog === []) { ?>
                        <div class="text-center py-5 text-muted">
                            <i class="fal fa-box-open fa-3x mb-2 d-block"></i>
                            Belum ada barang yang dipantau<?= $cari !== '' ? ' untuk pencarian "' . esc($cari) . '"' : '' ?>.
                            Klik tombol <strong>Tambah Barang</strong> di atas untuk menambah varian.
                        </div>
                    <?php } else { ?>
                        <div class="grid grid-cols-[repeat(auto-fill,minmax(15rem,1fr))] gap-3">
                            <?php foreach ($katalog as $i => $p) {
                                $varian = $p['varian'];
                                $jumlah   = count($varian);
                                [, $teks] = $keadaan_baris((int) $p['total_stok']);
                                $rentang = $p['harga_akhir'] > $p['harga_mulai'];

                                // foto kartu = gabungan foto semua variannya, jadi
                                // satu unggahan terlihat di semua ukuran
                                $fotonya_kartu = $fotonya[$i] ?? [];
                                $sampul        = $fotonya_kartu[0] ?? null;

                                // satu keterangan saja yang perlu tampil di kartu
                                $keterangan = '';

                                foreach ($varian as $b) {
                                    if (trim((string) ($b['keterangan'] ?? '')) !== '') {
                                        $keterangan = (string) $b['keterangan'];

                                        break;
                                    }
                                }

                                // tanpa keterangan, nama tokonya yang mengisi baris itu
                                if ($keterangan === '' && $juragan > 0) {
                                    $keterangan = (string) $p['nama_juragan'];
                                }
                                ?>
                                <div class="card produk-kartu h-100">
                                    <div class="rounded-kartu overflow-hidden">
                                        <div class="produk-sampul position-relative d-flex align-items-center justify-content-center">
                                            <?php if ($sampul !== null) { ?>
                                                <img src="<?= esc(\App\Models\ProdukFotoModel::alamat((int) $sampul['id_foto'], 480)) ?>"
                                                    alt="Foto <?= esc($p['kode']) ?>" loading="lazy">
                                            <?php } else { ?>
                                                <span class="produk-inisial"><?= esc($inisial($p['kode'])) ?></span>
                                            <?php } ?>
                                            <?php if ($juragan === 0) { ?>
                                                <span class="badge position-absolute top-0 start-0 m-2 rounded-pill bg-white text-ink-700 border border-ink-200 text-uppercase">
                                                    <?= esc($p['nama_juragan']) ?>
                                                </span>
                                            <?php } ?>
                                            <button type="button" class="produk-foto-btn position-absolute top-0 end-0 m-2 rounded-circle d-flex align-items-center justify-content-center"
                                                title="Kelola foto produk ini" aria-label="Kelola foto produk"
                                                data-stok="<?= (int) $varian[0]['id_stok'] ?>"
                                                data-nama="<?= esc($p['kode'], 'attr') ?>"
                                                data-bs-toggle="modal" data-bs-target="#modalFotoProduk">
                                                <i class="fal fa-camera"></i>
                                                <?php if ($fotonya_kartu !== []) { ?>
                                                    <span class="small fw-semibold"><?= count($fotonya_kartu) ?></span>
                                                <?php } ?>
                                            </button>
                                        </div>
                                    </div>

                                    <div class="card-body p-3 d-flex flex-column">
                                        <div class="d-flex align-items-baseline justify-content-between gap-2">
                                            <h2 class="h6 mb-0 text-truncate" title="<?= esc($p['kode']) ?>"><?= esc($p['kode']) ?></h2>
                                            <div class="text-nowrap small">
                                                <?php if ($rentang) { ?>
                                                    <span class="text-muted">mulai</span>
                                                <?php } ?>
                                                <span class="fw-bold text-brand-600">Rp <?= formatRibuan($p['harga_mulai']) ?></span>
                                            </div>
                                        </div>

                                        <div class="small text-muted text-truncate mb-2" title="<?= esc($keterangan) ?>">
                                            <i class="fal fa-tag me-1"></i><?= $keterangan === '' ? '&mdash;' : esc($keterangan) ?>
                                        </div>

                                        <div class="varian-daftar rounded-3 mb-2">
                                            <?php foreach ($varian as $b) {
                                                $ukuran_baris = (string) ($b['ukuran'] ?? '');
                                                [$vw, $label_keadaan] = $keadaan_baris((int) $b['stok']);
                                                $warna_teks = $vw === 'warning' ? 'text-dark' : 'text-white';
                                                ?>
                                                <button type="button" class="varian-baris d-flex align-items-center justify-content-between gap-2 w-100"
                                                    title="<?= esc($ukuran_baris === '' ? 'Tanpa ukuran' : 'Ukuran ' . $ukuran_baris) ?> · klik untuk sunting"
                                                    data-barang="<?= $barang($b) ?>"
                                                    data-bs-toggle="modal" data-bs-target="#modalSuntingBarang">
                                                    <span class="varian-ukuran badge rounded-pill text-uppercase"><?= $ukuran_baris === '' ? '&mdash;' : esc($ukuran_baris) ?></span>
                                                    <span class="badge rounded-pill bg-<?= $vw ?> <?= $warna_teks ?>"><?= esc($label_keadaan) ?> · stok <?= (int) $b['stok'] ?></span>
                                                </button>
                                            <?php } ?>
                                        </div>

                                        <div class="d-flex align-items-center justify-content-between gap-2 mt-auto pt-2 border-t border-ink-100">
                                            <div class="small text-muted text-nowrap" title="<?= esc($teks) ?> · <?= $jumlah ?> varian">
                                                <?= $jumlah ?> varian
                                                <span class="mx-1">·</span>
                                                <?= (int) $p['total_stok'] ?> stok
                                                <span class="mx-1">·</span>
                                                <?= (int) $p['terjual'] ?> terjual
                                            </div>
                                            <div class="d-flex align-items gap-2">
                                                <button type="button" class="btn btn-sm btn-link text-decoration-none p-0 small text-nowrap text-danger"
                                                    title="Hapus <?= esc($p['kode'], 'attr') ?> beserta semua variannya dari daftar stok"
                                                    form="formHapusKartu"
                                                    data-hapus-kartu="<?= (string) esc(json_encode(['kode' => $p['kode'], 'juragan' => (int) $p['juragan_id'], 'varian' => $jumlah])) ?>">
                                                    <i class="fal fa-trash"></i> Hapus
                                                </button>
                                                <button type="button" class="btn btn-sm btn-link text-decoration-none p-0 small text-nowrap"
                                                    data-tambah="<?= (string) esc(json_encode(['kode' => $p['kode'], 'juragan' => $p['juragan_id']])) ?>"
                                                    data-bs-toggle="modal" data-bs-target="#modalTambahBarang">
                                                    <i class="fal fa-plus"></i> Varian
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php } ?>
                        </div>

                        <div class="mt-3">
                            <?= $pager->makeLinks($page, $limit, $total, 'front_full') ?>
                        </div>
                    <?php } ?>
                </div>
            </div>
        </div>
    </div>

    <datalist id="daftarKode">
        <?php foreach ($kode as $k) { ?>
            <option value="<?= esc($k['kode']) ?>"></option>
        <?php } ?>
    </datalist>
    <datalist id="daftarUkuran">
        <?php foreach ($ukuran as $u) { ?>
            <option value="<?= esc($u['kode']) ?>"><?= esc($u['kelompok']) ?></option>
        <?php } ?>
    </datalist>
</div>
<?= $this->endSection() ?>

<?= $this->section('modal') ?>
<div class="modal fade" id="modalTambahBarang" tabindex="-1" aria-labelledby="modalTambahBarangLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <?= form_open('admin/produk/save', ['class' => 'modal-content', 'enctype' => 'multipart/form-data'], [
            'kembali_juragan' => (string) $juragan,
            'kembali_keadaan' => $keadaan,
            'kembali_cari'    => $cari,
        ]) ?>
        <div class="modal-header">
            <h5 class="modal-title" id="modalTambahBarangLabel">Tambah Barang</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
            <div class="mb-3">
                <?= form_label('Juragan', 'juragan_id', ['class' => 'form-label']) ?>
                <?= form_dropdown([
                    'class'    => 'form-select' . ($err_tambah('juragan_id') ? ' is-invalid' : ''),
                    'name'     => 'juragan_id',
                    'options'  => [0 => 'Pilih Juragan'] + $juragans,
                    'required' => '',
                    'selected' => (int) old('juragan_id', $juragan),
                ]) ?>
                <?php if ($err_tambah('juragan_id')) { ?>
                    <div class="invalid-feedback d-block"><?= esc($err_tambah('juragan_id')) ?></div>
                <?php } ?>
            </div>
            <div class="row gx-2">
                <div class="col-7 mb-3">
                    <?= form_label('Nama Barang', 'kode', ['class' => 'form-label']) ?>
                    <?= form_input([
                        'class'       => 'form-control' . ($err_tambah('kode') ? ' is-invalid' : ''),
                        'name'        => 'kode',
                        'list'        => 'daftarKode',
                        'placeholder' => 'JAS PREMIUM',
                        'required'    => '',
                        'max_length'  => 20,
                        'value'       => set_value('kode'),
                    ]) ?>
                    <?php if ($err_tambah('kode')) { ?>
                        <div class="invalid-feedback d-block"><?= esc($err_tambah('kode')) ?></div>
                    <?php } ?>
                    <div class="form-text">Boleh nama baru; nama yang sudah pernah dipakai muncul sebagai saran.</div>
                </div>
                <div class="col-5 mb-3">
                    <?= form_label('Ukuran', 'ukuran', ['class' => 'form-label']) ?>
                    <?= form_input([
                        'class'       => 'form-control' . ($err_tambah('ukuran') ? ' is-invalid' : ''),
                        'name'        => 'ukuran',
                        'list'        => 'daftarUkuran',
                        'placeholder' => 'L / 32 / custom',
                        'max_length'  => 6,
                        'value'       => set_value('ukuran'),
                    ]) ?>
                    <?php if ($err_tambah('ukuran')) { ?>
                        <div class="invalid-feedback d-block"><?= esc($err_tambah('ukuran')) ?></div>
                    <?php } ?>
                </div>
            </div>
            <div class="row gx-2">
                <div class="col-6 mb-3">
                    <?= form_label('Harga', 'harga', ['class' => 'form-label']) ?>
                    <?= form_input([
                        'class'       => 'form-control' . ($err_tambah('harga') ? ' is-invalid' : ''),
                        'type'        => 'number',
                        'name'        => 'harga',
                        'placeholder' => '250000',
                        'min'         => 0,
                        'required'    => '',
                        'value'       => set_value('harga'),
                    ]) ?>
                    <?php if ($err_tambah('harga')) { ?>
                        <div class="invalid-feedback d-block"><?= esc($err_tambah('harga')) ?></div>
                    <?php } ?>
                </div>
                <div class="col-6 mb-3">
                    <?= form_label('Stok', 'stok', ['class' => 'form-label']) ?>
                    <?= form_input([
                        'class'       => 'form-control' . ($err_tambah('stok') ? ' is-invalid' : ''),
                        'type'        => 'number',
                        'name'        => 'stok',
                        'placeholder' => '10',
                        'required'    => '',
                        'value'       => set_value('stok', '0'),
                    ]) ?>
                    <?php if ($err_tambah('stok')) { ?>
                        <div class="invalid-feedback d-block"><?= esc($err_tambah('stok')) ?></div>
                    <?php } ?>
                </div>
            </div>
            <div class="mb-3">
                <?= form_label('Keterangan (opsional)', 'keterangan', ['class' => 'form-label']) ?>
                <?= form_input([
                    'class'       => 'form-control',
                    'name'        => 'keterangan',
                    'placeholder' => 'mis. warna navy, bahan wool',
                    'max_length'  => 120,
                    'value'       => set_value('keterangan'),
                ]) ?>
            </div>
            <div class="mb-0">
                <?= form_label('Foto produk (opsional)', 'foto', ['class' => 'form-label']) ?>
                <input type="file" class="form-control" id="foto" name="foto[]" multiple accept="image/jpeg,image/png,image/webp">
                <div class="form-text">JPEG, PNG, atau WebP; maksimum 3 MB per foto dan 12 foto per barang. Foto menempel ke varian ini dan tampil di semua ukuran sekartunya.</div>
            </div>
        </div>
        <div class="modal-footer flex-nowrap p-0">
            <button type="button" data-bs-dismiss="modal" class="btn btn-lg btn-link fs-6 text-decoration-none col m-0 rounded-0">Batal</button>
            <button type="submit" class="btn btn-lg btn-link fs-6 text-decoration-none col m-0 rounded-0 border-start"><i class="fal fa-save"></i> Tambahkan</button>
        </div>
        <?= form_close() ?>
    </div>
</div>

<div class="modal fade" id="modalSuntingBarang" tabindex="-1" aria-labelledby="modalSuntingBarangLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <?= form_open('admin/produk/update', ['class' => 'modal-content'], [
            'id_stok'         => set_value('id_stok'),
            'kembali_juragan' => (string) $juragan,
            'kembali_keadaan' => $keadaan,
            'kembali_cari'    => $cari,
        ]) ?>
        <div class="modal-header">
            <h5 class="modal-title" id="modalSuntingBarangLabel">Sunting Barang</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
            <div class="mb-3">
                <?= form_label('Juragan', 'sunting_juragan', ['class' => 'form-label']) ?>
                <?= form_input(['class' => 'form-control-plaintext', 'name' => 'sunting_juragan', 'id' => 'sunting_juragan', 'readonly' => '']) ?>
            </div>
            <div class="row gx-2">
                <div class="col-7 mb-3">
                    <?= form_label('Nama Barang', 'sunting_kode', ['class' => 'form-label']) ?>
                    <?= form_input(['class' => 'form-control', 'name' => 'kode', 'id' => 'sunting_kode', 'list' => 'daftarKode', 'required' => '', 'max_length' => 20]) ?>
                    <?php if ($err_sunting('kode')) { ?>
                        <div class="text-danger small mt-1"><?= esc($err_sunting('kode')) ?></div>
                    <?php } ?>
                </div>
                <div class="col-5 mb-3">
                    <?= form_label('Ukuran', 'sunting_ukuran', ['class' => 'form-label']) ?>
                    <?= form_input(['class' => 'form-control', 'name' => 'ukuran', 'id' => 'sunting_ukuran', 'list' => 'daftarUkuran', 'max_length' => 6]) ?>
                </div>
            </div>
            <div class="row gx-2">
                <div class="col-6 mb-3">
                    <?= form_label('Harga', 'sunting_harga', ['class' => 'form-label']) ?>
                    <?= form_input(['class' => 'form-control', 'type' => 'number', 'name' => 'harga', 'id' => 'sunting_harga', 'min' => 0, 'required' => '']) ?>
                </div>
                <div class="col-6 mb-3">
                    <?= form_label('Stok', 'sunting_stok', ['class' => 'form-label']) ?>
                    <?= form_input(['class' => 'form-control', 'type' => 'number', 'name' => 'stok', 'id' => 'sunting_stok', 'required' => '']) ?>
                </div>
            </div>
            <div class="mb-0">
                <?= form_label('Keterangan', 'sunting_keterangan', ['class' => 'form-label']) ?>
                <?= form_input(['class' => 'form-control', 'name' => 'keterangan', 'id' => 'sunting_keterangan', 'max_length' => 120]) ?>
            </div>
        </div>
        <div class="modal-footer flex-nowrap p-0">
            <button type="button" data-bs-dismiss="modal" class="btn btn-lg btn-link fs-6 text-decoration-none col m-0 rounded-0">Batal</button>
            <button type="submit" form="formHapusBarang" class="btn btn-lg btn-link fs-6 text-decoration-none col m-0 rounded-0 border-start text-danger"><i class="fal fa-trash"></i> Hapus</button>
            <button type="submit" class="btn btn-lg btn-link fs-6 text-decoration-none col m-0 rounded-0 border-start">Simpan</button>
        </div>
        <?= form_close() ?>
    </div>
</div>

<?php
// Dipakai tombol Hapus di modal sunting. Form-nya berdiri sendiri di luar modal
// supaya tidak ada form yang bersarang, dan ditambatkan lewat atribut `form`.
echo form_open('admin/produk/hapus', ['id' => 'formHapusBarang', 'class' => 'd-none'])
    . form_hidden('id_stok')
    . form_hidden('kembali_juragan', (string) $juragan)
    . form_hidden('kembali_keadaan', $keadaan)
    . form_hidden('kembali_cari', $cari)
    . form_close();

// Tombol hapus per kartu mengirim form ini; kode dan toko tujuan diisi JS
// sesaat sebelum kirim, supaya kartu tidak perlu menyimpan satu form sendiri
echo form_open('admin/produk/hapus-kartu', ['id' => 'formHapusKartu', 'class' => 'd-none'])
    . form_hidden('kode')
    . form_hidden('juragan_id')
    . form_hidden('kembali_juragan', (string) $juragan)
    . form_hidden('kembali_keadaan', $keadaan)
    . form_hidden('kembali_cari', $cari)
    . form_close();
?>

<?php
// Panel fotonya tidak ikut ter-render untuk tiap kartu: 20 kartu x daftar foto
// per halaman berat, dan yang dibuka cuma satu. Isinya dimuat saat tombolnya
// ditekan (lihat js di bawah), memakai panel yang sama dengan form orderan.
?>
<div class="modal fade" id="modalFotoProduk" tabindex="-1" aria-labelledby="modalFotoProdukLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalFotoProdukLabel">Foto Produk</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body foto-muat">
                <div class="text-center text-muted py-4"><i class="fal fa-spinner fa-spin me-2"></i>Fotonya dimuat...</div>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('js') ?>
<script defer src="https://kit.fontawesome.com/22a9d93fa2.js" data-auto-replace-svg="nest" crossorigin="anonymous"></script>
<?= $this->include('template/common_js') ?>
<script defer src="<?= base_url('assets/js/foto-form.js') ?>?v=<?= (int) @filemtime(FCPATH . 'assets/js/foto-form.js') ?>"></script>
<?php
$showModalSunting = $sunting_err ? 'true' : 'false';
$showModalTambah  = $tambah_err ? 'true' : 'false';
$alamat_foto      = site_url('api/produk-foto/kelola');
$isiUlang         = $sunting_err ? json_encode([
    'id'         => (int) set_value('id_stok'),
    'kode'       => (string) set_value('kode'),
    'ukuran'     => (string) set_value('ukuran'),
    'harga'      => (string) set_value('harga'),
    'stok'       => (string) set_value('stok'),
    'keterangan' => (string) set_value('keterangan'),
    'juragan'    => '',
], JSON_THROW_ON_ERROR) : 'null';

$js = <<< JS
    $(function() {
    	'use strict';

        const suntingErr = {$showModalSunting};
        const tambahErr = {$showModalTambah};
        const alamatFoto = '{$alamat_foto}';
        let suntingLama = {$isiUlang};
        let namaHapus = '';
        const modalSunting = document.getElementById('modalSuntingBarang');
        const modalTambah = document.getElementById('modalTambahBarang');
        const formHapus = document.getElementById('formHapusBarang');
        const formHapusKartu = document.getElementById('formHapusKartu');
        const fields = {
            id_stok: modalSunting.querySelector('[name="id_stok"]'),
            kode: modalSunting.querySelector('[name="kode"]'),
            ukuran: modalSunting.querySelector('[name="ukuran"]'),
            harga: modalSunting.querySelector('[name="harga"]'),
            stok: modalSunting.querySelector('[name="stok"]'),
            keterangan: modalSunting.querySelector('[name="keterangan"]'),
            juragan: modalSunting.querySelector('[name="sunting_juragan"]'),
        };

        function isiForm(data) {
            fields.id_stok.value = data.id;
            fields.kode.value = data.kode;
            fields.ukuran.value = data.ukuran;
            fields.harga.value = data.harga;
            fields.stok.value = data.stok;
            fields.keterangan.value = data.keterangan;
            fields.juragan.value = data.juragan;

            // tombol Hapus di modal ini mengirim form sendiri
            formHapus.querySelector('[name="id_stok"]').value = data.id;
            namaHapus = (data.kode + ' ' + data.ukuran).trim();
        }

        modalSunting.addEventListener('show.bs.modal', event => {
            const button = event.relatedTarget;

            namaHapus = '';

            // sunting yang gagal: nilai yang tadi diketik dipulihkan, bukan nilai lama
            if (suntingLama) {
                isiForm(suntingLama);
                suntingLama = null;

                return;
            }

            if (!button || !button.hasAttribute('data-barang')) {
                return;
            }

            isiForm(JSON.parse(button.getAttribute('data-barang')));
        });

        // hapus diformalkan sekali per klik: kartu tidak lagi menyimpan satu form
        // per varian, jadi nama variannya diambil dari modal yang sedang terbuka
        document.querySelector('[form="formHapusBarang"]').addEventListener('click', event => {
            if (namaHapus === '' || ! confirm('Hapus ' + namaHapus + ' dari daftar stok?')) {
                event.preventDefault();
            }
        });

        // hapus per kartu: satu produk pergi bersama semua variannya, jadi
        // konfirmasinya menyebut jumlah varian supaya tidak terasa kejutan
        document.querySelectorAll('[form="formHapusKartu"]').forEach(button => {
            button.addEventListener('click', event => {
                const data = JSON.parse(button.getAttribute('data-hapus-kartu'));

                if (! confirm('Hapus ' + data.kode + ' (' + data.varian + ' varian) dari daftar stok? Transaksi lama tetap utuh.')) {
                    event.preventDefault();

                    return;
                }

                formHapusKartu.querySelector('[name="kode"]').value = data.kode;
                formHapusKartu.querySelector('[name="juragan_id"]').value = data.juragan;
            });
        });

        // "+ Varian" pada kartu membawa nama barang dan toko asalnya
        modalTambah.addEventListener('show.bs.modal', event => {
            const button = event.relatedTarget;

            if (!button || !button.hasAttribute('data-tambah')) {
                return;
            }

            const bawaan = JSON.parse(button.getAttribute('data-tambah'));
            const juragan = modalTambah.querySelector('[name="juragan_id"]');

            modalTambah.querySelector('[name="kode"]').value = bawaan.kode;

            for (let i = 0; i < juragan.options.length; i++) {
                if (juragan.options[i].value === String(bawaan.juragan)) {
                    juragan.value = juragan.options[i].value;

                    break;
                }
            }
        });

        if (suntingErr) {
            bootstrap.Modal.getOrCreateInstance(modalSunting).show();
        }

        // gagal menambah: modal dibuka lagi supaya isian tadi tidak perlu diketik ulang
        if (tambahErr) {
            bootstrap.Modal.getOrCreateInstance(modalTambah).show();
        }

        // Panel foto produk dimuat saat tombolnya ditekan: satu halaman bisa
        // berisi 20 kartu dan hampir semuanya tidak dibuka, jadi fotonya tidak
        // perlu ditarik semua. Isi lama dibuang tiap modal ditutup supaya kartu
        // berikutnya tidak sempat menampilkan foto kartu sebelumnya.
        const modalFoto = document.getElementById('modalFotoProduk');
        const muatFoto = modalFoto.querySelector('.foto-muat');
        const judulFoto = modalFoto.querySelector('.modal-title');

        modalFoto.addEventListener('show.bs.modal', event => {
            const tombol = event.relatedTarget;

            if (!tombol || !tombol.hasAttribute('data-stok')) {
                return;
            }

            judulFoto.textContent = 'Foto ' + tombol.getAttribute('data-nama');
            muatFoto.innerHTML = '<div class="text-center text-muted py-4"><i class="fal fa-spinner fa-spin me-2"></i>Fotonya dimuat...</div>';

            const id = tombol.getAttribute('data-stok');

            $.get(alamatFoto + '/' + id)
                .done(html => {
                    // modal bisa sempat ditutup sebelum jawaban datang
                    if (document.getElementById('modalFotoProduk') && modalFoto.classList.contains('show')) {
                        muatFoto.innerHTML = html;
                    }
                })
                .fail(() => {
                    muatFoto.innerHTML = '<div class="alert alert-danger py-2 mb-0">Foto produk gagal dimuat, coba lagi ya.</div>';
                });
        });

        modalFoto.addEventListener('hidden.bs.modal', () => {
            muatFoto.innerHTML = '';
        });
    });
    JS;

echo '<script>' . (new Tholu\Packer\Packer($js, 'Normal', true, false, true))->pack() . '</script>';
?>
<?= $this->endSection() ?>
