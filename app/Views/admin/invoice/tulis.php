<?php

use CodeIgniter\I18n\Time;

$sekarang = new Time('now');
$session  = \Config\Services::session();
?>

<?= $this->extend('template/main') ?>

<?= $this->section('content') ?>
<?= $this->include('admin/navbar') ?>

<div class="mx-auto mb-3 w-full max-w-[1120px] px-4">

    <h1 class="h3 mt-5">Tulis Orderan</h1>

    <nav aria-label="breadcrumb">
        <ol class="breadcrumb p-0">
            <li class="breadcrumb-item"><?= anchor('', 'Dasbor') ?></li>
            <li class="breadcrumb-item"><?= anchor('admin/invoices/lihat/semua/semua', 'Transaksi') ?></li>
            <li class="breadcrumb-item active" aria-current="page">Tulis Orderan</li>
        </ol>
    </nav>

</div>

<div class="mx-auto mb-5 w-full max-w-[1120px] px-4">

    <?= form_open('admin/invoices/save', ['class' => 'row', 'id' => 'iForm']); ?>

    <div class="col-12 col-lg-6 mb-3">
        <div class="h-full rounded-kartu border border-ink-200 bg-white shadow-kartu">
            <div class="flex items-center gap-2.5 rounded-t-kartu border-b border-ink-100 bg-ink-50/60 px-4 py-2.5 sm:px-5">
                <span class="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-[0.65rem] border border-brand-100 bg-white text-brand-500"><i class="fal fa-store text-xs"></i></span>
                <h6 class="mb-0 text-[0.78rem] font-bold uppercase tracking-[0.06em] text-ink-700">Informasi Orderan</h6>
            </div>
            <div class="form-kartu px-4 py-4 sm:px-5">
                <div class="mb-3">
                    <div class="row gx-2 mb-3">
                        <div class="col">
                            <?= form_label('Juragan', 'juragan', ['class' => 'form-label']); ?>
                            <?= form_dropdown('juragan', ['' => 'Pilih Juragan'], '', ['class' => 'form-select', 'id' => 'juragan', 'required' => '']); ?>
                        </div>
                        <div class="col">
                            <?= form_label('Admin/CS', 'pengguna', ['class' => 'form-label']); ?>
                            <?= form_dropdown('pengguna', ['' => 'Pilih Admin/CS'], '', ['class' => 'form-select', 'id' => 'pengguna', 'required' => '']); ?>
                        </div>
                    </div>
                    <div class="row gx-2">
                        <div class="col">
                            <?php
                            // get all active labels
                            $labelModel    = new \App\Models\LabelAsal();
                            $options_label = ['' => 'Pilih asal'];

                            foreach ($labelModel->where('status', '1')->orderBy('label', 'asc')->findAll() as $label) {
                                $options_label[$label->id] = $label->label;
                            }
                            ?>
                            <?= form_label('Asal Orderan', 'asal_orderan', ['class' => 'form-label']); ?>
                            <?= form_dropdown('asal_orderan', $options_label, '', ['class' => 'form-select', 'id' => 'asal_orderan', 'required' => '']); ?>
                        </div>
                        <div class="col">
                            <?= form_label('Label', 'label', ['class' => 'form-label']); ?>
                            <?= form_input('label', '', ['class' => 'form-control', 'id' => 'label', 'placeholder' => 'label - opsional, max: 50 karakter']); ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <div class="col-12 col-lg-6 mb-3">
        <div class="h-full rounded-kartu border border-ink-200 bg-white shadow-kartu">
            <div class="flex items-center gap-2.5 rounded-t-kartu border-b border-ink-100 bg-ink-50/60 px-4 py-2.5 sm:px-5">
                <span class="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-[0.65rem] border border-brand-100 bg-white text-brand-500"><i class="fal fa-user text-xs"></i></span>
                <h6 class="mb-0 text-[0.78rem] font-bold uppercase tracking-[0.06em] text-ink-700">Tanggal &amp; Pelanggan</h6>
            </div>
            <div class="form-kartu px-4 py-4 sm:px-5">
                <div class="mb-3">
                    <?= form_label('Tanggal Order', 'tanggal_order', ['class' => 'form-label']); ?>
                    <?= form_input('tanggal_order', set_value('tanggal_order', $sekarang->toDateString()), ['class' => 'form-control', 'id' => 'tanggal_order', 'required' => '', 'max' => $sekarang->toDateString()], 'date'); ?>
                </div>
                <div class="mb-3">
                    <div class="hidden_id">
                        <?= form_label('Pelanggan', 'pelanggan', ['class' => 'form-label']); ?>
                        <?= form_hidden('id_pemesan', ''); ?>
                        <?= form_hidden('id_kirimKe', ''); ?>
                    </div>

                    <div class="input-group mb-3 mycustom form_pemesan">
                        <?= form_input([
                            'class'       => 'form-control cari_pelanggan pemesan',
                            'id'          => 'cari_pemesan',
                            'placeholder' => 'cari data pelanggan',
                            'type'        => 'search',
                        ]); ?>
                        <?= form_button([
                            'class'          => 'btn btn-dark',
                            'content'        => '<i class="fal fa-plus"></i> Tambah',
                            'data-bs-target' => '#modalTambahPelanggan',
                            'data-bs-toggle' => 'modal',
                            'id'             => 'tambah_pemesan_kirimKe',
                            'title'          => 'Tambah Data Pemesan',
                        ]); ?>
                    </div>

                    <div class="mb-3 info-data-pemesan" style="display: none;">
                        <?= form_button([
                            'class'   => 'btn btn-link text-danger text-decoration-none btn-sm float-end btn-hapus-alamat pemesan',
                            'content' => '<i class="fal fa-trash"></i> <span class="visually-hidden">Hapus</span>',
                            'title'   => 'Hapus Pemesan',
                        ]); ?>
                        <span id="alamat_pemesan" class="d-block border rounded-[0.7rem] border-ink-200 bg-ink-50 p-3"></span>
                    </div>

                    <div class="input-group mb-3 mycustom form_kirimKe" style="display: none">
                        <?= form_input([
                            'class'       => 'form-control cari_pelanggan kirimKe',
                            'id'          => 'cari_kirimKe',
                            'placeholder' => 'cari data pelanggan',
                            'type'        => 'search',
                        ]); ?>
                        <?= form_button([
                            'class'          => 'btn btn-dark',
                            'content'        => '<i class="fal fa-plus"></i> Tambah',
                            'data-bs-target' => '#modalTambahPelanggan',
                            'data-bs-toggle' => 'modal',
                            'id'             => 'tambah_kirimKe',
                            'title'          => 'Tambah Data Kirim Kepada',
                        ]); ?>
                    </div>

                    <div class="mb-3 info-data-kirimKe" style="display: none;">
                        <?= form_button([
                            'class'   => 'btn btn-link text-danger text-decoration-none btn-sm float-end btn-hapus-alamat kirimKe',
                            'content' => '<i class="fal fa-trash"></i> <span class="visually-hidden">Hapus</span>',
                            'title'   => 'Hapus Kirim',
                        ]); ?>
                        <span id="alamat_kirimKe" class="d-block border rounded-[0.7rem] border-ink-200 bg-ink-50 p-3"></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 mb-3">
        <div class="rounded-kartu border border-ink-200 bg-white shadow-kartu">
            <div class="flex items-center gap-2.5 rounded-t-kartu border-b border-ink-100 bg-ink-50/60 px-4 py-2.5 sm:px-5">
                <span class="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-[0.65rem] border border-brand-100 bg-white text-brand-500"><i class="fal fa-tag text-xs"></i></span>
                <h6 class="mb-0 text-[0.78rem] font-bold uppercase tracking-[0.06em] text-ink-700">Tipe &amp; Jadwal</h6>
            </div>
            <div class="form-kartu px-4 py-4 sm:px-5">
                <div class="row gx-3 gy-3">
                    <div class="col-12 col-sm-6 col-lg-3">
                        <?= form_label('Tipe Pesanan', 'tipe_pesanan', ['class' => 'form-label']); ?>
                        <?= form_dropdown('rincian[tipe]', ['' => 'Pilih tipe'] + tipe_pesanan(), '', ['class' => 'form-select', 'id' => 'tipe_pesanan']); ?>
                    </div>
                    <div class="col-12 col-sm-6 col-lg-3 d-none tipe-buat">
                        <?= form_label('Deadline', 'rincian_deadline', ['class' => 'form-label']); ?>
                        <?= form_input('rincian[deadline]', '', ['class' => 'form-control', 'id' => 'rincian_deadline'], 'date'); ?>
                    </div>
                    <div class="col-12 col-sm-6 col-lg-3 d-none tipe-sewa">
                        <?= form_label('Tanggal Diambil', 'rincian_ambil', ['class' => 'form-label']); ?>
                        <?= form_input('rincian[ambil]', '', ['class' => 'form-control', 'id' => 'rincian_ambil'], 'date'); ?>
                    </div>
                    <div class="col-12 col-sm-6 col-lg-3 d-none tipe-sewa">
                        <?= form_label('Tanggal Kembali', 'rincian_kembali', ['class' => 'form-label']); ?>
                        <?= form_input('rincian[kembali]', '', ['class' => 'form-control', 'id' => 'rincian_kembali'], 'date'); ?>
                    </div>
                    <div class="col-12 col-sm-6 col-lg-3 d-none tipe-sewa">
                        <?= form_label('Jaminan', 'rincian_jaminan', ['class' => 'form-label']); ?>
                        <?= form_input('rincian[jaminan]', '', ['class' => 'form-control', 'id' => 'rincian_jaminan', 'placeholder' => 'cth: SIM C / KTP']); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 mb-3">
        <div class="card mb-3 overflow-hidden rounded-kartu border-ink-200 shadow-kartu">
            <div class="card-header border-ink-100 bg-ink-50/60">
                <ul class="nav nav-tabs card-header-tabs">
                    <li class="nav-item">
                        <span class="nav-link active" aria-current="true">Orderan</span>
                    </li>
                    <li class="nav-item">
                        <a href="#!" class="nav-link" data-bs-toggle="modal" data-bs-target="#tambahProduk">
                            <i class="fal fa-plus"></i>
                        </a>
                    </li>
                </ul>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th scope="col">Produk</th>
                                <th scope="col">Harga</th>
                                <th scope="col">QTY</th>
                                <th scope="col" class="text-end">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody class="list-orderan" data-length="1">
                            <tr class="orderan-kosong">
                                <td class="text-center" colspan="4">
                                    <div class="py-5"><i class="fad text-warning fa-<?= random_element(['shopping-cart', 'shopping-bag', 'shopping-basket', 'bags-shopping', 'dolly-flatbed-empty', 'dolly-empty']) ?> fa-4x"></i>
                                        <p class="mb-0"><?= random_element(['orderan kosong?', 'isi dulu orderannya ya?', 'jangan lupa isi orderannya ya?']) ?></p>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                        <tfoot class="customBiaya d-none listBiaya">
                            <tr>
                                <td colspan="3" class="text-end">Subtotal</td>
                                <td class="text-end" data-totalbiaya="0" data-subtotal="0" id="subTotal"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <div class="border-bottom pb-3 customBiaya d-none">
                    <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#biayaOrder" data-biayaID="1" data-judul="Ongkir" data-operasi="1"><i class="fal fa-plus"></i> Ongkir</button>
                    <button type="button" class="btn btn-sm btn-outline-primary" data-bs-target="#biayaOrder" data-bs-toggle="modal" data-biayaID="2" data-judul="Lain-lain" data-operasi="1"><i class="fal fa-plus"></i> Biaya Lain</button>
                </div>
                <div class="customBiaya d-none">
                    <div class="d-flex justify-content-between align-items-center">
                        <h6 class="fw-bold">TOTAL</h6>
                        <div class="h2 text-primary" id="grandTotal"></div>
                    </div>
                </div>
            </div>
        </div>

        <?= anggota_panel_form() ?>

        <div class="form-kartu mb-3">
            <?= form_label('Note / Keterangan', 'keterangan', ['class' => 'form-label']); ?>
            <?= form_textarea(['name' => 'keterangan', 'id' => 'keterangan', 'class' => 'form-control', 'rows' => '3', 'placeholder' => 'opsional']); ?>
        </div>

        <hr class="my-4 border-0 border-t border-ink-200 opacity-100">
        <button type="submit" class="btn btn-primary w-100 rounded-[0.75rem] py-2.5 text-uppercase">
            <i class="fal fa-save"></i> Simpan
        </button>
    </div>
    <?= form_close(); ?>

</div>

<?= $this->endSection() ?>

<?= $this->section('modal') ?>
<!-- Modal tambah pelanggan -->
<div class="modal fade" id="modalTambahPelanggan" data-backdrop="static" tabindex="-1" aria-labelledby="modalTambahPelangganLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <?= form_open('', ['id' => 'tambahPelanggan', 'class' => 'modal-content']); ?>
        <div class="modal-header">
            <h5 class="modal-title" id="modalTambahPelangganLabel"></h5>
            <button type="button" data-reset="false" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
            <div class="mb-3">
                <?= form_label('Nama Pelanggan', 'nama_pelanggan', ['class' => 'form-label']); ?>
                <?= form_input('nama_pelanggan', '', ['class' => 'form-control input', 'id' => 'nama_pelanggan', 'required' => '', 'placeholder' => 'nama pelanggan', 'autocomplete' => 'off']); ?>
                <div id="auto-results"></div>
            </div>
            <div class="row gx-2 mb-3">
                <div class="col-6">
                    <?= form_label('HP 1', 'hp1', ['class' => 'form-label']); ?>
                    <?= form_input([
                        'class'        => 'form-control input',
                        'id'           => 'hp1',
                        'name'         => 'hp[0]',
                        'pattern'      => '^(0|\+62|62)(?:\d{8,})$',
                        'placeholder'  => 'HP',
                        'required'     => '',
                        'autocomplete' => 'off',
                    ]) ?>
                </div>
                <div class="col-6">
                    <?= form_label('HP 2', 'hp2', ['class' => 'form-label']); ?>
                    <?= form_input([
                        'autocomplete' => 'off',
                        'class'        => 'form-control input',
                        'id'           => 'hp2',
                        'name'         => 'hp[1]',
                        'pattern'      => '^(0|\+62|62)(?:\d{8,})$',
                        'placeholder'  => 'HP 2 - opsional',
                    ]) ?>

                </div>
            </div>
            <div class="mb-3">
                <div class="form-check form-switch">
                    <?= form_label('COD', 'cod', ['class' => 'form-check-label']) ?>
                    <?= form_checkbox('cod', 'ya', true, ['class' => 'form-check-input swictCOD', 'id' => 'cod']) ?>
                </div>
            </div>

            <div class="collapse" id="nonCOD">
                <div class="mb-3">
                    <?= form_label('Alamat', 'alamat', ['class' => 'form-label']); ?>
                    <?= form_textarea(['name' => 'alamat', 'class' => 'form-control input',  'id' => 'alamat', 'required' => '', 'placeholder' => 'RT/RW, Nama Kampung/Perumahan, No Rumah, Desa/Kelurahan', 'rows' => '3', 'disabled' => '']); ?>
                </div>

                <div class="row gx-2 mb-3">
                    <div class="col">
                        <?= form_label('Provinsi', 'provinsi', ['class' => 'form-label']); ?>
                        <?= form_dropdown('provinsi', ['' => 'Pilih Provinsi'], '', ['class' => 'form-select input', 'id' => 'provinsi', 'required' => '', 'disabled' => '']); ?>
                    </div>
                    <div class="col">
                        <?= form_label('Kab/Kota', 'kabupaten', ['class' => 'form-label']); ?>
                        <?= form_dropdown('kabupaten', ['' => 'Pilih Kab/Kota'], '', ['class' => 'form-select input', 'id' => 'kabupaten', 'required' => '', 'disabled' => '']); ?>
                    </div>
                    <div class="col">
                        <?= form_label('Kecamatan', 'kecamatan', ['class' => 'form-label']); ?>
                        <?= form_dropdown('kecamatan', ['' => 'Pilih Kecamatan'], '', ['class' => 'form-select input', 'id' => 'kecamatan', 'required' => '', 'disabled' => '']);
                        ?>
                    </div>
                </div>

                <div class="row gx-2 mb-3">
                    <div class="col-4">
                        <?= form_label('Kode Pos', 'kodepos', ['class' => 'form-label']); ?>
                        <?= form_input('kodepos', '', ['class' => 'form-control input', 'id' => 'kodepos', 'placeholder' => 'xxxxx', 'autocomplete' => 'off', 'disabled' => '']); ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" data-reset="false" class="btn btn-link text-decoration-none" data-bs-dismiss="modal">Batal</button>

            <button type="button" disabled class="btn loading btn-primary" style="display:none">
                <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Simpan
            </button>
            <button type="submit" class="btn submit btn-primary">
                <i class="fal fa-save"></i> Simpan
            </button>
        </div>
        <?= form_close(); ?>
    </div>
</div>

<!-- Modal tambah biaya -->
<div class="modal fade" id="biayaOrder" data-backdrop="static" tabindex="-1" aria-labelledby="biayaOrderLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="biayaOrderLabel">Modal title</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">

                </button>
            </div>
            <?= form_open('', ['id' => 'tambahBiaya'], ['biayaId' => '']); ?>
            <div class="modal-body">
                <div class="mb-3">
                    <?= form_label('Nominal', 'nominalBiaya', ['class' => 'form-label']); ?>
                    <?= form_input(['name' => 'nominal_biaya', 'id' => 'nominalBiaya', 'class' => 'form-control', 'required' => '', 'placeholder' => 'cth: ' . random_element(['10000', '20000', '25000', '30000']), 'type' => 'number']); ?>
                    <div class="form-text" id="biayaHelp">Gunakan tanda ( <b>-</b> ) untuk mengurangi. misal untuk diskon: <b>-2000</b></div>
                </div>

                <div class="mb-3">
                    <?= form_label('Label', 'labelBiaya', ['class' => 'form-label']); ?>
                    <?= form_input(['name' => 'label_biaya', 'id' => 'labelBiaya', 'class' => 'form-control', 'placeholder' => 'label biaya - opsional, max: 20 karakter', 'maxlength' => '20']); ?>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-link text-decoration-none" data-bs-dismiss="modal">Batal</button>
                <button type="submit" id="submit-form" class="btn btn-primary addBiaya">Tambahkan</button>
            </div>
            <?= form_close(); ?>
        </div>
    </div>
</div>

<!-- Modal tambah produk -->
<div class="modal fade" id="tambahProduk" data-backdrop="static" tabindex="-1" aria-labelledby="tambahProdukLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="tambahProdukLabel">Tambah Orderan</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">

                </button>
            </div>
            <?= form_open('', ['id' => 'nambahProduk']); ?>
            <div class="modal-body">
                <div class="row gx-2">
                    <div class="col col-sm-6 col-md-3">
                        <?= form_label('Kode Produk', 'kode_produk', ['class' => 'form-label']); ?>
                        <?= form_input(['name' => 'kode_produk', 'id' => 'kode_produk', 'class' => 'form-control', 'required' => '', 'placeholder' => 'cth: ' . random_element(['SK-45', 'BK-01', 'Z-01'])]); ?>
                    </div>
                    <div class="col col-sm-6 col-md-3">
                        <?= form_label('Harga Satuan', 'harga_satuan', ['class' => 'form-label']); ?>
                        <?= form_input(['name' => 'harga_satuan', 'min' => '0', 'id' => 'harga_satuan', 'class' => 'form-control', 'required' => '', 'placeholder' => 'cth: ' . random_element(['225000', '280000', '295000', '320000']), 'type' => 'number']); ?>
                    </div>
                    <div class="col col-sm-6 col-md-3">
                        <?= form_label('Ukuran', 'ukuran', ['class' => 'form-label']); ?>
                        <?= '<select name="ukuran" class="form-select" id="ukuran" required="">';
                        echo '<option value="" disabled="" selected="">Pilih ukuran</option>';

                        foreach (config('JuraganConfig')->size as $k => $v) {
                            echo '<optgroup label="' . $k . '">';

                            foreach ($v as $k2 => $v2) {
                                echo '<option value="' . $k2 . '">' . $v2 . '</option>';
                            }
                            echo '</optgroup>';
                        }
                        echo '<option value="custom">Custom</option>';
                        echo '</select>';
                        ?>
                    </div>
                    <div class="col col-sm-6 col-md-3">
                        <?= form_label('QTY', 'QTY', ['class' => 'form-label']); ?>
                        <?= form_input(['name' => 'QTY', 'id' => 'QTY', 'class' => 'form-control', 'required' => '', 'placeholder' => 'cth: ' . mt_rand(1, 20), 'type' => 'number', 'min' => '1']); ?>
                    </div>
                </div>

                <div class="row gx-2 mt-1 d-none rounded-[0.85rem] border border-dashed border-[#dbe3ee] bg-ink-50 px-[0.85rem] pb-[0.6rem] pt-[0.4rem] [&_h6]:text-[0.78rem] [&_h6]:font-bold [&_h6]:uppercase [&_h6]:tracking-[0.06em] [&_h6]:text-ink-600 [&_.fal]:text-brand-500 [&_.form-label]:text-xs [&_.form-label]:text-ink-500" id="customDetail">
                    <div class="col-12">
                        <h6 class="mb-2 mt-3 fw-bold">
                            <i class="fal fa-ruler-combined text-danger"></i> Spesifikasi Custom
                            <span class="fw-normal text-muted small">- opsional, hanya untuk pesanan custom</span>
                        </h6>
                    </div>
                    <div class="col-sm-6 mb-2">
                        <?= form_label('Ukuran Jadi', 'ri_ukuran_jadi', ['class' => 'form-label']); ?>
                        <input type="text" class="form-control ri-field" id="ri_ukuran_jadi" data-key="ukuran_jadi" placeholder="cth: L / PM" autocomplete="off">
                    </div>
                    <div class="col-sm-6 mb-2">
                        <?= form_label('Nama Pemilik Ukuran', 'ri_pemilik', ['class' => 'form-label']); ?>
                        <input type="text" class="form-control ri-field" id="ri_pemilik" data-key="pemilik" placeholder="cth: AFRIZAL" autocomplete="off">
                    </div>
                    <div class="col-sm-6 mb-2">
                        <?= form_label('Bahan', 'ri_bahan', ['class' => 'form-label']); ?>
                        <input type="text" class="form-control ri-field" id="ri_bahan" data-key="bahan" placeholder="cth: JETBLACK" autocomplete="off">
                    </div>
                    <div class="col-sm-6 mb-2">
                        <?= form_label('Model & Jahitan', 'ri_spesifikasi', ['class' => 'form-label']); ?>
                        <input type="text" class="form-control ri-field" id="ri_spesifikasi" data-key="spesifikasi" placeholder="cth: KANCING JAS 2, SAKU BAWAH VARIASI" autocomplete="off">
                    </div>
                    <div class="col-12">
                        <?= form_label('Tabel Ukuran Detail', 'ri_ukuran_detail', ['class' => 'form-label']); ?>
                        <textarea class="form-control ri-field" id="ri_ukuran_detail" data-key="ukuran_detail" rows="2" placeholder="cth: P.JAS 75, L.DADA 103, L.PINGGANG 80"></textarea>
                    </div>
                </div>
                <div class="row gx-2 mt-1 rounded-[0.85rem] border border-dashed border-[#dbe3ee] bg-ink-50 px-[0.85rem] pb-[0.6rem] pt-[0.4rem] [&_h6]:text-[0.78rem] [&_h6]:font-bold [&_h6]:uppercase [&_h6]:tracking-[0.06em] [&_h6]:text-ink-600 [&_.fal]:text-brand-500 [&_.form-label]:text-xs [&_.form-label]:text-ink-500">
                    <div class="col-12">
                        <h6 class="mb-2 mt-3 fw-bold">
                            <i class="fal fa-ruler-combined text-danger"></i> Ukuran Terstruktur
                            <span class="fw-normal text-muted small">- opsional, pilih jenisnya lalu isi hasil ukurnya</span>
                        </h6>
                    </div>
                    <?= ukuran_form_panel(['id' => 'ukuranModal']) ?>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-link text-decoration-none" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary addBiaya">Tambahkan</button>
            </div>
            <?= form_close(); ?>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('js') ?>
<script defer src="https://kit.fontawesome.com/22a9d93fa2.js" data-auto-replace-svg="nest" crossorigin="anonymous"></script>
<?php

$current_user_id     = $session->get('id');
$link_api_invoice    = site_url('admin/invoices/save');
$link_api_juragan    = site_url('api/juragan/by_user/');
$link_api_kecamatan  = site_url('rajaongkir/kecamatan');
$link_api_kota       = site_url('rajaongkir/kota');
$link_api_pengguna   = site_url('api/juragan/get_users/');
$link_api_provinsi   = site_url('rajaongkir/provinsi');
$link_cari_pelanggan = site_url('pelanggan/cari');
$link_invoice        = site_url('admin/invoices/lihat/');
$link_post_pelanggan = site_url('pelanggan/baru');
$link_api_notif      = site_url('api/notifikasi/');

?>
<script>
    window.UKURAN_FORM = <?= ukuran_form_data() ?>;
    window.CARI_PELANGGAN = '<?= $link_cari_pelanggan ?>';
</script>
<script defer src="<?= base_url('assets/js/ukuran-form.js') ?>?v=<?= (int) @filemtime(FCPATH . 'assets/js/ukuran-form.js') ?>"></script>
<script defer src="<?= base_url('assets/js/anggota-form.js') ?>?v=<?= (int) @filemtime(FCPATH . 'assets/js/anggota-form.js') ?>"></script>
<script defer src="<?= base_url('assets/js/cari-pelanggan.js') ?>?v=<?= (int) @filemtime(FCPATH . 'assets/js/cari-pelanggan.js') ?>"></script>

<?php

$js = <<< JS
    $(function() {
    	'use strict';
    	// collapse sidebar

    	var juragan = document.getElementById('juragan');
        if(juragan) {
            juragan.addEventListener('show.bs.offcanvas', function () {
                var id = {$current_user_id};
                $('#listLi').empty();
                $.getJSON('{$link_api_juragan}', { id: id }, function(b){
                    var a=[];
                    a.push('<a class="list-group-item text-light list-group-item-action" href="{$link_invoice}semua"><i class="fal fa-user-circle"></i> Semua Juragan</a>');

                    $.each(b[id].juragan,function(c,b){
                        a.push('<a href="{$link_invoice}'+b.slug+'" class="list-group-item text-light list-group-item-action"><i class="fal fa-user-circle"></i> '+b.nama+'</a>');
                    }),

                    $(a.join('')).appendTo('#listLi');
                });
            });
        }

    	// notifikasi

    	function getNotif(page = 1, dibaca = 0){
    		var id = {$current_user_id};
    		$.getJSON('{$link_api_notif}' + 'get', { id: id, page: page, dibaca: dibaca }, function(b){
    			var a=[],
    				data = b.results;

    			if (b.count > 0) {
    				$('.btnSettingNotif').show();
    				a.push('');

    				$.each(data,function(c,b){
    					a.push(`<div class="list-group-item list-group-item-action">
    						<div class="d-flex">
    							<div class="me-auto">
    								<div class="text-muted small">`+b.created_at +`</div>
    								<a class="d-block text-decoration-none" href="{$link_invoice}semua/semua?cari[kolom]=faktur&cari[q]=`+b.invoice+`">`+ b.notif+`</a>
    							</div>
    							<div class="d-flex justify-content-right flex-column actionNotif">
    								<button data-id="`+b.id+`" class="markAs border-0 bg-transparent text-primary small" type="button"><i class="fal fa-circle"></i></button>
    							</div>
    						</div>
    					</div>`);
    				}),
    				$(a.join('')).appendTo('#notifDisini');
    				var next = '';

    				if (b.next) {
    					$('<button type="button" class="list-group-item list-group-item-action text-center lanjutNotif" data-page="'+ (b.page+1) +'">lanjut ...</button>').appendTo('#notifDisini');
    				}
    			}
    			else {
    				$('.btnSettingNotif').hide();

    				$('<div class="d-flex justify-content-center align-items-center" style="height: 90vh"><i class="fal fa-bell-slash fa-5x"></i></div>').appendTo('#notifDisini');
    			}
    		});
    	}

    	$(document).on('click', '.lanjutNotif',function(){
    		getNotif($(this).data('page'));
    		$(this).attr('disabled', true).html(`<div class="text-center">
    			<div class="spinner-border" role="status">
    				<span class="visually-hidden">Loading...</span>
    			</div>
    		</div>`).remove();
    	});

    	var notifikasi = document.getElementById('notifikasi');
        if(notifikasi) {
            notifikasi.addEventListener('show.bs.offcanvas', function () {

                var id = {$current_user_id};
                $('#notifDisini').empty(),
                getNotif();
            });
        }

    	counter_notif();

    	setInterval(function(){
    		counter_notif();
    	}, 10000);

    	function counter_notif() {
    		var id = {$current_user_id};
    		$.getJSON('{$link_api_notif}' + 'get', { id: id }, function(b){
    			$('.counter').text(b.count_text);
    		});
    	}

    	$(document).on('click', '.actionNotif .markAs', function() { // markAs
    		var action = '';

    		if ($(this).hasClass( "text-primary" )) {
    			$(this).removeClass('text-primary').addClass('text-muted');
    			action = 'sudahBaca';
    		}
    		else {
    			$(this).removeClass('text-muted').addClass('text-primary');
    			action = 'belumBaca';
    		}

    		$.post( '{$link_api_notif}' + 'mark', { action: action, id: $(this).data('id') });
    	});

    	$('.tandaiSemuaTerbaca').on('click',function(){
    		var id = {$current_user_id};

    		$.post( '{$link_api_notif}' + 'mark_all', { id: id }, function() {
    			//
    			$('.actionNotif .markAs').removeClass('text-primary').addClass('text-muted');
    		});
    	});

    	// hapus data pemesan / kirim kepada
    	$('.btn-hapus-alamat').on('click', function(){
    		if($(this).hasClass("pemesan")) {
    			$('#alamat_pemesan').empty(); // hapus alamat pemesan
    			$('.form_pemesan').show();
    			$('#cari_pemesan').show().addClass('ganti-data-pemesan');
    			$('.info-data-pemesan').hide();

    			$('.hidden_id [name="id_pemesan"]').val('');

    			if ($('.hidden_id [name="id_kirimKe"]').val() == '') {
    				$('.form_kirimKe').hide();
    				$('.info-data-kirimKe').hide();

    				$(".cari_pelanggan").removeClass('ganti-data-pemesan');
    				$('#tambah_pemesan').attr("id","tambah_pemesan_kirimKe");
    			}
    		}
    		else if($(this).hasClass("kirimKe")) {
    			$('#alamat_kirimKe').empty(); // hapus alamat pemesan
    			$('.form_kirimKe').show();
    			$('#cari_kirimKe').show().addClass('ganti-data-kirimKe');
    			$('.info-data-kirimKe').hide();

    			$('.hidden_id [name="id_kirimKe"]').val('');

    			if ($('.hidden_id [name="id_pemesan"]').val() == '') {
    				// $('.form_pemesan').hide();
    				$('.form_kirimKe').hide();

    				$(".cari_pelanggan").removeClass('ganti-data-kirimKe');
    				$('#tambah_pemesan').attr("id","tambah_pemesan_kirimKe");
    			}
    		}
    	});


    	// load juragan
    	// embed langsung ke form select - dropdown
    	$.getJSON('{$link_api_juragan}', { id: {$current_user_id} }, function(b){
    		$.each(b[{$current_user_id}].juragan,function(c,b){
    			$('<option />',{value:b.id,text:b.nama}).appendTo($('select[name="juragan"]'));
    		});
    	});


    	// load admin/cs
    	// embed langsung ke form select - dropdown
    	$.getJSON('{$link_api_pengguna}', { id: {$current_user_id} }, function(a){
    		$.each(a,function(b,a){
    			$('<option />',{value:a.id,text:a.nama}).appendTo($('select[name="pengguna"]'));
    		});
    	});

    	// modal tambah pelanggan

    	var mpel=document.getElementById('modalTambahPelanggan');
    	mpel.addEventListener('show.bs.modal',function(g){
    		var a=g.relatedTarget,
    			c=a.getAttribute('title'),
    			d=a.getAttribute('id'),
    			e=mpel.querySelector('.modal-title');
    		e.textContent=c;
    		$('#tambahPelanggan').addClass(d);
    	});

    	var CoN = document.getElementById('nonCOD');
    	CoN.addEventListener('show.bs.collapse', function () {
    		var b=mpel.querySelector('[name="provinsi"]');

    		$.ajax({
    			method:'GET',
    			url:'{$link_api_provinsi}'
    		}).done(function(a){
    			$.each(a,function(c,a){
    				$('<option />',{value:a.province_id,text:a.province_name}).appendTo(b);
    			});
    		});
    	});

    	$(document).on("keyup change", '.swictCOD',function(){
            if(this.checked) {
                // disable "semua" form alamat
                $('#nonCOD .input').prop('disabled', true);
                $('#nonCOD').collapse('hide');
            }
            else {
                $('#nonCOD .input').prop('disabled', false);
                $('#nonCOD').collapse('show');
            }
        });

    	var kab=mpel.querySelector('#modalTambahPelanggan [name="kabupaten"]'),
    		kec=mpel.querySelector('#modalTambahPelanggan [name="kecamatan"]'),
    		fpel=$('#tambahPelanggan'),
    		request_pelanggan;

    	fpel.on('submit',function(event){
    		// Prevent default posting of form - put here to work in case of errors
    		event.preventDefault();

    		// Abort any pending request
    		if (request_pelanggan) {
    			request_pelanggan.abort();
    		}
    		// setup some local variables
    		var form = $(this);

    		// Let's select and cache all the fields
    		var inputs = form.find("input, select, button, textarea");

    		// Serialize the data in the form
    		var serializedData = form.serialize();

    		// Let's disable the inputs for the duration of the Ajax request.
    		// Note: we disable elements AFTER the form data has been serialized.
    		// Disabled form elements will not be serialized.
    		inputs.prop("disabled", true);

    		// Fire off the request to /form.php
    		request_pelanggan = $.ajax({
    			url: "{$link_post_pelanggan}",
    			type: "post",
    			data: serializedData
    		});

    		// Callback handler that will be called on success
    		request_pelanggan.done(function (response, textStatus, jqXHR){
    			var c='';
    			c+='<span class="d-block fw-bold">'+response.nama_pelanggan+'</span>',
    			c+='<span class="d-block">';
    			$.each(response.hp, function (i,v){
    				if(i==1) {
    					c+='<span> / </span>';
    				}
    				c+=v;
    			});
    			c+='</span>';
    			c+='<span class="d-block">';
    			if (response.cod === '0') {
    				c+=response.alamat+', '+ response.nama_kecamatan+', '+response.nama_kabupaten +', '+ response.nama_provinsi+', '+response.kodepos;
    			}
    			else {
    				c+= 'C.O.D';
    			}
    			c+='</span>';

    			if (form.hasClass('tambah_pemesan_kirimKe')) {
    				// sisipkan dikedua
    				$('#alamat_pemesan').empty().append('<h6 class="text-muted fw-normal">Pemesan</h6>' + c);
    				$('#alamat_kirimKe').empty().append('<h6 class="text-muted fw-normal">Kirim Kepada</h6>' + c);

    				$('.hidden_id [name="id_pemesan"]').val(response.id_pelanggan);
    				$('.hidden_id [name="id_kirimKe"]').val(response.id_pelanggan);

    				$('.form_pemesan').hide();

    				$('.info-data-pemesan').show();
    				$('.info-data-kirimKe').show();

    				form.removeClass('tambah_pemesan_kirimKe'),
    				$('#tambah_pemesan_kirimKe').attr("id","tambah_pemesan");
    			}
    			else if (form.hasClass('tambah_pemesan')) {
    				// sisipkan hanya untuk pemesan
    				$('#alamat_pemesan').empty().append('<h6 class="text-muted fw-normal">Pemesan</h6>' + c);
    				$('.hidden_id [name="id_pemesan"]').val(response.id_pelanggan);

    				$('.form_pemesan').hide();
    				$('.info-data-pemesan').show();
    			}
    			else if (form.hasClass('tambah_kirimKe')) {
    				// sisipkan hanya untuk kirim kepada
    				$('#alamat_kirimKe').empty().append('<h6 class="text-muted fw-normal">Kirim Kepada</h6>' + c);
    				$('.hidden_id [name="id_kirimKe"]').val(response.id_pelanggan);

    				$('.form_kirimKe').hide();
    				$('.info-data-kirimKe').show();
    			}
    			$(mpel).modal('hide');
    		});

    		// Callback handler that will be called on failure
    		request_pelanggan.fail(function (jqXHR, textStatus, errorThrown){
    			// Log the error to the console
    			console.error(
    				"The following error occurred: "+
    				textStatus, errorThrown
    			);
    		});

    		// Callback handler that will be called regardless
    		// if the request failed or succeeded
    		request_pelanggan.always(function () {
    			// Reenable the inputs
    			inputs.prop("disabled", false);
    			// matikan yang perlu dimatikan seperti sebelumnya
    		});
    	}),
    	$('select[name="provinsi"]').on('change',function(){
    		$.ajax({
    			method:'GET',
    			url:'{$link_api_kota}',
    			data:{prov:this.value}
    		}).done(function(a){
    			$('[name="kabupaten"]').empty(),
    			$('[name="kecamatan"]').empty(),
    			$('<option />',{value:'',text:'Pilih Kab/Kota'}).appendTo(kab),
    			$('<option />',{value:'',text:'Pilih Kecamatan'}).appendTo(kec),
    			$.each(a,function(c,a){
    				var b=a.city_name;
    				a.type=='Kota'&&(b=a.type+' '+a.city_name),
    				$('<option />',{value:a.city_id,text:b}).attr('data-kodepos',a.postal_code).appendTo(kab);
    			});
    		});
    	}),
    	$('select[name="kabupaten"]').on('change',function(){
    		$.ajax({
    			method:'GET',
    			url:'{$link_api_kecamatan}',
    			data:{kota:this.value}
    		}).done(function(a){
    			$('[name="kecamatan"]').empty(),
    			$('<option />',{value:'',text:'Pilih Kecamatan'}).appendTo(kec),
    			$.each(a,function(b,a){
    				$('<option />',{value:a.subdistrict_id,text:a.subdistrict_name}).appendTo(kec);
    			});
    		});
    	});

    	// modal tambah produk

    	function listOrder(){
    		var a=$('.orderan-kosong'),
    			c=$('.customBiaya'),
    			b=$('.list-orderan').data('length');
    		b>1?a.hide():a.show();
    		b>1?c.removeClass('d-none'):c.addClass('d-none');
    	}

    	function newRow(a,b){
    		var c = $('<tr/>');
    		for(var i=0; i<b.length; i++){
    			var d = $('<td/>').append(b[i]);
    			c.append(d);
    		}
    		a.append(c);
    	}

    	function price(n){
    		// untuk menampilkan label harga
    		var a = new Intl.NumberFormat(
    			'id-ID',{
    				style:'currency',
    				currency:'IDR',
    				unitDisplay:'narrow',
    				minimumFractionDigits:0
    				}
    			).format(n);
    		return a;
    	}

    	var pl='';
    	listOrder();

    	// inputan jadwal ikut tipe pesanan: deadline untuk pembuatan, tanggal
    	// ambil/kembali dan jaminan untuk sewa. Data lama bisa punya jadwal tanpa
    	// tipe, jadi saat halaman dibuka field yang tak relevan cuma disembunyikan.
    	function tampilJadwal(hapus) {
    		var t = $('#tipe_pesanan').val(),
    			sewa = $('#rincian_ambil').val() !== '' || $('#rincian_kembali').val() !== '' || $('#rincian_jaminan').val() !== '',
    			buat = t === 'pembuatan' || (!hapus && $('#rincian_deadline').val() !== '');

    		sewa = t === 'sewa' || (!hapus && sewa);

    		$('.tipe-buat').toggleClass('d-none', !buat);
    		$('.tipe-sewa').toggleClass('d-none', !sewa);

    		if (hapus && !buat) {
    			$('#rincian_deadline').val('');
    		}
    		if (hapus && !sewa) {
    			$('#rincian_ambil, #rincian_kembali, #rincian_jaminan').val('');
    		}
    	}

    	tampilJadwal(false);

    	$('#tipe_pesanan').on('change', function() {
    		tampilJadwal(true);
    	});

    	// spesifikasi custom hanya muncul saat ukuran = Custom
    	$('#ukuran').on('change', function() {
    		var custom = $(this).val() === 'custom';
    		$('#customDetail').toggleClass('d-none', !custom);
    		if (!custom) {
    			$('#customDetail .ri-field').val('');
    		}
    	});

    	var fpro=$('#nambahProduk');
    	fpro.on('submit',function(c){
    		c.preventDefault(),
    		c.stopPropagation();
    		var d=fpro.find('[name="kode_produk"]').val(),
    			a=fpro.find('[name="harga_satuan"]').val(),
    			e=fpro.find('[name="ukuran"]').val(),
    			b=fpro.find('[name="QTY"]').val(),
    			f=price(a),
    			g=price(a*b),
    			j = uniqId(),
    			ik = $('<input/>',{'type':'hidden','name': 'produk['+j+'][kode]', 'value': d}),
    			ip = $('<input/>',{'type':'hidden','name': 'produk['+j+'][harga]', 'value': a}),
    			iz = $('<input/>',{'type':'hidden','name': 'produk['+j+'][ukuran]', 'value': e}),
    			iq = $('<input/>',{'type':'hidden','name': 'produk['+j+'][qty]', 'value': b}),
    			bt = $('<button />', {'class' : 'bg-transparent border-0 hapus_row orderan me-1', html:'<span aria-hidden="true"><i class="fal fa-trash-alt h6"></i></span>'}),
    			t = $('<div/>').append(bt).append(d+' ( '+e+' )'),
    			tb = $('.list-orderan'),
    			p = $('<div/>', {'class' : 'text-end', 'data-uang1': a*b}).append(g).append(ik).append(ip).append(iz).append(iq);

    		// simpan spesifikasi custom (kalau ukuran = custom) sebagai hidden input
    		if (e === 'custom') {
    			fpro.find('#customDetail .ri-field').each(function(){
    				var k = $(this).data('key'),
    					v = $.trim($(this).val() || '');
    				if (k && v !== '') {
    					p.append($('<input/>', {'type':'hidden','name':'produk['+j+'][rincian]['+k+']','value':v}));
    				}
    			});
    		}

    		// ukuran terstruktur: nama inputnya baru bisa dibuat sekarang karena
    		// kunci barisnya (j) baru ketemu di sini
    		var uku = UKURAN.nilai('#ukuranModal');
    		$.each(uku, function (i, u) {
    			p.append($('<input/>', {'type':'hidden','name':'produk['+j+'][nilai_ukuran]['+u.key+']','value':u.nilai}));
    		});
    		if (uku.length) {
    			t.append($('<div/>', {'class':'small text-muted'}).text(UKURAN.ringkas('#ukuranModal')));
    		}
    		newRow(tb,[t,f,b,p]);
    		subtotal();

    		pl=$('.list-orderan').data('length'),
    		$('.list-orderan').data('length',pl+1),
    		listOrder(),
    		$('#tambahProduk').modal('hide');
    	}),
    	$(document).on('click','.hapus_row',function(){
    		this.closest('tr').remove(),
    		$(this).hasClass('orderan')&&(
    			pl=$('.list-orderan').data('length'),
    			$('.list-orderan').data('length',
    			ps-1),
    			listOrder(),
    			subtotal()
    		),
    		totalbiaya();
    	});

    	var mPro=document.getElementById('tambahProduk');
    	mPro.addEventListener('hidden.bs.modal',function(){
    		var a=$('#nambahProduk');
    		a.removeClass('was-validated')[0].reset();
    		$('#customDetail').addClass('d-none');
    		UKURAN.kosongkan('#ukuranModal');
    	});

    	function subtotal(){
    		// hitung subtotal produk
    		var r=$('.list-orderan').find('[data-uang1'),
    			a=0;
    		// console.log(r);
    		r.each(function() {
    			a+=Number($(this).data('uang1'));
    			// console.log($(this).data('uang1'));
    		});
    		$('#subTotal').attr('data-subtotal', a).empty().html(price(a));
    		$(document).trigger('data-attribute-changed');

    		$('.customBiaya').show();
    		// grandtotal();
    	}

    	$(document).on('data-attribute-changed', function() {
    		var a = $('#subTotal')[0]['attributes'],
    			b = a.getNamedItem("data-subtotal")['nodeValue'],
    			c = a.getNamedItem("data-totalbiaya")['nodeValue'],
    			d = parseInt(b) + parseInt(c);

    		$('#grandTotal').empty().html(price(d));
    	});

    	function totalbiaya(){
    		var r=$('.listBiaya').find('[data-biaya]'),
    			a=0;
    		// console.log(r);
    		r.each(function() {
    			a+=Number($(this).data('biaya'));
    			// console.log($(this).data('uang1'));
    		});
    		$('#subTotal').attr('data-totalbiaya', a);
    		$(document).trigger('data-attribute-changed');
    		// grandtotal();
    	}

    	// modal tambah biaya

    	var mBia=document.getElementById('biayaOrder');
    	mBia.addEventListener('show.bs.modal',function(f){
    		var a=f.relatedTarget;
    		var c=a.getAttribute('data-judul');
    		var b=a.getAttribute('data-biayaID');
    		var d=mBia.querySelector('.modal-title');
    		var e=mBia.querySelector('.modal-content [name="biayaId"]');
    		d.textContent='Biaya '+c,b=='1'&&$('#biayaHelp').addClass('d-none'),e.value=b;
    	}),
    	mBia.addEventListener('hidden.bs.modal',function(b){
    		var a=$('#tambahBiaya');
    		a.removeClass('was-validated')[0].reset(),
    		$('#biayaHelp').removeClass('d-none');
    	});

    	var fBia=$('#tambahBiaya');
    	fBia.on('submit',function(f){
    		f.preventDefault(),
    		f.stopPropagation();

    		var a=fBia.find('[name="nominal_biaya"]').val();
    		var c=fBia.find('[name="label_biaya"]').val();
    		var g=fBia.find('[name="biayaId"]').val();
    		var d='';
    		c!=''&&(d='<span class="text-muted ms-1">'+c+'</span>');
    		var b='';
    		switch(parseInt(g)){
    			case 1:b='Ongkir';
    			break;

    			default:b='Lain-lain';
    		}

    		if(parseInt(a)!=0){
    			var e='',
    				u=parseInt(a);
    			u<0&&(e='text-danger');
    			var h=price(u),
    				j = uniqId(),
    				id = $('<input/>',{'type':'hidden','name': 'biaya['+j+'][biaya_id]', 'value': g}),
    				no = $('<input/>',{'type':'hidden','name': 'biaya['+j+'][nominal]', 'value': u}),
    				la = $('<input/>',{'type':'hidden','name': 'biaya['+j+'][label]', 'value': c}),
    				r=$('<tr/>'),
    				c1=$('<td/>', {'colspan': '3', 'class': 'text-end'}).append('<button type="button" class="bg-transparent border-0 hapus_row me-1" aria-label="Close"><span aria-hidden="true"><i class="fal fa-trash-alt h6"></i></span></button>'+b+d),
    				c2=$('<td/>').append('<div data-biaya="'+u+'" class="text-end '+e+'">'+h+'</div>').append(id).append(no).append(la),
    				q = r.append(c1).append(c2);
    			$(q).appendTo('.listBiaya');
    			totalbiaya();
    		}
    		$('#biayaOrder').modal('hide');
    	});

    	// main form
    	var emef=$('#iForm');
    	var request;
    	emef.on('submit',function(f){
    		f.preventDefault(),
    		f.stopPropagation();

    		// Abort any pending request
    		if (request) {
    			request.abort();
    		}
    		// setup some local variables
    		var f = $(this);

    		// Let's select and cache all the fields
    		var inputs = f.find("input, select, button, textarea");

    		// Serialize the data in the form
    		var serializedData = f.serialize();

    		// Let's disable the inputs for the duration of the Ajax request.
    		// Note: we disable elements AFTER the form data has been serialized.
    		// Disabled form elements will not be serialized.
    		inputs.prop("disabled", true);

    		// Fire off the request to /form.php
    		request = $.ajax({
    			url: "{$link_api_invoice}",
    			type: "post",
    			data: serializedData
    		});

    		// Callback handler that will be called on success
    		request.done(function (response, textStatus, jqXHR){
    			// Log a message to the console
    			document.location.href = response.url;
    		});

    		// Callback handler that will be called on failure
    		request.fail(function (jqXHR, textStatus, errorThrown){
    			//
    			// console.log( Object.keys(jqXHR['responseJSON']).length);
    			$( Object.entries(jqXHR['responseJSON']) ).each(function( i,v ) {
    				var Tid = 't-'+uniqId();

    				// console.log(v);
    				$('#lTo').append(makeToast(Tid, v[1], 'Galat'));
    				$('#'+Tid).toast('show');
    			});


    			var myToastEl = document.getElementsByClassName('toast')[0];
    			myToastEl.addEventListener('hidden.bs.toast', function () {
    				// do something...
    				$(this).remove();
    			});

    		});

    		// Callback handler that will be called regardless
    		// if the request failed or succeeded
    		request.always(function () {
    			// Reenable the inputs
    			inputs.prop("disabled", false);

    		});
    	});

    	function makeToast(id,text,status) {
    		return `<div id="`+id+`" class="toast" role="alert" aria-live="assertive" aria-atomic="true" data-delay="5000">
    			<div class="toast-header">
    				<strong class="me-auto">`+status+`</strong>
    				<button type="button" class="ms-2 mb-1 close" data-bs-dismiss="toast" aria-label="Close">

    				</button>
    			</div>
    			<div class="toast-body">`+text+`</div>
    		</div>`;
    	}

    	// disable hit enter
    	// https://stackoverflow.com/a/895231/2094645
    	$(document).on("keydown", ":input:not(textarea)", function(event) {
    		if(event.keyCode == 13) {
    			event.preventDefault();
    			return false;
    		}
    	});

    	function uniqId() {
    		return Math.round(new Date().getTime() + (Math.random() * 100));
    	}

    });

    JS;

$packer    = new Tholu\Packer\Packer($js, 'Normal', true, false, true);
$packed_js = $packer->pack();
echo '<script>' . $packed_js . '</script>';
?>
<?= $this->endSection() ?>
