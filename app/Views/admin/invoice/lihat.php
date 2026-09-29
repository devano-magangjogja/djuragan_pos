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
<?= $this->include('admin/navbar') ?>

<div class="container-xxl">

    <h1 class="h3 mt-5"><?= esc($title) ?></h1>

    <nav aria-label="breadcrumb">
        <ol class="breadcrumb p-0">
            <li class="breadcrumb-item"><?= anchor('/', 'Dasbor') ?></li>
            <li class="breadcrumb-item"><?= anchor('admin/invoices', 'Orderan') ?></li>
            <li class="breadcrumb-item active" aria-current="page">Semua Orderan</li>
        </ol>
    </nav>

    <div class="wrap-btn-filter mb-3">
        <?php
        $arr_button = [
            [
                'link'  => ['admin', 'invoices', 'lihat', $juragan, 'semua'],
                'title' => 'Semua Orderan <span class="badge rounded-pill bg-danger" id="counterSemua">0</span>',
                'attr'  => [
                    'class' => 'mb-2 btn rounded-pill me-1 btn-' . ($hal === 'semua' ? 'primary' : 'outline-secondary'),
                    'title' => 'Semua Orderan',
                ],
            ],
            [
                'link'  => ['admin', 'invoices', 'lihat', $juragan, 'cek-bayar'],
                'title' => 'Cek Pembayaran <span class="badge rounded-pill bg-danger" id="counterCekBayar">0</span>',
                'attr'  => [
                    'class' => 'mb-2 btn rounded-pill me-1 btn-' . ($hal === 'cek-bayar' ? 'primary' : 'outline-secondary'),
                    'title' => 'Cek Pembayaran',
                ],
            ],
            [
                'link'  => ['admin', 'invoices', 'lihat', $juragan, 'belum-proses'],
                'title' => 'Belum Proses <span class="badge rounded-pill bg-danger" id="counterBelumProses">0</span>',
                'attr'  => [
                    'class' => 'mb-2 btn rounded-pill me-1 btn-' . ($hal === 'belum-proses' ? 'primary' : 'outline-secondary'),
                    'title' => 'Belum Proses',
                ],
            ],
            [
                'link'  => ['admin', 'invoices', 'lihat', $juragan, 'dalam-proses'],
                'title' => 'Dalam Proses <span class="badge rounded-pill bg-danger" id="counterDalamProses">0</span>',
                'attr'  => [
                    'class' => 'mb-2 btn rounded-pill me-1 btn-' . ($hal === 'dalam-proses' ? 'primary' : 'outline-secondary'),
                    'title' => 'Dalam Proses',
                ],
            ],
            [
                'link'  => ['admin', 'invoices', 'lihat', $juragan, 'selesai'],
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
        <?php
        // warna pill status dan panel wajib bayar (pengganti text-bg-* dan list-group-item-*)
        $pil = [
            'danger'  => ['pill' => 'bg-red-600 text-white', 'band' => 'bg-red-100 text-red-800'],
            'warning' => ['pill' => 'bg-tunggu text-ink-900', 'band' => 'bg-amber-100 text-amber-800'],
            'primary' => ['pill' => 'bg-blue-600 text-white', 'band' => 'bg-blue-100 text-blue-800'],
            'success' => ['pill' => 'bg-selesai text-white', 'band' => 'bg-emerald-100 text-emerald-800'],
        ];
        $kelas_status = status_pembayaran($pesanan->pembayaran, $pesanan->status_pembayaran, 'l_class');
        ?>
        <div class="mb-4 rounded-[1.5rem] border border-ink-200 bg-white shadow-sm">
            <div class="p-3 lg:p-4">
                <div class="flex flex-col sm:flex-row justify-between items-start gap-4">
                    <div class="min-w-0">
                        <?php $time = Time::createFromFormat('Y-m-d', $pesanan->tanggal_pesan); ?>
                        <div class="flex flex-wrap items-center gap-x-3 gap-y-2">
                            <span class="inline-flex h-[2.6rem] w-[2.6rem] shrink-0 items-center justify-center rounded-[0.9rem] border border-brand-100 bg-brand-50 text-lg text-brand-500"><i class="fal fa-receipt"></i></span>

                            <div class="min-w-0">
                                <h5 class="text-[1.15rem] font-extrabold tracking-[-0.01em] mb-0">#<?= esc($pesanan->seri) ?></h5>
                                <p class="text-ink-500 text-xs mb-0">( <?= $time->toLocalizedString('EEEE, d MMMM yyyy') ?> )</p>
                            </div>

                            <span class="rounded-full px-3 py-1 text-xs font-semibold <?= $pil[$kelas_status]['pill'] ?>">
                                <?= esc(status_pembayaran($pesanan->pembayaran, $pesanan->status_pembayaran, 'label')) ?>
                            </span>

                            <div class="ms-auto flex items-center gap-1 max-sm:ms-0">
                                <a class="inline-flex cursor-pointer items-center gap-1 rounded-[0.7rem] border border-ink-200 bg-ink-50 px-2.5 py-1 text-sm text-ink-700 transition hover:bg-ink-100 hover:text-ink-900" href="<?= site_url('download/invoice/' . $pesanan->seri) ?>" target="_blank" rel="noopener" title="Cetak pesanan ini">
                                    <i class="fal fa-print"></i> <span class="hidden md:inline">Cetak</span>
                                </a>
                                <button type="button" class="inline-flex cursor-pointer items-center gap-1 rounded-[0.7rem] border border-ink-200 bg-ink-50 px-2.5 py-1 text-sm text-ink-700 transition hover:bg-ink-100 hover:text-ink-900 salinTautan" data-link="<?= esc(site_url('download/invoice/' . $pesanan->seri), 'attr') ?>" title="Salin tautan invoice">
                                    <i class="fal fa-copy"></i><span class="sr-only">Salin tautan</span>
                                </button>
                            </div>
                        </div>

                        <div class="mt-2 flex flex-wrap gap-x-5 gap-y-1 text-sm text-ink-600">
                            <span class="inline-flex min-w-0 items-center gap-1 [&>.fal]:text-[0.85rem] [&>.fal]:text-ink-400">
                                <i class="fal fa-store"></i>
                                <span class="text-lowercase text-ink-500">Juragan:</span> <?= anchor('admin/invoices/lihat/' . $pesanan->juragan->slug, esc($pesanan->juragan->nama)) ?>
                            </span>
                            <span class="inline-flex min-w-0 items-center gap-1 [&>.fal]:text-[0.85rem] [&>.fal]:text-ink-400">
                                <i class="fal fa-user"></i>
                                <span class="text-lowercase text-ink-500">Admin/CS:</span> <?= esc($pesanan->pengguna->nama) ?>
                            </span>
                        </div>
                    </div>

                    <div>
                        <ul class="list-inline mb-0 timeliner">
                            <li class="list-inline-item me-0 position-relative start full" data-bs-toggle="tooltip" data-bs-placement="top" title="Pesanan Ditambahkan">
                                <div class="flex justify-center">
                                    <div class="text-center">
                                        <i class="fal fa-plus-circle icon block"></i>
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
                <hr class="my-4 border-0 border-t border-ink-200 opacity-100" />

                <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-12 lg:gap-6">
                    <section class="col-span-full lg:col-span-4">
                        <div class="h-full rounded-2xl border border-ink-200 bg-[#fcfdfe] px-[1.15rem] py-4">
                            <h6 class="mb-2 flex items-center gap-1.5 text-[0.72rem] font-bold uppercase tracking-[0.09em] text-ink-500 [&_.fal]:text-[0.95rem] [&_.fal]:text-ink-400">
                                <i class="fal fa-user-circle"></i>
                                <?= ($pesanan->kirimKepada_id === $pesanan->pemesan_id ? 'Pemesan / Kirim Kepada' : 'Pemesan') ?>
                            </h6>

                            <?php
                            $pelanggan = $pesanan->pelanggan;
                            ?>
                            <div class="text-sm leading-[1.55] text-ink-800">
                                <span class="block font-bold"><?= strtoupper($pelanggan->nama); ?></span>
                                <span class="block">
                                    <?php
                                    $i = 0;

                                    foreach (json_decode($pelanggan->hp) as $hp) {
                                        if ($i === 1) {
                                            echo '<span class="sr-only">/</span>';
                                        }
                                        echo '<span class="me-1 inline-block rounded-md bg-ink-500 px-1.5 py-0.5 text-[0.75em] font-normal text-white">' . esc($hp) . '</span>';
                                        $i++;
                                    }
                                    ?>
                                </span>
                                <span class="block">
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
                                <h6 class="mb-2 flex items-center gap-1.5 text-[0.72rem] font-bold uppercase tracking-[0.09em] text-ink-500 [&_.fal]:text-[0.95rem] [&_.fal]:text-ink-400 mt-4 border-t border-dashed border-ink-200 pt-3"><i class="fal fa-map-marker-alt"></i> Kirim Kepada</h6>
                                <div class="text-sm leading-[1.55] text-ink-800">
                                    <span class="block font-bold"><?= strtoupper($kirimKe->nama); ?></span>
                                    <span class="block">
                                        <?php
                                        $i = 0;

                                        foreach (json_decode($kirimKe->hp) as $hp) {
                                            if ($i === 1) {
                                                echo '<span class="sr-only">/</span>';
                                            }
                                            echo '<span class="me-1 inline-block rounded-md bg-ink-500 px-1.5 py-0.5 text-[0.75em] font-normal text-white">' . esc($hp) . '</span>';
                                            $i++;
                                        }
                                        ?>
                                    </span>
                                    <span class="block">
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

                            <h6 class="mb-2 flex items-center gap-1.5 text-[0.72rem] font-bold uppercase tracking-[0.09em] text-ink-500 [&_.fal]:text-[0.95rem] [&_.fal]:text-ink-400 mt-4 border-t border-dashed border-ink-200 pt-3"><i class="fal fa-inbox-in"></i> Asal Orderan</h6>
                            <div class="text-sm leading-[1.55] text-ink-800"><?= label_asal($pesanan->source->id, $pesanan->source->label) ?></div>
                        </div>
                    </section>

                    <section class="col-span-full md:col-span-1 lg:col-span-3">
                        <div class="h-full rounded-2xl border border-ink-200 bg-[#fcfdfe] px-[1.15rem] py-4">
                            <h6 class="mb-2 flex items-center gap-1.5 text-[0.72rem] font-bold uppercase tracking-[0.09em] text-ink-500 [&_.fal]:text-[0.95rem] [&_.fal]:text-ink-400"><i class="fal fa-tshirt"></i> Produk</h6>

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

                            <ul class="m-0 list-none p-0 text-sm">
                                <?php
                                foreach ($pesanan->barang as $b) {
                                    echo '<li class="flex flex-wrap items-baseline gap-1 py-[0.2rem]">';
                                    echo '<span class="grow">' . strtoupper($b->kode) . ' (' . strtoupper($b->ukuran) . ')</span>';
                                    echo '<span class="shrink-0 font-semibold whitespace-nowrap">' . $b->qty . ' pcs</span>';

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
                                            'content'         => '<i class="fal fa-info-circle"></i> <span class="sr-only">info</span>',
                                            'class'           => 'cursor-pointer border-0 bg-transparent p-0 text-xs text-ink-500 hover:underline',
                                        ]
                                    );

                                    $rincian_item = daftar_rincian($b->rincian ?? null, 'produk');

                                    if ($rincian_item !== []) {
                                        echo '<div class="basis-full mb-[0.15rem] mt-[0.3rem] flex flex-wrap gap-[0.3rem] pl-[1.1rem]">';

                                        foreach ($rincian_item as $item) {
                                            echo '<span class="inline-flex max-w-full items-baseline gap-1 rounded-full border border-ink-200 bg-[#f6f8fb] px-[0.55rem] py-[0.2rem] text-xs leading-snug text-ink-600 [&>.fal]:text-[0.7rem] [&>.fal]:text-ink-400"><i class="fal ' . $item['ikon'] . '"></i> '
                                                . '<span class="font-bold tracking-wide text-ink-500 whitespace-nowrap">' . esc($item['label']) . '</span>'
                                                . '<span class="break-words">' . esc($item['nilai']) . '</span></span>';
                                        }

                                        echo '</div>';
                                    }

                                    echo '</li>';
                                }
                                ?>
                            </ul>
                            <div class="mt-2 border-t border-dashed border-ink-200 pt-2 text-[0.85rem] text-ink-500">
                                total: <span class="inline-block rounded-full bg-ink-900 px-2 py-0.5 text-xs font-semibold text-white"><?= $count_barang; ?></span> pcs
                            </div>
                        </div>
                    </section>

                    <section class="col-span-full md:col-span-1 lg:col-span-5">
                        <div class="h-full rounded-2xl border border-ink-200 bg-[#fcfdfe] px-[1.15rem] py-4">
                            <h6 class="mb-2 flex items-center gap-1.5 text-[0.72rem] font-bold uppercase tracking-[0.09em] text-ink-500 [&_.fal]:text-[0.95rem] [&_.fal]:text-ink-400"><i class="fal fa-receipt"></i> Info Biaya</h6>

                            <?php
                            foreach ($pesanan->biaya as $c) {
                                $wajib_bayar += $c->nominal;
                            }

                            $sudah_bayar = status_pembayaran($pesanan->pembayaran, $pesanan->status_pembayaran, 'sudah_bayar');
                            ?>

                            <div class="mb-3 flex items-center justify-between gap-3 rounded-xl px-4 py-3 <?= $pil[$kelas_status]['band'] ?>">
                                <span class="text-[0.72rem] font-bold uppercase tracking-[0.09em]">Wajib Bayar</span>
                                <span class="text-[1.35rem] font-extrabold max-sm:text-[1.15rem]"><?= number_to_currency($wajib_bayar, 'IDR'); ?></span>
                            </div>

                            <ul class="m-0 list-none p-0 text-[0.85rem]">
                                <li class="flex items-baseline justify-between gap-3 py-1">
                                    <span class="uppercase tracking-wide text-ink-500">Harga Produk</span>
                                    <span class="font-bold whitespace-nowrap"><?= number_to_currency($harga_barang, 'IDR'); ?></span>
                                </li>

                                <?php
                                foreach ($pesanan->biaya as $c) { ?>
                                    <li class="flex items-baseline justify-between gap-3 py-1">
                                        <span class="uppercase tracking-wide text-ink-500">
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
                                            <span class="font-bold"><?= $biaya; ?></span>
                                            <?= $label; ?>
                                        </span>
                                        <span class="font-bold whitespace-nowrap <?= ($c->nominal < 0 ? 'text-red-600' : ''); ?>"><?= number_to_currency($c->nominal, 'IDR') ?></span>
                                    </li>
                                <?php
                                } ?>

                                <?php
                                if ($sudah_bayar > 0) { ?>
                                    <li class="flex items-baseline justify-between gap-3 py-1 mt-1 border-t border-dashed border-ink-200 pt-2">
                                        <span class="uppercase tracking-wide text-ink-500">
                                            <span class="font-bold">Sudah</span>&nbsp;Bayar
                                            <?= form_button([
                                                'class'          => 'ms-1 cursor-pointer border-0 bg-transparent text-ink-900 pesanBayar',
                                                'content'        => '<i class="fal fa-info-circle"></i> <span class="sr-only">info</span>',
                                                'data-invoice'   => $pesanan->id_invoice,
                                                'data-juragan'   => $pesanan->juragan_id,
                                                'data-bs-target' => '#modalBayar',
                                                'data-bs-toggle' => 'modal',
                                                'style'          => 'border:none; background:transparent',
                                            ]); ?>
                                        </span>
                                        <span class="font-bold whitespace-nowrap"><?= number_to_currency($sudah_bayar, 'IDR'); ?></span>
                                    </li>
                                <?php
                                }
                                if (($wajib_bayar - $sudah_bayar) > 0) { ?>
                                    <li class="flex items-baseline justify-between gap-3 py-1">
                                        <span class="uppercase tracking-wide text-ink-500"><span class="font-bold">Kurang</span>&nbsp;Bayar</span>
                                        <span class="font-bold whitespace-nowrap text-red-600"><?= number_to_currency(-($wajib_bayar - $sudah_bayar), 'IDR'); ?></span>
                                    </li>
                                <?php
                                }
                                if ($sudah_bayar > $wajib_bayar) { ?>
                                    <li class="flex items-baseline justify-between gap-3 py-1">
                                        <span class="uppercase tracking-wide text-ink-500"><span class="font-bold">Lebih</span>&nbsp;Bayar</span>
                                        <span class="font-bold whitespace-nowrap"><?= number_to_currency(($sudah_bayar - $wajib_bayar), 'IDR'); ?></span>
                                    </li>
                                <?php
                                } ?>
                            </ul>
                        </div>
                    </section>

                </div>

                <?php $rincian_pesanan = daftar_rincian($pesanan->rincian ?? null, 'pesanan'); ?>
                <?php if ($rincian_pesanan !== []) { ?>
                    <div class="rounded-2xl border border-ink-200 border-l-[3px] border-l-proses bg-[#fcfdfe] px-[1.15rem] py-4 mt-4">
                        <h6 class="mb-2 flex items-center gap-1.5 text-[0.72rem] font-bold uppercase tracking-[0.09em] text-ink-500 [&_.fal]:text-[0.95rem] [&_.fal]:text-ink-400"><i class="fal fa-list-check"></i> Detail Pesanan</h6>
                        <div class="grid grid-cols-[repeat(auto-fit,minmax(12rem,1fr))] gap-x-4 gap-y-2.5">
                            <?php foreach ($rincian_pesanan as $item) { ?>
                                <div class="min-w-0">
                                    <span class="block text-[0.7rem] font-bold uppercase tracking-[0.08em] text-ink-500 [&_.fal]:mr-1 [&_.fal]:text-ink-400"><i class="fal <?= $item['ikon'] ?>"></i> <?= esc($item['label']) ?></span>
                                    <span class="mt-[0.1rem] block break-words text-[0.92rem] font-semibold leading-normal text-ink-900"><?= esc($item['nilai']) ?></span>
                                </div>
                            <?php } ?>
                        </div>
                    </div>
                <?php } ?>

                <?php if ($pesanan->keterangan !== null && trim($pesanan->keterangan) !== '') { ?>
                    <div class="rounded-2xl border border-ink-200 border-l-[3px] border-l-brand-500 bg-[#fcfdfe] px-[1.15rem] py-4 mt-4">
                        <h6 class="mb-2 flex items-center gap-1.5 text-[0.72rem] font-bold uppercase tracking-[0.09em] text-ink-500 [&_.fal]:text-[0.95rem] [&_.fal]:text-ink-400"><i class="fal fa-clipboard-list"></i> Keterangan</h6>
                        <p class="keterangan mb-0 break-words text-sm leading-[1.65] text-ink-700" id="keterangan-<?= esc($pesanan->id_invoice) ?>">
                            <?= nl2br(esc($pesanan->keterangan)) ?>
                        </p>
                    </div>
                <?php } ?>

                <?php if ($pesanan->status_pengiriman !== '1' && $pesanan->pengiriman !== []) { ?>
                    <div class="mt-4">
                        <h6 class="mb-2 flex items-center gap-1.5 text-[0.72rem] font-bold uppercase tracking-[0.09em] text-ink-500 [&_.fal]:text-[0.95rem] [&_.fal]:text-ink-400"><i class="fal fa-truck-fast"></i> Resi</h6>
                        <div class="grid grid-cols-[repeat(auto-fill,minmax(15rem,1fr))] gap-3">
                            <?php
                            foreach ($pesanan->pengiriman as $key => $kirim) { ?>
                                <?php $tanggal_kirim = Time::createFromTimestamp($kirim->tanggal_kirim); ?>
                                <div class="rounded-xl border border-ink-200 bg-white px-3.5 py-3">
                                    <div class="flex items-center justify-between gap-2 text-[0.72rem] font-bold uppercase tracking-[0.08em] text-ink-500">
                                        <span class="truncate"><?= esc($kirim->kurir) ?></span>
                                        <?= form_button([
                                            'class'        => 'ms-1 cursor-pointer border-0 bg-transparent p-0 text-xs text-sky-600 hover:underline suntingResi',
                                            'content'      => '<i class="fal fa-money-check-edit fa-flip-horizontal"></i> <span class="sr-only">sunting</span>',
                                            'data-id'      => esc($kirim->id),
                                            'data-invoice' => esc($pesanan->id_invoice),
                                        ]) ?>
                                    </div>
                                    <div class="mt-[0.15rem] break-words text-[1.05rem] font-bold tracking-wide text-ink-900"><?= $kirim->resi; ?></div>
                                    <div class="text-[0.8rem] text-ink-500">
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

                <hr class="my-4 border-0 border-t border-ink-200 opacity-100" />

                <div class="flex items-center justify-between">
                    <div>
                        <div class="btn-group">
                            <?= anchor('admin/invoices/sunting/' . esc($pesanan->seri), '<i class="fal fa-pencil"></i> Sunting', ['class' => 'btn btn-outline-secondary', 'role' => 'button']) ?>
                            <button type="button" class="btn btn-outline-secondary dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown" aria-expanded="false">
                                <span class="sr-only">Toggle Dropdown</span>
                            </button>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="#">Print Label</a></li>
                                <li><a class="dropdown-item" target="_blank" href="<?= site_url('download/invoice/' . $pesanan->seri) ?>">Unduh Invoice (PDF)</a></li>
                                <li>
                                    <hr class="dropdown-divider" />
                                </li>
                                <li>
                                    <?= form_button([
                                        'class'          => 'dropdown-item tambahBayar',
                                        'content'        => 'Tambah Pembayaran',
                                        'data-invoice'   => $pesanan->id_invoice,
                                        'data-juragan'   => $pesanan->juragan_id,
                                        'data-kurang'    => $wajib_bayar - $sudah_bayar,
                                        'data-seri'      => $pesanan->seri,
                                        'data-bs-target' => '#modalTambahBayar',
                                        'data-bs-toggle' => 'modal',
                                    ]); ?>
                                </li>
                                <li>
                                    <hr class="dropdown-divider" />
                                </li>
                                <li>
                                    <?= form_button([
                                        'class'        => 'dropdown-item hapusOrderan text-red-600',
                                        'content'      => 'Hapus',
                                        'data-invoice' => $pesanan->id_invoice,
                                        'data-seri'    => $pesanan->seri,
                                    ]); ?>
                                </li>
                            </ul>
                        </div>

                        <?php if ((int) $pesanan->status_pembayaran > 2 && ! $dipacking) {

                            // tombol proses pesanan
                                        echo form_button([
                                            'class'          => 'btn btn-dark ms-1 pesanStatus',
                                            'content'        => '<i class="fal fa-comment-alt-plus"></i> Proses Orderan',
                                            'data-invoice'   => $pesanan->id_invoice,
                                            'data-seri'      => $pesanan->seri,
                                            'data-bs-target' => '#modalProgress',
                                            'data-bs-toggle' => 'modal',
                                        ]);
                                    }

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

                        if ($dipacking && (int) $pesanan->status_pengiriman < 3) {
                            echo form_button([
                                'class'        => 'btn btn-warning ms-1 kirimResi',
                                'content'      => '<i class="fal fa-paper-plane"></i> Input Resi',
                                'data-count'   => $count_barang,
                                'data-invoice' => $pesanan->id_invoice,
                            ]);
                        } ?>
                    </div>

                    <div class="form-check form-check-inline">
                        <?= form_checkbox(['name' => 'cetak[]', 'class' => 'form-check-input', 'id' => 'printLabel-' . $pesanan->id_invoice], $pesanan->id_invoice) ?>
                        <?= form_label('Print Label Masal', 'printLabel-' . $pesanan->id_invoice, ['class' => 'form-check-label']); ?>
                    </div>
                </div>
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
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
            <div class="mb-3">
                <?= form_label('Pilih Status', 'status', ['class' => 'form-label']) ?>
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
                <?= form_label('Note / Keterangan', 'keterangan', ['class' => 'form-label']); ?>
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
        <?= form_close() ?>
    </div>
</div>

<!-- Modal cek pembayaran -->
<div class="modal fade" id="modalBayar" tabindex="-1" aria-labelledby="modalBayarLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalBayarLabel">Info Pembayaran</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="bg-warning p-2 rounded mb-2">
                    Perlu diingat, detail pembayaran tidak bisa dihapus atau diubah
                </div>

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
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
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

<!-- Modal input resi -->
<div class="modal fade" id="modalKirim" tabindex="-1" aria-labelledby="modalKirimLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <?= form_open('', ['id' => 'submitResi', 'class' => 'modal-content'], ['invoice_id' => '1']) ?>
        <div class="modal-header">
            <h5 class="modal-title" id="modalKirimLabel">Modal title</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
            <div class="row gx-2 mb-3">
                <div class="col-4">
                    <?= form_label('Kurir', 'kurir', ['class' => 'form-label']) ?>
                    <?php
                    $options_kurir = ['' => 'Pilih kurir'];

                    foreach (config('JuraganConfig')->kurir as $label) {
                        $options_kurir[$label] = $label;
                    }
                    ?>

                    <?= form_dropdown('kurir', $options_kurir, '', ['class' => 'form-select', 'id' => 'kurir', 'required' => '']) ?>
                </div>
                <div class="col">
                    <?= form_label('Lainnya', 'resi', ['class' => 'form-label']) ?>
                    <?= form_input([
                        'class'       => 'form-control',
                        'disabled'    => '',
                        'id'          => 'lainnya',
                        'name'        => 'lainnya',
                        'placeholder' => 'kurir lain',
                    ]) ?>
                </div>
            </div>

            <div class="mb-3">
                <?= form_label('No Resi', 'resi', ['class' => 'form-label']) ?>
                <?= form_input([
                    'class'       => 'form-control',
                    'id'          => 'resi',
                    'name'        => 'resi',
                    'placeholder' => 'JOGxxxxxxxxxxx',
                    'required'    => '',
                ]); ?>
            </div>


            <div class="row gx-2 mb-3">
                <div class="col-5">
                    <div class="mb-3">
                        <?= form_label('Tanggal Kirim', 'tanggal_kirim', ['class' => 'form-label']) ?>
                        <?= form_input([
                            'class'       => 'form-control',
                            'id'          => 'tanggal_kirim',
                            'max'         => $sekarang->toDateString(),
                            'name'        => 'tanggal_kirim',
                            'placeholder' => 'JOGxxxxxxxxxxx',
                            'required'    => '',
                            'type'        => 'date',
                            'value'       => $sekarang->toDateString(),
                        ]) ?>
                    </div>
                </div>
                <div class="col">
                    <div class="mb-3">
                        <?= form_label('Isi Paket', 'isi_paket', ['class' => 'form-label']) ?>
                        <?= form_input([
                            'class'       => 'form-control',
                            'id'          => 'isi_paket',
                            'min'         => 1,
                            'name'        => 'qty',
                            'placeholder' => '1',
                            'required'    => '',
                            'type'        => 'number',
                        ]) ?>
                    </div>
                </div>
                <div class="col-4">
                    <div class="mb-3">
                        <?= form_label('Ongkir', 'ongkir', ['class' => 'form-label']) ?>
                        <?= form_input([
                            'class'       => 'form-control',
                            'id'          => 'ongkir',
                            'name'        => 'ongkir',
                            'placeholder' => '25000',
                            'required'    => '',
                            'min'         => 0,
                            'type'        => 'number',
                        ]) ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn link" data-bs-dismiss="modal">Batal</button>
            <button type="submit" class="btn btn-primary">Simpan</button>
        </div>
        <?= form_close(); ?>
    </div>
</div>

<!-- Modal pencarian invoice -->
<div class="modal fade" id="modalCari" tabindex="-1" aria-labelledby="modalCariLabel" aria-hidden="true">
    <div class="modal-dialog modal-md modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-body">
                <?= form_open('admin/invoices/lihat/' . $juragan . '/semua', ['method' => 'get']) ?>
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
$link_api_get_pembayaran = site_url('admin/invoices/info_pembayaran');
$link_api_pengiriman     = site_url('api/pengiriman');
$link_api_juragan        = site_url('api/juragan/by_user/');
$link_get_status_invoice = site_url('admin/invoices/detail_status/');
$link_hapus_orderan      = site_url('admin/invoices/hapus_orderan');
$link_invoice            = site_url('admin/invoices/lihat/');
$link_save_progress      = site_url('admin/invoices/save_progress');
$link_tambah_pembayaran  = site_url('admin/invoices/simpan_pembayaran');
$link_update_pembayaran  = site_url('admin/invoices/update_bayar');
$link_api_notif          = site_url('api/notifikasi/');
$link_api_counter_tab    = site_url('api/invoice/counter_tab/' . $juragan_id);

$codvalue = 'COD' . $sekarang->toLocalizedString('ddMMyyyy');

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

    	//

    	$('.pesanStatus').on('click',function(){
    		let id = $(this).data('invoice');

    		$('#newStatus [name="id_invoice"]').val(id);
    		$("#status option").prop('disabled', false);
    		$('#stat option').prop('disabled', false);
    		$.get("{$link_get_status_invoice}" + id, function(data, status){

    			if (data.length === 0) {
    				// console.log('data');
    				$("#status option").not('option[value=1]').each(function (index) {
    					$(this).prop('disabled', true);
    				});
    			} else {
    				// console.log(data);
    				if (data[0].tanggal_selesai === null) {
    					$("#status option").not('option[value='+data[0].status+']').each(function (index) {
    						$(this).prop('disabled', true);
    					});
    					$('#stat option').not('option[value=1]').prop('disabled', true);
    				} else {

    				}
    			}
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

    	//
    	var request;
    	var submitform=$('#newStatus');
    	submitform.on('submit',function(f){
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
    			url: "{$link_save_progress}",
    			type: "post",
    			data: serializedData
    		});

    		// Callback handler that will be called on success
    		request.done(function (response, textStatus, jqXHR){
    			// redirect
    			document.location.href = response.url;
    		});

    		// Callback handler that will be called on failure
    		request.fail(function (jqXHR, textStatus, errorThrown){
    			//
    			// console.log( Object.keys(jqXHR['responseJSON']).length);
    		});

    		// Callback handler that will be called regardless
    		// if the request failed or succeeded
    		request.always(function () {
    			// Reenable the inputs
    			inputs.prop("disabled", false);

    		});
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
    						btn = `<li>
    									<button class="dropdown-item" data-val="3" data-invoice="`+id+`" data-id="`+ bayar[i].id +`">
    										Dana ada
    									</button>
    								</li>
    								<li>
    									<button class="dropdown-item" data-val="2" data-invoice="`+id+`" data-id="`+ bayar[i].id +`">
    										Dana tidak ada
    									</button>
    								</li>`;
    						ico = 'minus-circle text-info';
    						info = 'dana belum dicek';
    						break;
    				}

    				var bta = `<div>
    					<div class="dropdown">
    						<button class="btn btn-outline-secondary btn-sm" type="button" id="dropdownMenuButton" data-bs-toggle="dropdown" aria-expanded="false">
    							<i class="fal fa-ellipsis-h"></i>
    						</button>
    						<ul class="dropdown-menu btn-action" aria-labelledby="dropdownMenuButton">`+ btn +`</ul>
    					</div>
    				</div>`;

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

    	// set dana
    	$(document).on('click', '.btn-action .dropdown-item',function() {
    		var id = $(this).data('id'),
    			invoice = $(this).data('invoice'),
    			status = $(this).data('val');

    		if(confirm("Sudah yakin, ini tidak bisa diubah lho?"))
    		{
    			$.ajax({
    				method: "POST",
    				url: "{$link_update_pembayaran}",
    				data: { id_pembayaran: id, status: status, invoice_id: invoice }
    			})
    			.done(function( msg ) {
    				// console.log( "Data Saved: " + msg );
    				document.location.href = msg.url;
    			});
    		}
    	});

    	$('.hapusOrderan').on('click',function(){
    		let seri = $(this).data('seri');
    		let id = $(this).data('invoice');

    		if(confirm("Hapus orderan " + seri + "? sudah yakin?"))
    		{
    			$.ajax({
    				method: "POST",
    				url: "{$link_hapus_orderan}",
    				data: { invoice_id: id }
    			})
    			.done(function( msg ) {
    				// console.log( "Data Saved: " + msg );
    				document.location.href = msg.url;
    			});
    		}
    	});

    	// submit resi
    	$('.kirimResi').on('click',function(){
    		var modal = new bootstrap.Modal(document.getElementById('modalKirim')),
    			invoice = $(this).data('invoice'),
    			count = $(this).data('count'),
    			qty = $('#submitResi [name="qty"]');

    		$('#submitResi [name="invoice_id"]').val(invoice);
    		qty.val(count).attr('readonly', true);

    		if (parseInt( count ) > 1 ) {
    			// remove readonly
    			qty.attr('readonly', false);
    		}

    		modal.show();
    	});

    	// source: https://stackoverflow.com/a/27521981/2094645
    	function dropdownToArray(target) {
            var targets = $(target + " option");
            var results = [];
            targets.each(function () {
                var val = $(this).val();
                if (val !== '') results.push(val);
            });
            return results;
    	}

    	// sunting resi
    	$('.suntingResi').on('click', function() {
    		var id = $(this).data('id'), // id pengiriman
    			modal = new bootstrap.Modal(document.getElementById('modalKirim')),
    			invoice = $(this).data('invoice');

    		$('#modalKirim .modal-title').text('Sunting Resi');

    		$('#submitResi').attr('id', 'suntingResi');

    		$('#suntingResi [name="invoice_id"]').val(invoice);

    		// get data
    		// source: https://stackoverflow.com/a/18867652/2094645
    		$.getJSON('{$link_api_pengiriman}/get', { id: id }, function(data) {
    			// set array
    			var arr = dropdownToArray('#suntingResi #kurir');
    			if(jQuery.inArray(data.kurir, arr) != -1) {
    				$('#suntingResi #kurir').val(data.kurir);
    				$('#suntingResi #lainnya').attr('disabled', true);
    			} else {
    				$('#suntingResi #kurir').val('lainnya');
    				$('#suntingResi #lainnya').attr('disabled', false).val(data.kurir);
    			}

    			$('#suntingResi #resi').val(data.resi);
    			var date = new Date(data.tanggal_kirim * 1000),
    				tggl = date.getFullYear() +'-'+("0" + date.getMonth()).slice(-2) + '-'+ ("0" + date.getDate()).slice(-2);

    			console.log(tggl);
    			$('#suntingResi #tanggal_kirim').val(tggl);
    			$('#suntingResi #isi_paket').val(data.qty_kirim).attr('readonly', true);
    			$('#suntingResi #ongkir').val(data.ongkir);

    			$('#suntingResi [name="invoice_id"]').after($('<input>').attr({
    				type: 'hidden',
    				name: 'id_pengiriman'
    			}));

    			$('#suntingResi [name="id_pengiriman"]').val(id);

    		});

    		modal.show();
    	});

    	// kurir
    	$('#submitResi #kurir').on('change', function() {
    		var lainnya = $('#submitResi #lainnya');
    		var resi = $('#submitResi #resi');
    		if ($(this).val() === "lainnya") {
    			lainnya.attr('disabled', false);
    			lainnya.attr('required', true);
    		} else {
    			lainnya.attr('disabled', true);
    		}

    		if ($(this).val() === "COD") {

    			resi.val('{$codvalue}');
    		} else {
    			resi.val('');
    		}
    	});


    	// Variable to hold request
    	var request_resi;

    	// Bind to the submit event of our form
    	$(document).on('submit', "#submitResi", function(event){

    		// Prevent default posting of form - put here to work in case of errors
    		event.preventDefault();

    		// Abort any pending request
    		if (request_resi) {
    			request_resi.abort();
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
    		request_resi = $.ajax({
    			url: "{$link_api_pengiriman}/save",
    			type: "post",
    			data: serializedData
    		});

    		// Callback handler that will be called on success
    		request_resi.done(function (response, textStatus, jqXHR){
    			document.location.href = response.url;
    		});

    		// Callback handler that will be called on failure
    		request_resi.fail(function (jqXHR, textStatus, errorThrown){
    			// Log the error to the console
    			console.error(
    				"The following error occurred: "+
    				textStatus, errorThrown
    			);
    		});

    		// Callback handler that will be called regardless
    		// if the request failed or succeeded
    		request_resi.always(function () {
    			// Reenable the inputs
    			inputs.prop("disabled", false);
    		});

    	});

    	var request_suntingResi;
    	$(document).on('submit', "#suntingResi", function(event){

    		// Prevent default posting of form - put here to work in case of errors
    		event.preventDefault();

    		// Abort any pending request
    		if (request_suntingResi) {
    			request_suntingResi.abort();
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
    		request_suntingResi = $.ajax({
    			url: "{$link_api_pengiriman}/save",
    			type: "post",
    			data: serializedData
    		});

    		// Callback handler that will be called on success
    		request_suntingResi.done(function (response, textStatus, jqXHR){
    			document.location.href = response.url;
    		});

    		// Callback handler that will be called on failure
    		request_suntingResi.fail(function (jqXHR, textStatus, errorThrown){
    			// Log the error to the console
    			console.error(
    				"The following error occurred: "+
    				textStatus, errorThrown
    			);
    		});

    		// Callback handler that will be called regardless
    		// if the request failed or succeeded
    		request_suntingResi.always(function () {
    			// Reenable the inputs
    			inputs.prop("disabled", false);
    		});

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
    			$(this).append('<a href="javascript:void(0);" class="read-more ms-1 font-semibold whitespace-nowrap">lanjut ...</a>');
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
