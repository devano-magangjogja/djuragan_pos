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

// badge warna untuk kolom stok
$keadaan_baris = static function (int $stok) use ($ambangnya): array {
    if ($stok <= 0) {
        return ['danger', 'Habis'];
    }

    return $stok <= $ambangnya ? ['warning', 'Menipis'] : ['success', 'Aman'];
};
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
        $kartu = [
            ['Varian tercatat', (string) $ringkas['varian'], 'fa-shapes'],
            ['Total unit', (string) $ringkas['unit'], 'fa-boxes-stacked'],
            ['Nilai stok', 'Rp ' . formatRibuan($ringkas['nilai']), 'fa-coins'],
            ['Menipis', (string) $ringkas['menipis'], 'fa-triangle-exclamation'],
            ['Habis', (string) $ringkas['habis'], 'fa-circle-xmark'],
        ];

        foreach ($kartu as [$label, $nilai, $ikon]) { ?>
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
                    <?= form_open('admin/produk', ['method' => 'get', 'class' => 'row g-2 align-items-end mb-3']) ?>
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
                    <div class="col-md-auto ms-md-auto">
                        <button class="btn btn-sm btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#modalTambahBarang">
                            <i class="fal fa-plus"></i> Tambah Barang
                        </button>
                    </div>
                    <?= form_close() ?>

                    <?php if ($stok === []) { ?>
                        <div class="text-center py-5 text-muted">
                            <i class="fal fa-box-open fa-3x mb-2 d-block"></i>
                            Belum ada barang yang dipantau<?= $cari !== '' ? ' untuk pencarian "' . esc($cari) . '"' : '' ?>.
                            Klik tombol <strong>Tambah Barang</strong> di atas untuk menambah varian.
                        </div>
                    <?php } else { ?>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th scope="col">Produk</th>
                                        <th scope="col">Ukuran</th>
                                        <?php if ($juragan === 0) { ?>
                                            <th scope="col">Juragan</th>
                                        <?php } ?>
                                        <th scope="col" class="text-end">Stok</th>
                                        <th scope="col" class="text-end">Harga</th>
                                        <th scope="col" class="text-end">Terjual</th>
                                        <th scope="col" class="text-end">&nbsp;</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    foreach ($stok as $b) {
                                        [$warna, $teks] = $keadaan_baris((int) $b['stok']);
                                        $ukuran_baris = (string) ($b['ukuran'] ?? '');
                                        ?>
                                        <tr>
                                            <td>
                                                <div class="fw-semibold"><?= esc($b['kode']) ?></div>
                                                <?php if (! empty($b['keterangan'])) { ?>
                                                    <div class="text-muted small"><?= esc($b['keterangan']) ?></div>
                                                <?php } ?>
                                            </td>
                                            <td><?= $ukuran_baris === '' ? '&mdash;' : esc($ukuran_baris) ?></td>
                                            <?php if ($juragan === 0) { ?>
                                                <td class="small"><?= esc($b['nama_juragan']) ?></td>
                                            <?php } ?>
                                            <td class="text-end" title="<?= $teks ?>">
                                                <span class="badge rounded-pill bg-<?= $warna ?>"><?= (int) $b['stok'] ?></span>
                                            </td>
                                            <td class="text-end">Rp <?= formatRibuan((int) $b['harga']) ?></td>
                                            <td class="text-end"><?= (int) $b['terjual'] ?></td>
                                            <td class="text-end text-nowrap">
                                                <button type="button" class="btn btn-sm btn-outline-secondary" aria-label="Sunting barang"
                                                    data-barang="<?= esc(json_encode([
                                                        'id'         => (int) $b['id_stok'],
                                                        'kode'       => $b['kode'],
                                                        'ukuran'     => $ukuran_baris,
                                                        'harga'      => (int) $b['harga'],
                                                        'stok'       => (int) $b['stok'],
                                                        'keterangan' => (string) ($b['keterangan'] ?? ''),
                                                        'juragan'    => $b['nama_juragan'],
                                                    ])) ?>"
                                                    data-bs-toggle="modal" data-bs-target="#modalSuntingBarang">
                                                    <i class="fal fa-pencil"></i>
                                                </button>
                                                <?= form_open('admin/produk/hapus', ['class' => 'd-inline']) ?>
                                                <?= form_hidden('id_stok', (string) $b['id_stok']) ?>
                                                <?= form_hidden('kembali_juragan', (string) $juragan) ?>
                                                <?= form_hidden('kembali_keadaan', $keadaan) ?>
                                                <?= form_hidden('kembali_cari', $cari) ?>
                                                <button type="submit" class="btn btn-sm btn-outline-danger" aria-label="Hapus barang"
                                                    onclick="return confirm('Hapus <?= esc($b['kode']) ?> <?= esc($ukuran_baris) ?> dari daftar stok?')">
                                                    <i class="fal fa-trash"></i>
                                                </button>
                                                <?= form_close() ?>
                                            </td>
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
        <?= form_open('admin/produk/save', ['class' => 'modal-content'], [
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
            <div class="mb-0">
                <?= form_label('Keterangan (opsional)', 'keterangan', ['class' => 'form-label']) ?>
                <?= form_input([
                    'class'       => 'form-control',
                    'name'        => 'keterangan',
                    'placeholder' => 'mis. warna navy, bahan wool',
                    'max_length'  => 120,
                    'value'       => set_value('keterangan'),
                ]) ?>
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
            <button type="submit" class="btn btn-lg btn-link fs-6 text-decoration-none col m-0 rounded-0 border-start">Simpan</button>
        </div>
        <?= form_close() ?>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('js') ?>
<script defer src="https://kit.fontawesome.com/22a9d93fa2.js" data-auto-replace-svg="nest" crossorigin="anonymous"></script>
<?= $this->include('template/common_js') ?>
<?php
$showModalSunting = $sunting_err ? 'true' : 'false';
$showModalTambah  = $tambah_err ? 'true' : 'false';
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
        let suntingLama = {$isiUlang};
        const modalSunting = document.getElementById('modalSuntingBarang');
        const modalTambah = document.getElementById('modalTambahBarang');
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
        }

        modalSunting.addEventListener('show.bs.modal', event => {
            const button = event.relatedTarget;

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

        if (suntingErr) {
            bootstrap.Modal.getOrCreateInstance(modalSunting).show();
        }

        // gagal menambah: modal dibuka lagi supaya isian tadi tidak perlu diketik ulang
        if (tambahErr) {
            bootstrap.Modal.getOrCreateInstance(modalTambah).show();
        }
    });
    JS;

echo '<script>' . (new Tholu\Packer\Packer($js, 'Normal', true, false, true))->pack() . '</script>';
?>
<?= $this->endSection() ?>
