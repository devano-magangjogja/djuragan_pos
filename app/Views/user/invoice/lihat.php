<?php

use App\Libraries\Ongkir;
use CodeIgniter\I18n\Time;

$ongkir   = new Ongkir();
$pager    = \Config\Services::pager();
$sekarang = new Time('now');
$session  = \Config\Services::session();

?>
<?= $this->extend('template/main') ?>

<?= $this->section('content') ?>
<?= $this->include('user/navbar') ?>

<div class="container-xxl">

    <h1 class="h3 mt-5"><?= esc($title) ?></h1>

    <nav aria-label="breadcrumb">
        <ol class="breadcrumb p-0">
            <li class="breadcrumb-item"><?= anchor('/', 'Dasbor') ?></li>
            <li class="breadcrumb-item"><?= anchor('user/invoices', 'Orderan') ?></li>
            <li class="breadcrumb-item active" aria-current="page">Semua Orderan</li>
        </ol>
    </nav>

    <div class="wrap-btn-filter mb-3">
        <?php
        $arr_button = [
            [
                'link'  => ['user', 'invoices', 'lihat', $juragan, 'semua'],
                'title' => 'Semua Orderan <span class="badge rounded-pill bg-danger" id="counterSemua">0</span>',
                'attr'  => [
                    'class' => 'mb-2 btn rounded-pill me-1 btn-' . ($hal === 'semua' ? 'primary' : 'outline-secondary'),
                    'title' => 'Semua Orderan',
                ],
            ],
            [
                'link'  => ['user', 'invoices', 'lihat', $juragan, 'cek-bayar'],
                'title' => 'Cek Pembayaran <span class="badge rounded-pill bg-danger" id="counterCekBayar">0</span>',
                'attr'  => [
                    'class' => 'mb-2 btn rounded-pill me-1 btn-' . ($hal === 'cek-bayar' ? 'primary' : 'outline-secondary'),
                    'title' => 'Cek Pembayaran',
                ],
            ],
            [
                'link'  => ['user', 'invoices', 'lihat', $juragan, 'belum-proses'],
                'title' => 'Belum Proses <span class="badge rounded-pill bg-danger" id="counterBelumProses">0</span>',
                'attr'  => [
                    'class' => 'mb-2 btn rounded-pill me-1 btn-' . ($hal === 'belum-proses' ? 'primary' : 'outline-secondary'),
                    'title' => 'Belum Proses',
                ],
            ],
            [
                'link'  => ['user', 'invoices', 'lihat', $juragan, 'dalam-proses'],
                'title' => 'Dalam Proses <span class="badge rounded-pill bg-danger" id="counterDalamProses">0</span>',
                'attr'  => [
                    'class' => 'mb-2 btn rounded-pill me-1 btn-' . ($hal === 'dalam-proses' ? 'primary' : 'outline-secondary'),
                    'title' => 'Dalam Proses',
                ],
            ],
            [
                'link'  => ['user', 'invoices', 'lihat', $juragan, 'selesai'],
                'title' => 'Orderan Selesai <span class="badge rounded-pill bg-danger" id="counterSelesai">0</span>',
                'attr'  => [
                    'class' => 'mb-2 btn rounded-pill me-1 btn-' . ($hal === 'selesai' ? 'primary' : 'outline-secondary'),
                    'title' => 'Orderan Selesai',
                ],
            ],
        ];

        foreach ($arr_button as $key => $b) {
            echo anchor($b['link'], $b['title'], $b['attr']);
        }
        ?>

        <a class="mb-2 btn btn-warning rounded-pill me-1" data-bs-target="#modalCari" data-bs-toggle="modal" href="#!"><i class="fal fa-search"></i></a>
    </div>

    <?php
    foreach ($orderan['data'] as $pesanan) { ?>
        <div class="card order-card rounded-4 shadow-sm mb-4">
            <div class="card-body p-3 p-lg-4">
                <div class="order-head d-flex flex-column flex-sm-row justify-content-between align-items-start gap-3">
                    <div class="order-head-main">
                        <?php $time = Time::createFromFormat('Y-m-d', $pesanan->tanggal_pesan); ?>
                        <div class="order-head-top">
                            <span class="order-head-badge"><i class="fal fa-receipt"></i></span>

                            <div class="order-head-id">
                                <h5 class="card-title mb-0">#<?= esc($pesanan->seri) ?></h5>
                                <p class="text-muted small mb-0">( <?= $time->toLocalizedString('EEEE, d MMMM yyyy') ?> )</p>
                            </div>

                            <span class="badge rounded-pill order-head-pill text-bg-<?= esc(status_pembayaran($pesanan->pembayaran, $pesanan->status_pembayaran, 'l_class')) ?>">
                                <?= esc(status_pembayaran($pesanan->pembayaran, $pesanan->status_pembayaran, 'label')) ?>
                            </span>

                            <div class="order-head-actions">
                                <a class="btn btn-sm order-head-btn" href="<?= site_url('download/invoice/' . $pesanan->seri) ?>" target="_blank" rel="noopener" title="Cetak pesanan ini">
                                    <i class="fal fa-print"></i> <span class="d-none d-md-inline">Cetak</span>
                                </a>
                                <button type="button" class="btn btn-sm order-head-btn salinTautan" data-link="<?= esc(site_url('download/invoice/' . $pesanan->seri), 'attr') ?>" title="Salin tautan invoice">
                                    <i class="fal fa-copy"></i><span class="visually-hidden">Salin tautan</span>
                                </button>
                            </div>
                        </div>

                        <div class="order-head-meta">
                            <span class="order-head-meta-item">
                                <i class="fal fa-store"></i>
                                <span class="text-muted text-lowercase">Juragan:</span> <?= anchor('user/invoices/lihat/' . $pesanan->juragan->slug, esc($pesanan->juragan->nama)) ?>
                            </span>
                            <span class="order-head-meta-item">
                                <i class="fal fa-user"></i>
                                <span class="text-muted text-lowercase">Admin/CS:</span> <?= esc($pesanan->pengguna->nama) ?>
                            </span>
                        </div>
                    </div>

                    <div>
                        <ul class="list-inline mb-0 timeliner">
                            <li class="list-inline-item me-0 position-relative start full" data-bs-toggle="tooltip" data-bs-placement="top" title="Pesanan Ditambahkan">
                                <div class="d-flex justify-content-center">
                                    <div class="text-center">
                                        <i class="fal fa-plus-circle icon d-block"></i>
                                        <?= '<span><abbr title="' . esc($time->humanize()) . '">' . esc($time->day . '/' . $time->month) . '</abbr></span>' ?>
                                    </div>
                                </div>
                            </li>

                            <?= status_pembayaran($pesanan->pembayaran, $pesanan->status_pembayaran) ?>

                            <?php
                            $dipacking = false;

                            foreach ($pesanan->status as $status) {
                                echo status_orderan($status->status, $status->tanggal_masuk, $status->tanggal_selesai, $status->keterangan_masuk, $status->keterangan_selesai);

                                if (isset($status->status) && $status->status === '7') {
                                    if ($status->tanggal_selesai !== null) {
                                        $dipacking = true;
                                    }
                                }
                            }
                            ?>
                            <?= ($dipacking ? status_pengiriman($pesanan->pengiriman, $pesanan->status_pengiriman) : '') ?>
                        </ul>
                    </div>
                </div>
                <hr class="my-3" />

                <div class="row g-3 g-lg-4">
                    <section class="col-12 col-lg-4">
                        <div class="order-block h-100">
                            <h6 class="order-block-title">
                                <i class="fal fa-user-circle"></i>
                                <?= ($pesanan->kirimKepada_id === $pesanan->pemesan_id ? 'Pemesan / Kirim Kepada' : 'Pemesan') ?>
                            </h6>

                            <?php
                            $pelanggan = $pesanan->pelanggan;
                            ?>
                            <div class="order-block-body">
                                <span class="d-block fw-bold"><?= strtoupper($pelanggan->nama); ?></span>
                                <span class="d-block">
                                    <?php
                                    $i = 0;

                                    foreach (json_decode($pelanggan->hp) as $hp) {
                                        if ($i === 1) {
                                            echo '<span class="visually-hidden">/</span>';
                                        }
                                        echo '<span class="badge bg-secondary me-1 fw-light">' . esc($hp) . '</span>';
                                        $i++;
                                    }
                                    ?>
                                </span>
                                <span class="d-block">
                                    <?php
                                    if ($pelanggan->cod === '1') {
                                        echo 'C.O.D';
                                    } else {
                                        echo strtoupper(esc($pelanggan->alamat)) . '<br/>' .
                                            strtoupper(esc($ongkir->kecamatan($pelanggan->kabupaten, $pelanggan->kecamatan)['subdistrict_name'])) . ', ' .
                                            strtoupper(esc($ongkir->kota($pelanggan->provinsi, $pelanggan->kabupaten)['city_name'])) . ', ' .
                                            strtoupper(esc($ongkir->provinsi($pelanggan->provinsi)['province_name'])) .
                                            esc($pelanggan->kodepos === '0' ? '' : ' - ' . $pelanggan->kodepos);
                                    }
                                    ?>
                                </span>
                            </div>

                            <?php
                            if ($pesanan->kirimKepada_id !== $pesanan->pemesan_id) {
                                $kirimKe = $pesanan->kirimKe; ?>
                                <h6 class="order-block-title mt-3"><i class="fal fa-map-marker-alt"></i> Kirim Kepada</h6>
                                <div class="order-block-body">
                                    <span class="d-block fw-bold"><?= strtoupper($kirimKe->nama); ?></span>
                                    <span class="d-block">
                                        <?php
                                        $i = 0;

                                        foreach (json_decode($kirimKe->hp) as $hp) {
                                            if ($i === 1) {
                                                echo '<span class="visually-hidden">/</span>';
                                            }
                                            echo '<span class="badge bg-secondary me-1 fw-light">' . esc($hp) . '</span>';
                                            $i++;
                                        }
                                        ?>
                                    </span>
                                    <span class="d-block">
                                        <?php
                                        if ($kirimKe->cod === '1') {
                                            echo 'C.O.D';
                                        } else {
                                            echo strtoupper(esc($kirimKe->alamat)) . '<br/>' .
                                                strtoupper(esc($ongkir->kecamatan($kirimKe->kabupaten, $kirimKe->kecamatan)['subdistrict_name'])) . ', ' .
                                                strtoupper(esc($ongkir->kota($kirimKe->provinsi, $kirimKe->kabupaten)['city_name'])) . ', ' .
                                                strtoupper(esc($ongkir->provinsi($kirimKe->provinsi)['province_name'])) .
                                                esc($kirimKe->kodepos === '0' ? '' : ' - ' . $kirimKe->kodepos);
                                        }
                                        ?>
                                    </span>
                                </div>
                            <?php } ?>

                            <h6 class="order-block-title mt-3"><i class="fal fa-inbox-in"></i> Asal Orderan</h6>
                            <div class="order-block-body"><?= label_asal($pesanan->source->id, $pesanan->source->label) ?></div>
                        </div>
                    </section>

                    <section class="col-12 col-md-6 col-lg-3">
                        <div class="order-block h-100">
                            <h6 class="order-block-title"><i class="fal fa-tshirt"></i> Produk</h6>

                            <?php
                            $count_barang = 0;
                            $harga_barang = 0;
                            $dibeli       = $pesanan->barang;

                            foreach ($pesanan->barang as $b) {
                                $count_barang += $b->qty;
                                $harga_barang += $b->qty * $b->harga;
                            }

                            $wajib_bayar = $harga_barang;
                            ?>

                            <ul class="order-list">
                                <?php
                                foreach ($pesanan->barang as $b) {
                                    echo '<li>';
                                    echo '<span class="order-list-name">' . strtoupper($b->kode) . ' (' . strtoupper($b->ukuran) . ')</span>';
                                    echo '<span class="order-list-qty">' . $b->qty . ' pcs</span>';

                                    $content = '<div class=\'text-end\'>';
                                    $content .= 'harga @: <strong>' . number_to_currency($b->harga, 'IDR') . '</strong>';
                                    if ($b->qty > 1) {
                                        $content .= '<br/>harga @ x ' . $b->qty . ': <strong>' . number_to_currency($b->harga * $b->qty, 'IDR') . '</strong>';
                                    }
                                    $content .= '</div>';

                                    echo form_button(
                                        [
                                            'data-bs-toggle'  => 'popHarga',
                                            'data-bs-content' => $content,
                                            'content'         => '<i class="fal fa-info-circle"></i> <span class="visually-hidden">info</span>',
                                            'class'           => 'btn btn-link btn-sm text-secondary p-0',
                                        ]
                                    );

                                    $rincian_item = daftar_rincian($b->rincian ?? null, 'produk');

                                    if ($rincian_item !== []) {
                                        echo '<div class="order-specs">';

                                        foreach ($rincian_item as $item) {
                                            echo '<span class="order-spec"><i class="fal ' . $item['ikon'] . '"></i> '
                                                . '<span class="order-spec-label">' . esc($item['label']) . '</span>'
                                                . '<span class="order-spec-value">' . esc($item['nilai']) . '</span></span>';
                                        }

                                        echo '</div>';
                                    }

                                    echo '</li>';
                                }
                                ?>
                            </ul>
                            <div class="order-list-total">
                                total: <span class="badge rounded-pill bg-dark"><?= $count_barang; ?></span> pcs
                            </div>
                        </div>
                    </section>

                    <section class="col-12 col-md-6 col-lg-5">
                        <div class="order-block h-100">
                            <h6 class="order-block-title"><i class="fal fa-receipt"></i> Info Biaya</h6>

                            <?php
                            foreach ($pesanan->biaya as $c) {
                                $wajib_bayar += $c->nominal;
                            }

                            $sudah_bayar = status_pembayaran($pesanan->pembayaran, $pesanan->status_pembayaran, 'sudah_bayar');
                            ?>

                            <div class="order-total list-group-item-<?= status_pembayaran($pesanan->pembayaran, $pesanan->status_pembayaran, 'l_class'); ?>">
                                <span class="order-total-label">Wajib Bayar</span>
                                <span class="order-total-nominal"><?= number_to_currency($wajib_bayar, 'IDR'); ?></span>
                            </div>

                            <ul class="order-cost">
                                <li>
                                    <span class="order-cost-label">Harga Produk</span>
                                    <span class="order-cost-nominal"><?= number_to_currency($harga_barang, 'IDR'); ?></span>
                                </li>

                                <?php
                                foreach ($pesanan->biaya as $c) { ?>
                                    <li>
                                        <span class="order-cost-label">
                                            <?php
                                            $biaya = 'Lainnya';
                                            if ($c->biaya_id === 1) {
                                                $biaya = 'Ongkir';
                                            }
                                            $label = $c->label;
                                            if ($c->label !== 'null' && $c->biaya_id !== 1) {
                                                $biaya = $c->label;
                                                $label = '';
                                            } elseif ($c->label === 'null') {
                                                $label = '';
                                            } ?>
                                            <span class="fw-bold"><?= $biaya; ?></span>
                                            <?= $label; ?>
                                        </span>
                                        <span class="order-cost-nominal <?= ($c->nominal < 0 ? 'text-danger' : ''); ?>"><?= number_to_currency($c->nominal, 'IDR') ?></span>
                                    </li>
                                <?php
                                } ?>

                                <?php
                                if ($sudah_bayar > 0) { ?>
                                    <li class="order-cost-sep">
                                        <span class="order-cost-label">
                                            <span class="fw-bold">Sudah</span>&nbsp;Bayar
                                            <?= form_button([
                                                'class'          => 'text-dark ms-1 pesanBayar',
                                                'content'        => '<i class="fal fa-info-circle"></i> <span class="visually-hidden">info</span>',
                                                'data-invoice'   => $pesanan->id_invoice,
                                                'data-juragan'   => $pesanan->juragan_id,
                                                'data-bs-target' => '#modalBayar',
                                                'data-bs-toggle' => 'modal',
                                                'style'          => 'border:none; background:transparent',
                                            ]); ?>
                                        </span>
                                        <span class="order-cost-nominal"><?= number_to_currency($sudah_bayar, 'IDR'); ?></span>
                                    </li>
                                <?php
                                }
                                if (($wajib_bayar - $sudah_bayar) > 0) { ?>
                                    <li>
                                        <span class="order-cost-label"><span class="fw-bold">Kurang</span>&nbsp;Bayar</span>
                                        <span class="order-cost-nominal text-danger"><?= number_to_currency(-($wajib_bayar - $sudah_bayar), 'IDR'); ?></span>
                                    </li>
                                <?php
                                }
                                if ($sudah_bayar > $wajib_bayar) { ?>
                                    <li>
                                        <span class="order-cost-label"><span class="fw-bold">Lebih</span>&nbsp;Bayar</span>
                                        <span class="order-cost-nominal"><?= number_to_currency(($sudah_bayar - $wajib_bayar), 'IDR'); ?></span>
                                    </li>
                                <?php
                                } ?>
                            </ul>
                        </div>
                    </section>

                </div>

                <?php $rincian_pesanan = daftar_rincian($pesanan->rincian ?? null, 'pesanan'); ?>
                <?php if ($rincian_pesanan !== []) { ?>
                    <div class="order-detail mt-3">
                        <h6 class="order-block-title"><i class="fal fa-list-check"></i> Detail Pesanan</h6>
                        <div class="order-detail-grid">
                            <?php foreach ($rincian_pesanan as $item) { ?>
                                <div class="order-detail-item">
                                    <span class="order-detail-label"><i class="fal <?= $item['ikon'] ?>"></i> <?= esc($item['label']) ?></span>
                                    <span class="order-detail-value"><?= esc($item['nilai']) ?></span>
                                </div>
                            <?php } ?>
                        </div>
                    </div>
                <?php } ?>

                <?php if ($pesanan->keterangan !== null && trim($pesanan->keterangan) !== '') { ?>
                    <div class="order-note mt-3">
                        <h6 class="order-block-title"><i class="fal fa-clipboard-list"></i> Keterangan</h6>
                        <p class="keterangan mb-0" id="keterangan-<?= esc($pesanan->id_invoice) ?>">
                            <?= nl2br(esc($pesanan->keterangan)) ?>
                        </p>
                    </div>
                <?php } ?>

                <?php if ($pesanan->status_pengiriman !== '1' && $pesanan->pengiriman !== []) { ?>
                    <div class="mt-3">
                        <h6 class="order-block-title"><i class="fal fa-truck-fast"></i> Resi</h6>
                        <div class="order-resi">
                            <?php
                            foreach ($pesanan->pengiriman as $key => $kirim) { ?>
                                <?php $tanggal_kirim = Time::createFromTimestamp($kirim->tanggal_kirim); ?>
                                <div class="order-resi-card">
                                    <div class="order-resi-kurir">
                                        <span class="text-truncate"><?= esc($kirim->kurir) ?></span>
                                    </div>
                                    <div class="order-resi-nomor"><?= $kirim->resi; ?></div>
                                    <div class="order-resi-meta">
                                        <?= $tanggal_kirim->toLocalizedString('EEEE, d MMMM yyyy') ?>
                                        <?php if ($kirim->ongkir > 0) { ?>
                                            <br>ongkir fix (<?= esc($kirim->qty . 'pcs') ?>): <u><?= number_to_currency($kirim->ongkir, 'IDR') ?></u>
                                        <?php } ?>
                                    </div>
                                </div>
                            <?php
                            } ?>
                        </div>
                    </div>
                <?php } ?>

                <hr class="my-3" />

                <!-- Example split danger button -->
                <div class="btn-group">
                    <?= form_button([
                        'class'          => 'btn btn-outline-secondary tambahBayar',
                        'content'        => 'Tambah Pembayaran',
                        'data-invoice'   => $pesanan->id_invoice,
                        'data-juragan'   => $pesanan->juragan_id,
                        'data-kurang'    => $wajib_bayar - $sudah_bayar,
                        'data-seri'      => $pesanan->seri,
                        'data-bs-target' => '#modalTambahBayar',
                        'data-bs-toggle' => 'modal',
                    ]) ?>
                    <button type="button" class="btn btn-outline-secondary dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown" aria-expanded="false">
                        <span class="visually-hidden">Toggle Dropdown</span>
                    </button>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" target="_blank" href="<?= site_url('download/invoice/' . $pesanan->seri); ?>">Unduh Invoice (PDF)</a></li>
                        <li>
                            <hr class="dropdown-divider" />
                        </li>
                        <li>
                            <?= form_button([
                                'class'   => 'dropdown-item belumFungsi',
                                'content' => 'Sunting',
                            ]); ?>
                        </li>
                    </ul>
                </div>

                <?php if ($pesanan->status_pembayaran !== '1') {
                    if ($pesanan->status_pembayaran === '2' || $pesanan->status_pembayaran === '3') {
                        // tombol cek pembayaran
                        // hanya tampil jika ada pembayaran yang perlu dicek
                        echo form_button([
                            'class'          => 'btn btn-warning ms-1 pesanBayar',
                            'content'        => '<i class="fal fa-wallet"></i> Cek Pembayaran',
                            'data-invoice'   => $pesanan->id_invoice,
                            'data-juragan'   => $pesanan->juragan_id,
                            'data-bs-target' => '#modalBayar',
                            'data-bs-toggle' => 'modal',
                        ]);
                    }
                } ?>
            </div>
        </div>
    <?php
    }
    ?>

    <?= $pager->makeLinks($page, $limit, $orderan['totalPage'], 'front_full') ?>

</div>

<?= $this->endSection() ?>

<?= $this->section('modal') ?>

<!-- Modal orderan status-->
<div class="modal fade" id="modalProgress" tabindex="-1" aria-labelledby="modalProgressLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <?= form_open('', ['class' => 'modal-content', 'id' => 'newStatus'], ['id_invoice' => '']) ?>
        <div class="modal-header">
            <h5 class="modal-title" id="modalProgressLabel">Status Proses Orderan</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">

            </button>
        </div>
        <div class="modal-body">
            <div class="mb-3">
                <?= form_label('Pilih Status', 'status', ['class' => 'form-label']); ?>
                <div class="input-group mb-3">
                    <?php
                    $list_status = [
                        ''  => 'Pilih status',
                        '1' => 'Data Pesanan',
                        '2' => 'Bahan Produk',
                        '3' => 'Sablon',
                        '4' => 'Bordir',
                        '5' => 'Penjahit',
                        '6' => 'QC',
                        '7' => 'Packing',
                    ];
                    echo form_dropdown('status', $list_status, '', ['class' => 'form-select', 'id' => 'status', 'required' => '']);
                    echo form_dropdown('stat', ['' => 'Pilih', '1' => 'Ada', '0' => 'Tidak Ada'], '', ['class' => 'form-select', 'id' => 'stat', 'required' => '', 'disabled' => '']);
                    ?>
                </div>
            </div>

            <div class="mb-3">
                <?= form_label('Note / Keterangan', 'keterangan', ['class' => 'form-label']) ?>
                <?= form_input(['name' => 'keterangan', 'id' => 'keterangan', 'class' => 'form-control', 'placeholder' => 'opsional']) ?>
            </div>

            <div class="bg-warning p-2 border rounded">
                Perlu diingat, penambahan "status proses orderan" ini tidak bisa diubah.
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-link text-decoration-none" data-bs-dismiss="modal">Batal</button>
            <button type="submit" class="btn btn-primary">Simpan</button>
        </div>
        <?= form_close(); ?>
    </div>
</div>

<!-- Modal cek pembayaran -->
<div class="modal fade" id="modalBayar" tabindex="-1" aria-labelledby="modalBayarLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalBayarLabel">Info Pembayaran</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">

                </button>
            </div>
            <div class="modal-body">
                <div id="cek">...</div>
            </div>
        </div>
    </div>
</div>

<!-- Modal tambah pembayaran -->
<div class="modal fade" id="modalTambahBayar" tabindex="-1" aria-labelledby="modalTambahBayarLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTambahBayarLabel">Tambah Pembayaran</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">

                </button>
            </div>
            <div class="modal-body">
                <div class="bg-warning p-2 rounded mt-2">
                    Perlu diingat, detail pembayaran tidak bisa dihapus atau diubah
                </div>

                <div class="tab-content mt-3" id="myTabContent">
                    <?= form_open('', ['id' => 'tambahPembayaran'], ['invoice_id' => '']) ?>
                    <div class="row mb-0 gx-2">
                        <div class="col-6 form-group mb-3">
                            <?= form_label('Tujuan Bayar', 'tujuan', ['class' => 'form-label']) ?>
                            <?= form_dropdown('sumber_dana', [], '', ['class' => 'form-select', 'required' => '', 'id' => 'tujuan']) ?>
                        </div>
                        <div class="col-6 form-group mb-3">
                            <?= form_label('Tanggal Bayar', 'tanggal_bayar', ['class' => 'form-label']) ?>
                            <?= form_input('tanggal_pembayaran', set_value('tanggal_pembayaran', $sekarang->toDateString()), ['class' => 'form-control', 'id' => 'tanggal_pembayaran', 'required' => '', 'max' => $sekarang->toDateString()], 'date') ?>
                        </div>
                        <div class="col-6 form-group mb-3">
                            <?= form_label('Jumlah Dana', 'jumlah_dana', ['class' => 'form-label']) ?>
                            <?= form_input(['name' => 'total_pembayaran', 'class' => 'form-control', 'id' => 'jumlah_dana', 'required' => '', 'placeholder' => '200000', 'type' => 'number', 'min' => '1']) ?>
                        </div>
                    </div>
                    <hr>
                    <button class="btn btn-primary" type="submit">Simpan</button>
                    <?= form_close(); ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal pencarian invoice -->
<div class="modal fade" id="modalCari" tabindex="-1" aria-labelledby="modalCariLabel" aria-hidden="true">
    <div class="modal-dialog modal-md modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-body">
                <?= form_open('user/invoices/lihat/' . $juragan . '/semua', ['method' => 'get']) ?>
                <div class="row g-2 align-items-center">
                    <div class="col-4">
                        <?= form_label('Status Pembayaran', 'status_pembayaran', ['class' => 'form-label visually-hidden']) ?>
                        <?php
                        $opsi_pembayaran = [
                            ''   => 'Opsi Pembayaran',
                            '1'  => 'Belum bayar',
                            '2A' => 'Tunggu Konfirmasi Pembayaran',
                            '4'  => 'Cicilan / Kredit',
                            '5'  => 'Ada kelebihan',
                            '6'  => 'Sudah lunas',
                        ];
                        echo form_dropdown('cari[pembayaran]', $opsi_pembayaran, '', ['class' => 'form-select mb-2 me-sm-2']);
                        ?>
                    </div>

                    <div class="col-4">
                        <div class="col-12">
                            <?= form_label('Status Orderan', 'status_orderan', ['class' => 'form-label visually-hidden']) ?>
                            <?php
                            $opsi_orderan = [
                                ''  => 'Opsi Orderan',
                                '1' => 'Belum diproses',
                                '2' => 'Sedang/sudah diproses',
                                '3' => 'Dibatalkan',
                            ];
                            echo form_dropdown('cari[orderan]', $opsi_orderan, '', ['class' => 'form-select mb-2 me-sm-2']);
                            ?>
                        </div>
                    </div>

                    <div class="col-4">
                        <?= form_label('Status Pengiriman', 'status_pengiriman', ['class' => 'form-label visually-hidden']) ?>
                        <?php
                        $opsi_pengiriman = [
                            ''   => 'Opsi pengiriman',
                            '1'  => 'Belum dikirim',
                            '2A' => 'Dikirim',
                        ];
                        echo form_dropdown('cari[pengiriman]', $opsi_pengiriman, '', ['class' => 'form-select mb-2 me-sm-2']);
                        ?>
                    </div>
                </div>

                <div class="input-group mb-2">
                    <?php
                    $opsi_kolom = [
                        ''              => 'Opsi Pencarian',
                        'nama'          => 'Nama Pelanggan',
                        'hp'            => 'HP Pelanggan',
                        'faktur'        => 'Kode Invoice',
                        'tanggal_pesan' => 'Tanggal Pesan',
                        'kode'          => 'Kode Produk',
                    ];
                    echo form_dropdown('cari[kolom]', $opsi_kolom, '', ['class' => 'form-select']);
                    echo form_input(['name' => 'cari[q]', 'class' => 'form-control allow-enter', 'style' => 'flex:2', 'placeholder' => 'cari .....']);
                    ?>
                    <div class="text-muted">untuk pencarian berdasarkan tanggal, gunakan format seperti: <code>2020-09-20</code></div>
                </div>

                <div class="col-12">
                    <button type="submit" class="btn btn-primary">Submit</button>
                </div>
                <?= form_close() ?>

            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('js') ?>
<script defer src="https://kit.fontawesome.com/22a9d93fa2.js" data-auto-replace-svg="nest" crossorigin="anonymous"></script>
<?php

$current_user_id         = $session->get('id');
$link_api_get_bank       = site_url('api/juragan/all/');
$link_api_get_pembayaran = site_url('user/invoices/info_pembayaran');
$link_api_juragan        = site_url('api/juragan/by_user/');
$link_get_status_invoice = site_url('admin/invoices/detail_status/');
$link_hapus_orderan      = site_url('user/invoices/hapus_orderan/');
$link_invoice            = site_url('user/invoices/lihat/');
$link_tambah_pembayaran  = site_url('user/invoices/simpan_pembayaran');
$link_api_notif          = site_url('api/notifikasi/');
$link_api_counter_tab    = site_url('api/invoice/counter_tab/' . $juragan_id);

$js = <<< JS
    $(function() {
    	'use strict';
    	// popover

    	var po = {};
    		po.trigger = 'focus';
    		po.placement = 'right';
    		po.container = 'body';
    		po.html = true;
    		po.selector = '[data-bs-toggle="popHarga"]';
    		po.template = '<div class="popover shadow" role="tooltip"><div class="popover-arrow"></div><div class="popover-body"></div></div>';
    	$('body').popover(po);

    	// tooltips

    	var tt = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    	var ttl = tt.map(function(e) {
    		return new bootstrap.Tooltip(e);
    	});

    	// sidebar

    	var juragan = document.getElementById('juragan');
        if(juragan) {
            juragan.addEventListener('show.bs.offcanvas', function () {
                var id = {$current_user_id};
                $('#listLi').html('');
                $.getJSON('{$link_api_juragan}', { id: id }, function(b){
                    var a=[];
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
    								<a class="d-block text-decoration-none" href="{$link_invoice}`+b.juragan+`/semua?cari[kolom]=faktur&cari[q]=`+b.invoice+`">`+ b.notif+`</a>
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
        notifikasi.addEventListener('show.bs.offcanvas', function () {

            var id = {$current_user_id};
    		$('#notifDisini').empty(),
    		getNotif();
        });

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

    	//
    	$("#status").change(function() {
    		let val = this.value;
    		// alert("Selected value is : " + val);
    		let stat = $('#stat');
    		let v1 = $('#stat option[value=1]');
    		let v0 = $('#stat option[value=0]');

    		stat.val('');

    		if (val !== '') {
    			stat.prop('disabled', false);
    		}
    		else {
    			stat.prop('disabled', true);
    		}

    		if (val == 1) {
    			v1.text('Lengkap');
    			v0.text('Tidak Lengkap');
    		} else if (val == 2) {
    			v1.text('Ada');
    			v0.text('Belum Ada');
    		} else {
    			v1.text('Selesai');
    			v0.text('Masuk');
    		}
    	});

    	// cek pembayaran
    	$('.pesanBayar').on('click',function(){
    		let juragan = $(this).data('juragan'),
    			id = $(this).data('invoice'),
    			dv_list =$("<div/>").addClass('list-group list-group-flushs').attr('id', 'list_pembayaran');

    			$.getJSON('{$link_api_get_pembayaran}', {id:id}, function(bayar){
    			for (let i = 0; i < bayar.length; i++) {
    				var label = $('<div/>').addClass('list-group-item list-group-action');

    				var info = '';
    				var ico = '';
    				var btn = '';

    				switch (bayar[i].status) {
    					case 2:
    						btn = ``;
    						ico = 'times-circle text-danger';
    						info = 'dana tidak ada, sudah cek pada: '+ tanggal(bayar[i].tanggal_cek);
    						break;

    					case 3:
    						btn = ``;
    						ico = 'check-circle text-success';
    						info = 'dana ada, sudah cek pada: '+ tanggal(bayar[i].tanggal_cek);
    						break;

    					default:
    						btn = ``;
    						ico = 'minus-circle text-info';
    						info = 'dana belum dicek';
    						break;
    				}

    				var bta = ``;

    				if (bayar[i].status !== 1) {
    					bta = '';
    				}

    				var bank = bayar[i].atas_nama;
    				if (bayar[i].sumber > 2) {
    					bank = bayar[i].nama + ' (' + bayar[i].atas_nama + ')';
    				}

    				var dv = $(`
    				<div class="d-flex justify-content-between">
    					<div class="d-flex justify-content-start align-items-center">
    						<i class="fal fa-`+ ico +` fa-2x me-3"></i>
    						<div>
    							<span class="me-1">`+ bank.toUpperCase() +`</span> <span class="me-1">`+ bayar[i].nominal +`</span> <span>(`+ tanggal(bayar[i].tanggal_bayar) +`)</span>
    							<div class="text-muted small">`+ info +`</div>
    						</div>
    					</div>
    					`+ bta +`
    				</div>
    				`);

    				label.append(dv);
    				dv_list.append(label);

    				$('#modalBayar #cek').empty().append(dv_list);
    			}
    		});
    	});

    	// tambah pembayaran
    	$('.tambahBayar').on('click',function(){
    		let juragan = $(this).data('juragan'),
    			id = $(this).data('invoice'),
    			kekurangan = $(this).data('kurang'),
    			ttl = $('#myTabContent [name="total_pembayaran"]');

    		// invoice_id
    		$('#myTabContent [name="invoice_id"]').val(id);
    		ttl.val('');
    		if (parseInt(kekurangan)>0) {
    			ttl.val(kekurangan);
    		}

    		// set dropdown sumber_dana
    		$.getJSON('{$link_api_get_bank}', function(data){
    			var banks = data[juragan].bank;
    			var apnd = '<option value="">Pilih Tujuan</option>';
    			for (var i in banks) {
    				apnd += "<option value = '" + banks[i].id + " '>" + banks[i].nama.toUpperCase() + ' (' + banks[i].atas_nama + ')' + " </option>";
    			}
    			$("#modalTambahBayar #tujuan").empty().append(apnd);
    		});
    	});

    	var req_pay,
    		tambah_pay=$('#tambahPembayaran');
    	tambah_pay.on('submit',function(f){
    		f.preventDefault(),
    		f.stopPropagation();

    		// Abort any pending request
    		if (req_pay) {
    			req_pay.abort();
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
    		req_pay = $.ajax({
    			url: "{$link_tambah_pembayaran}",
    			type: "post",
    			data: serializedData
    		});

    		// Callback handler that will be called on success
    		req_pay.done(function (response, textStatus, jqXHR){
    			// Log a message to the console
    			// console.log(response);
    			// redirect
    			document.location.href = response.url;
    		});

    		// Callback handler that will be called on failure
    		req_pay.fail(function (jqXHR, textStatus, errorThrown){
    			//
    			// console.log( Object.keys(jqXHR['responseJSON']).length);
    		});

    		// Callback handler that will be called regardless
    		// if the request failed or succeeded
    		req_pay.always(function () {
    			// Reenable the inputs
    			inputs.prop("disabled", false);

    		});
    	});

    	$('.belumFungsi').on('click',function(){
    		alert('Fungsi ini belum tersedia');
    	});

    	//
    	var maxLength = 7;
    	$(".keterangan").each(function(){
    		var myStr = $(this).html(),
    			lines = myStr.replace(/^\\s+|\\s+$|\\s+(?=\\s)/g, "").split("<br>"); // https://stackoverflow.com/a/18066013/2094645

    		if (lines.length > maxLength) {
    			var newStr = lines.slice(0, maxLength),
    				hideStr = lines.slice(maxLength, lines.length);

    			$(this).empty().html(newStr.join('<br/>'));
    			$(this).append('<a href="javascript:void(0);" class="read-more ms-1">lanjut ...</a>');
    			$(this).append('<span class="more-text"><br/>' + hideStr.join('<br/>') + '</span>');
    		}
    	});
    	$(".read-more").on('click', function(){
    		$(this).siblings(".more-text").contents().unwrap();
    		$(this).remove();
    	});

    	// salin tautan cetak invoice ke clipboard
    	$(".salinTautan").on('click', function(){
    		var tombol  = $(this),
    			tautan  = tombol.data('link'),
    			asali   = tombol.html();

    		navigator.clipboard.writeText(tautan).then(function () {
    			tombol.html('<i class="fal fa-check"></i>');
    			window.setTimeout(function () {
    				tombol.html(asali);
    			}, 1500);
    		});
    	});

    	// disable hit enter
    	// https://stackoverflow.com/a/895231/2094645
    	$(document).on("keydown", ":input:not(textarea):not(.allow-enter)", function(event) {
    		if(event.keyCode == 13) {
    			event.preventDefault();
    			return false;
    		}
    	});

    	// create date from unix
    	function tanggal(unixtime) {
    		var u = new Date(unixtime*1000);

    		return ('0' + u.getDate()).slice(-2) + '/' + ('0' + (u.getMonth()+1)).slice(-2) + '/' + u.getFullYear();
    	};
    });
    // document ready
    document.addEventListener("DOMContentLoaded", function(event) {
        $.getJSON('{$link_api_counter_tab}', {}, function(counter){
            $('#counterSemua').html(counter.semua.text);
            $('#counterCekBayar').html(counter.cek_bayar.text);
            $('#counterBelumProses').html(counter.belum_proses.text);
            $('#counterDalamProses').html(counter.dalam_proses.text);
            $('#counterSelesai').html(counter.selesai.text);
        });
    });
    JS;

$packer    = new Tholu\Packer\Packer($js, 'Normal', true, false, true);
$packed_js = $packer->pack();
echo '<script>' . $packed_js . '</script>';
?>
<?= $this->endSection() ?>
