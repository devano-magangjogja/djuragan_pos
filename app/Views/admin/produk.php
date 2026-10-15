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
    'juragan_id' => (int) $b['juragan_id'],
]));

// huruf pertama nama barang, dipakai waktu foto produk belum ada
$inisial = static fn (string $kode): string => mb_strtoupper(mb_substr(trim($kode), 0, 2));
?>
<?= $this->extend('template/main') ?>

<?= $this->section('content') ?>
<?= $this->include('admin/navbar') ?>

<div class="container-xxl">
    <h1 class="h3 mt-5">Produk &amp; Stok</h1>

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
                                $jumlah = count($varian);
                                [$vw_kartu, $teks] = $keadaan_baris((int) $p['total_stok']);
                                $rentang = $p['harga_akhir'] > $p['harga_mulai'];

                                // foto kartu = gabungan foto semua variannya, jadi
                                // satu unggahan terlihat di semua ukuran
                                $fotonya_kartu = $fotonya[$i] ?? [];
                                $sampul        = $fotonya_kartu[0] ?? null;

                                // satu keterangan saja yang perlu tampil di kartu;
                                // tanpa keterangan, nama tokonya yang mengisi baris itu
                                $keterangan = '';

                                foreach ($varian as $b) {
                                    if (trim((string) ($b['keterangan'] ?? '')) !== '') {
                                        $keterangan = (string) $b['keterangan'];

                                        break;
                                    }
                                }

                                if ($keterangan === '') {
                                    $keterangan = (string) $p['nama_juragan'];
                                }
                                ?>
                                <div class="card produk-kartu h-100" tabindex="0" role="button" aria-label="Lihat detail <?= esc($p['kode'], 'attr') ?>">
                                    <div class="rounded-kartu overflow-hidden">
                                        <div class="produk-sampul position-relative d-flex align-items-center justify-content-center">
                                            <?php if ($sampul !== null) { ?>
                                                <img src="<?= esc(\App\Models\ProdukFotoModel::alamat((int) $sampul['id_foto'], 480)) ?>"
                                                    alt="Foto <?= esc($p['kode']) ?>" loading="lazy">
                                            <?php } else { ?>
                                                <span class="produk-inisial"><?= esc($inisial($p['kode'])) ?></span>
                                            <?php } ?>
                                            <?php if ($juragan === 0) { ?>
                                                <span class="badge position-absolute top-0 start-0 m-2 rounded-pill bg-white text-ink-700 border border-ink-200">
                                                    <?= esc($p['nama_juragan']) ?>
                                                </span>
                                            <?php } ?>
                                            <button type="button" class="produk-foto-btn position-absolute top-0 end-0 m-2 rounded-circle d-flex align-items-center justify-content-center"
                                                title="Kelola foto produk ini" aria-label="Kelola foto produk"
                                                data-stok="<?= (int) $varian[0]['id_stok'] ?>"
                                                data-nama="<?= esc($p['kode'], 'attr') ?>"
                                                data-bs-toggle="modal" data-bs-target="#modalFotoProduk">
                                                <i class="fal fa-camera"></i>
                                            </button>

                                            <?php /* tombol Detail baru tampak saat kartu di-hover; seluruh kartu memang bisa diklik */ ?>
                                            <button type="button" class="produk-detail-btn position-absolute bottom-0 end-0 m-2 rounded-circle d-flex align-items-center justify-content-center"
                                                title="Lihat detail <?= esc($p['kode'], 'attr') ?>" aria-label="Lihat detail produk">
                                                <i class="fal fa-expand"></i>
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

                                        <div class="small text-muted text-truncate mb-2"
                                            title="<?= esc($keterangan) ?> · <?= esc($p['nama_juragan']) ?>">
                                            <i class="fal fa-tag me-1"></i><?= esc($keterangan) ?>
                                        </div>

                                        <div class="varian-daftar mb-2">
                                            <?php foreach ($varian as $b) {
                                                $ukuran_baris = (string) ($b['ukuran'] ?? '');
                                                [$vw, $label_keadaan] = $keadaan_baris((int) $b['stok']);
                                                ?>
                                                <button type="button" class="varian-chip"
                                                    title="<?= esc($ukuran_baris === '' ? 'Tanpa ukuran' : 'Ukuran ' . $ukuran_baris) ?> · <?= esc($label_keadaan) ?> · klik untuk sunting"
                                                    data-barang="<?= $barang($b) ?>"
                                                    data-bs-toggle="modal" data-bs-target="#modalSuntingBarang">
                                                    <span class="varian-dot<?= $vw === 'success' ? '' : ' ' . $vw ?>"></span>
                                                    <span class="text-truncate"><?= esc($ukuran_baris === '' ? 'Tanpa ukuran' : $ukuran_baris) ?></span>
                                                    <span class="varian-stok"><?= (int) $b['stok'] ?></span>
                                                </button>
                                            <?php } ?>
                                        </div>

                                        <div class="d-flex produk-stat mt-auto mb-2 pt-2">
                                            <div class="flex-fill text-center">
                                                <div class="produk-stat-label">Varian</div>
                                                <div class="produk-stat-angka"><?= $jumlah ?></div>
                                            </div>
                                            <div class="flex-fill text-center">
                                                <div class="produk-stat-label">Total Stok</div>
                                                <div class="produk-stat-angka"><?= (int) $p['total_stok'] ?></div>
                                            </div>
                                            <div class="flex-fill text-center">
                                                <div class="produk-stat-label">Keadaan</div>
                                                <div><span class="badge bg-<?= $vw_kartu ?> mt-1"><?= esc($teks) ?></span></div>
                                            </div>
                                        </div>

                                        <div class="d-flex align-items-center gap-2 produk-aksi">
                                            <button type="button" class="btn btn-sm btn-outline-secondary produk-aksi-ikon"
                                                title="Tambah varian untuk <?= esc($p['kode'], 'attr') ?>"
                                                data-tambah="<?= (string) esc(json_encode(['kode' => $p['kode'], 'juragan' => $p['juragan_id']])) ?>"
                                                data-bs-toggle="modal" data-bs-target="#modalTambahBarang">
                                                <i class="fal fa-plus"></i><span class="visually-hidden">Tambah varian</span>
                                            </button>
                                            <button type="submit" class="btn btn-sm btn-outline-danger produk-aksi-ikon"
                                                title="Hapus <?= esc($p['kode'], 'attr') ?> beserta semua variannya dari daftar stok"
                                                form="formHapusKartu"
                                                data-hapus-kartu="<?= (string) esc(json_encode(['kode' => $p['kode'], 'juragan' => (int) $p['juragan_id'], 'varian' => $jumlah])) ?>">
                                                <i class="fal fa-trash"></i><span class="visually-hidden">Hapus barang</span>
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-secondary produk-aksi-ikon ms-auto"
                                                title="Sunting <?= esc($varian[0]['ukuran'] !== '' && $varian[0]['ukuran'] !== null ? $p['kode'] . ' ' . (string) $varian[0]['ukuran'] : $p['kode']) ?>"
                                                data-barang="<?= $barang($varian[0]) ?>"
                                                data-bs-toggle="modal" data-bs-target="#modalSuntingBarang">
                                                <i class="fal fa-pen"></i><span class="visually-hidden">Edit</span>
                                            </button>
                                        </div>
                                    </div>

                                    <?php
                                    // rangkapan detail kartu; baru dipindah ke modal saat kartunya dibuka
                                    $harga_lain = $rentang ? ' – Rp ' . formatRibuan($p['harga_akhir']) : '';
                                    $foto_rinci = $fotonya_kartu;
                                    $utama      = $foto_rinci[0] ?? null;
                                    ?>
                                    <div class="produk-rincian d-none">
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <?php if ($utama !== null) { ?>
                                                    <button type="button" class="foto-utama w-100 border-0 rounded-3 overflow-hidden p-0"
                                                        data-full="<?= esc(\App\Models\ProdukFotoModel::alamat((int) $utama['id_foto'])) ?>"
                                                        title="Klik untuk ukuran penuh">
                                                        <img src="<?= esc(\App\Models\ProdukFotoModel::alamat((int) $utama['id_foto'], 480)) ?>" alt="Foto <?= esc($p['kode'], 'attr') ?>">
                                                    </button>
                                                    <?php if (count($foto_rinci) > 1) { ?>
                                                        <div class="foto-galeri mt-2">
                                                            <?php foreach ($foto_rinci as $j => $f) { ?>
                                                                <button type="button" class="foto-thumbs<?= $j === 0 ? ' aktif' : '' ?>"
                                                                    data-full="<?= esc(\App\Models\ProdukFotoModel::alamat((int) $f['id_foto'])) ?>"
                                                                    aria-label="Lihat foto <?= $j + 1 ?>">
                                                                    <img src="<?= esc(\App\Models\ProdukFotoModel::alamat((int) $f['id_foto'], 240)) ?>" alt="" loading="lazy">
                                                                </button>
                                                            <?php } ?>
                                                        </div>
                                                    <?php } ?>
                                                <?php } else { ?>
                                                    <div class="produk-inisial-kotak rounded-3 d-flex align-items-center justify-content-center">
                                                        <span class="produk-inisial"><?= esc($inisial($p['kode'])) ?></span>
                                                    </div>
                                                <?php } ?>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="fs-5 fw-bold"><?= esc($p['kode']) ?></div>
                                                <div class="fw-bold text-brand-600">Rp <?= formatRibuan($p['harga_mulai']) ?><?= $harga_lain ?></div>
                                                <div class="small text-muted mt-1"><i class="fal fa-store me-1"></i><?= esc($p['nama_juragan']) ?></div>
                                                <div class="small mt-2 produk-ket"><?= esc($keterangan) ?></div>
                                                <div class="d-flex gap-2 mt-2">
                                                    <span class="badge align-self-start bg-<?= $vw_kartu ?> <?= $vw_kartu === 'warning' ? 'text-dark' : 'text-white' ?>"><?= esc($teks) ?></span>
                                                    <span class="small text-muted align-self-center"><?= (int) $p['terjual'] ?> terjual</span>
                                                </div>
                                            </div>
                                            <div class="col-12">
                                                <table class="table table-sm align-middle mb-0">
                                                    <thead>
                                                        <tr class="small text-muted">
                                                            <th>Ukuran</th>
                                                            <th>Harga</th>
                                                            <th>Stok</th>
                                                            <th class="d-none d-sm-table-cell">Keterangan</th>
                                                            <th class="w-1"></th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($varian as $b) {
                                                            $ukuran_baris = (string) ($b['ukuran'] ?? '');
                                                            $ket_baris    = trim((string) ($b['keterangan'] ?? ''));
                                                            [$bw, $bteks]  = $keadaan_baris((int) $b['stok']);
                                                            ?>
                                                            <tr>
                                                                <td><?= $ukuran_baris === '' ? 'Tanpa ukuran' : esc($ukuran_baris) ?></td>
                                                                <td>Rp <?= formatRibuan((int) $b['harga']) ?></td>
                                                                <td>
                                                                    <span class="badge bg-<?= $bw ?> <?= $bw === 'warning' ? 'text-dark' : 'text-white' ?>"><?= (int) $b['stok'] ?> · <?= esc($bteks) ?></span>
                                                                </td>
                                                                <td class="small text-muted d-none d-sm-table-cell"><?= $ket_baris === '' ? '&mdash;' : esc($ket_baris) ?></td>
                                                                <td class="text-end">
                                                                    <button type="submit" class="btn btn-sm btn-outline-danger produk-aksi-ikon varian-hapus"
                                                                        title="Hapus varian ini dari daftar stok"
                                                                        form="formHapusBarang"
                                                                        data-hapus-varian="<?= (string) esc(json_encode([
                                                                            'id'    => (int) $b['id_stok'],
                                                                            'label' => trim($p['kode'] . ' ' . $ukuran_baris),
                                                                        ])) ?>">
                                                                        <i class="fal fa-trash"></i><span class="visually-hidden">Hapus varian</span>
                                                                    </button>
                                                                </td>
                                                            </tr>
                                                        <?php } ?>
                                                    </tbody>
                                                </table>
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
                <div class="col-6 mb-3">
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
                <div class="col-6 mb-3">
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
                <?= form_label('Catatan (opsional)', 'keterangan', ['class' => 'form-label']) ?>
                <?= form_textarea([
                    'class'       => 'form-control',
                    'name'        => 'keterangan',
                    'rows'        => 3,
                    'maxlength'   => 500,
                    'placeholder' => 'mis. warna navy, bahan wool',
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
                <?= form_dropdown([
                    'class'    => 'form-select' . ($err_sunting('juragan_id') ? ' is-invalid' : ''),
                    'name'     => 'juragan_id',
                    'id'       => 'sunting_juragan',
                    'options'  => $juragans,
                    'required' => '',
                ]) ?>
                <?php if ($err_sunting('juragan_id')) { ?>
                    <div class="invalid-feedback d-block"><?= esc($err_sunting('juragan_id')) ?></div>
                <?php } ?>
            </div>
            <div class="row gx-2">
                <div class="col-6 mb-3">
                    <?= form_label('Nama Barang', 'sunting_kode', ['class' => 'form-label']) ?>
                    <?= form_input(['class' => 'form-control', 'name' => 'kode', 'id' => 'sunting_kode', 'list' => 'daftarKode', 'required' => '', 'max_length' => 20]) ?>
                    <?php if ($err_sunting('kode')) { ?>
                        <div class="text-danger small mt-1"><?= esc($err_sunting('kode')) ?></div>
                    <?php } ?>
                </div>
                <div class="col-6 mb-3">
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
                <?= form_label('Catatan', 'sunting_keterangan', ['class' => 'form-label']) ?>
                <?= form_textarea(['class' => 'form-control', 'name' => 'keterangan', 'id' => 'sunting_keterangan', 'rows' => 3, 'maxlength' => 500, 'placeholder' => 'mis. warna navy, bahan wool, catatan stok...']) ?>
            </div>
        </div>
        <div class="modal-footer flex-nowrap p-0">
            <button type="button" data-bs-dismiss="modal" class="btn btn-lg btn-link fs-6 text-decoration-none col m-0 rounded-0">Batal</button>
            <button type="submit" class="btn btn-lg btn-link fs-6 text-decoration-none col m-0 rounded-0 border-start">Simpan</button>
        </div>
        <?= form_close() ?>
    </div>
</div>

<?php
// Dipakai tombol sampah di tabel varian modal detail. Form-nya berdiri sendiri
// di luar modal supaya tidak ada form yang bersarang, dan ditambatkan lewat
// atribut `form`.
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

<?php
// Isi modal detail tidak ditulis di sini: tiap kartu menyimpan rangkapannya
// sendiri (.produk-rincian, tersembunyi) dan tinggal dipindah ke modal saat
// kartunya dibuka. Foto memang sudah terlanjur ter-query untuk sampul kartu,
// dan gambar di dalam blok d-none baru dimuat saat muncul, jadi tidak ada
// permintaan tambahan sama sekali.
?>
<div class="modal fade" id="modalDetailProduk" tabindex="-1" aria-labelledby="modalDetailProdukLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalDetailProdukLabel">Detail Produk</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body"></div>
        </div>
    </div>
</div>

<div class="lightbox-produk" id="lightboxProduk" aria-hidden="true">
    <img src="" alt="Foto ukuran penuh">
</div>
<?= $this->endSection() ?>

<?= $this->section('js') ?>
<script defer src="https://kit.fontawesome.com/22a9d93fa2.js" data-auto-replace-svg="nest" crossorigin="anonymous"></script>
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
    'juragan_id' => (int) set_value('juragan_id'),
], JSON_THROW_ON_ERROR) : 'null';

$js = <<< JS
    $(function() {
    	'use strict';

        const suntingErr = {$showModalSunting};
        const tambahErr = {$showModalTambah};
        const alamatFoto = '{$alamat_foto}';
        let suntingLama = {$isiUlang};
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
            juragan: modalSunting.querySelector('[name="juragan_id"]'),
        };

        function isiForm(data) {
            fields.id_stok.value = data.id;
            fields.kode.value = data.kode;
            fields.ukuran.value = data.ukuran;
            fields.harga.value = data.harga;
            fields.stok.value = data.stok;
            fields.keterangan.value = data.keterangan;
            fields.juragan.value = String(data.juragan_id);
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

        // hapus per varian: tombolnya hidup di tabel modal detail (klon per kartu),
        // jadi delegasi ke dokumen; id varian diisi sesaat sebelum form-nya berangkat
        $(document).on('click', '[form="formHapusBarang"]', function (event) {
            const data = JSON.parse($(this).attr('data-hapus-varian'));

            if (! confirm('Hapus ' + data.label + ' dari daftar stok? Transaksi lama tetap utuh.')) {
                event.preventDefault();

                return;
            }

            formHapus.querySelector('[name="id_stok"]').value = data.id;
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

        // klik di mana pun pada kartu membuka rangkapan detailnya; tombol aksi,
        // chip varian, dan tombol foto berhenti sendiri karena punya handler masing-masing
        const modalDetail = document.getElementById('modalDetailProduk');
        const isiDetail = modalDetail.querySelector('.modal-body');
        const judulDetail = modalDetail.querySelector('.modal-title');

        document.querySelectorAll('.produk-kartu').forEach(kartu => {
            kartu.addEventListener('click', event => {
                // klik tombol apa pun (termasuk anak-anaknya: ikon, angka) tidak
                // membuka detail; svg dilewati dulu karena font-awesome kit bisa
                // mengganti <i> dengan <svg> yang tidak punya parent HTML
                const dipencet = event.target.closest ? (event.target.closest('svg') ? event.target.closest('svg').parentElement : event.target) : event.target;

                if (dipencet.closest && dipencet.closest('button, a, [data-bs-toggle], [form]')) {
                    return;
                }

                judulDetail.textContent = kartu.querySelector('h2').textContent.trim();
                isiDetail.innerHTML = '';

                const cetakan = kartu.querySelector('.produk-rincian').cloneNode(true);
                cetakan.classList.remove('d-none');
                isiDetail.appendChild(cetakan);

                bootstrap.Modal.getOrCreateInstance(modalDetail).show();
            });
        });

        // galeri mini di modal detail: thumbnail menukar foto utama, foto utama
        // membuka versi penuh di lapisan lightbox (berkas asli, bukan thumbnail)
        $(document)
            .on('click', '#modalDetailProduk .foto-thumbs', function () {
                // sengaja tanpa awalan dollar: PHP 8.3 ke atas me-interpolasi
                // variabel di dalam heredoc walau ditulis dengan backslash,
                // dan nama "this" menabrak objek View yang sedang merender
                const thumb = $(this);

                thumb.closest('.foto-galeri').find('.foto-thumbs').removeClass('aktif');
                thumb.addClass('aktif');
                thumb.closest('.row').find('.foto-utama')
                    .attr('data-full', thumb.attr('data-full'))
                    .find('img')
                    .attr('src', thumb.find('img').attr('src'));
            })
            .on('click', '#modalDetailProduk .foto-utama', function () {
                $('#lightboxProduk img').attr('src', $(this).attr('data-full') || $(this).find('img').attr('src'));
                $('#lightboxProduk').addClass('tampil');
            })
            .on('click', '#lightboxProduk', function () {
                $(this).removeClass('tampil');
            });

        // Esc ditangkap lebih dulu (capture) supaya lightbox yang menutup, bukan
        // modal di baliknya; tanpa ini satu Esc menutup dua-duanya sekaligus
        document.addEventListener('keydown', event => {
            const lapisan = document.getElementById('lightboxProduk');

            if (event.key === 'Escape' && lapisan.classList.contains('tampil')) {
                event.stopPropagation();
                event.preventDefault();
                lapisan.classList.remove('tampil');
            }
        }, true);
    });
    JS;

echo '<script>' . (new Tholu\Packer\Packer($js, 'Normal', true, false, true))->pack() . '</script>';
?>
<?= $this->endSection() ?>
