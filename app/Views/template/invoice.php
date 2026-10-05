<?php

use App\Libraries\Ongkir;

$html_logo = '<img src="' . str_replace('\\', '/', rtrim(FCPATH, '/\\')) . '/assets/img/logo-invoice.png" width="100" height="100" />';
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Invoice <?= $invoice->seri; ?></title>

    <style type="text/css">
        * {
            font-family: Verdana, Arial, sans-serif;
        }

        table {
            font-size: small;
        }

        tfoot tr td {
            font-weight: bold;
            font-size: small;
        }

        table.inv {
            border: 1px solid lightgrey;
        }

        .gray {
            background-color: lightgray
        }

        .bb {
            border-bottom: 1px solid #ddd;
            margin-bottom: 30px;
            padding-bottom: 15px
        }

        .inf {
            text-transform: uppercase;
            color: #ccc;
        }

        .text-danger {
            color: red;
        }

        /* absolute, bukan fixed: dompdf menggambar elemen fixed di setiap halaman
           jadi stempelnya ikut muncul di halaman lampiran */
        .ribbon {
            padding-top: 15px;
            padding-bottom: 15px;
            text-align: center;
            width: 400px;
            position: absolute;
            color: white;
            font-size: 18px;
            transform: rotate(45deg);
            top: -20px;
            right: -210px;
        }

        .ribbon.red {
            background: #d9534f;
        }

        .ribbon.green {
            background: #5cb85c;
        }

        /* ukuran rombongan dicetak di halaman sendiri supaya nota utama tetap pendek */
        .lampiran {
            page-break-before: always;
        }

        .lampiran td {
            vertical-align: top;
            padding: 4px 6px;
        }
    </style>

</head>

<body>
    <?php
    // status pembayaran dan tahap produksi dibaca dari helper yang sama dengan kartu
    // transaksi, supaya angka di kertas tidak lagi berbeda dengan di layar
    $lunas       = tagihan_lunas($invoice->status_pembayaran);
    $label_bayar = status_pembayaran($invoice->pembayaran, $invoice->status_pembayaran, 'label');
    $dibayarkan  = status_pembayaran($invoice->pembayaran, $invoice->status_pembayaran, 'sudah_bayar');
    $tahap       = tahap_terakhir($invoice->status ?? []);
    ?>
    <div class="ribbon top-left <?= $lunas ? 'green' : 'red'; ?>"><?= $lunas ? 'Lunas' : 'Belum Lunas'; ?></div>

    <table width="100%" class="bb">
        <tr>
            <td><?= $html_logo ?></td>
            <td align="right">
                <h3><?= esc($invoice->juragan?->nama ?? 'Seven Inc') ?></h3>
                <p>Karangjambe, Banguntapan<br>Bantul, D.I Yogyakarta - 55198</p>
            </td>
        </tr>

    </table>

    <table width="100%" class="bb">
        <tbody>
            <tr>
                <td>
                    No Invoice: #<?= esc($invoice->seri) ?><br>
                    Tanggal: <?= esc(tanggal_rincian((string) $invoice->tanggal_pesan)) ?><br>
                    Status: <?= esc($label_bayar) ?><br>
                    Status Produksi: <?= esc(label_status_orderan($invoice->status_pesanan)) ?>
                    <?php if ($tahap !== null) { ?>
                        <br>Tahap terakhir: <?= esc($tahap['nama']) ?> (<?= esc($tahap['tanggal']) ?>, <?= $tahap['selesai'] ? 'selesai' : 'berjalan' ?>)
                    <?php } ?>
                </td>
            </tr>
        </tbody>
    </table>

    <table width="100%">
        <?php
        $ongkir     = new Ongkir();
        $pemesan    = $invoice->pelanggan;
        $kirimKe    = $invoice->kirimKe;
        ?>
        <tr>
            <td>
                <span class="inf">Pemesan<?= esc($pemesan->id === $kirimKe->id ? ' / Kirim Kepada' : '') ?></span> <br>
                <?= $pemesan->nama; ?><br>
                <?php
                $i = 0;

                foreach (json_decode($pemesan->hp) as $hp) {
                    if ($i === 1) {
                        echo ' / ';
                    }
                    echo esc($hp);
                    $i++;
                }
                ?><br>
                <?php
                if ($pemesan->cod === '1') {
                    echo 'C.O.D';
                } else {
                    $kec  = strtoupper(esc($ongkir->kecamatan($pemesan->kabupaten, $pemesan->kecamatan)['subdistrict_name']));
                    $kab  = strtoupper(esc($ongkir->kota($pemesan->provinsi, $pemesan->kabupaten)['city_name']));
                    $prov = strtoupper(esc($ongkir->provinsi($pemesan->provinsi)['province_name']));

                    echo $pemesan->alamat . '<br/>' . $kec . ', ' . $kab . '<br/>' . $prov . ($pemesan->kodepos === '0' ? '' : ' - ' . $pemesan->kodepos);
                }
                ?>
            </td>
            <td>
                <?php if ($pemesan->id !== $kirimKe->id) { ?>
                    <span class="inf">Kirim Kepada</span> <br>
                    <?= esc($kirimKe->nama) ?><br>
                    <?php
                    $i = 0;

                    foreach (json_decode($kirimKe->hp) as $hp) {
                        if ($i === 1) {
                            echo ' / ';
                        }
                        echo esc($hp);
                        $i++;
                    }
                    ?><br>
                    <?php
                    if ($kirimKe->cod === '1') {
                        echo 'C.O.D';
                    } else {
                        $kec  = strtoupper(esc($ongkir->kecamatan($kirimKe->kabupaten, $kirimKe->kecamatan)['subdistrict_name']));
                        $kab  = strtoupper(esc($ongkir->kota($kirimKe->provinsi, $kirimKe->kabupaten)['city_name']));
                        $prov = strtoupper(esc($ongkir->provinsi($kirimKe->provinsi)['province_name']));

                        echo $kirimKe->alamat . '<br/>' . $kec . ', ' . $kab . '<br/>' . $prov . ($kirimKe->kodepos === '0' ? '' : ' - ' . $kirimKe->kodepos);
                    }
                    ?>
                <?php } ?>
            </td>
        </tr>
    </table>

    <br>

    <table width="100%" class="inv">
        <thead style="background-color: lightgray;">
            <tr>
                <th>Deskripsi</th>
                <th>Qty</th>
                <th>Harga</th>
                <th>Subtotal</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $wajib_bayar = 0;
            // $count_barang = 0;
            $harga_barang = 0;

            foreach ($invoice->barang as $b) {
                // $count_barang += $b->qty;
                $wajib_bayar += $b->qty * $b->harga;
                $harga_barang += $b->qty * $b->harga;
                echo '<tr>';
                echo '<td>' . strtoupper($b->kode) . ' (' . strtoupper($b->ukuran) . ')';

                echo '</td>';
                echo '<td style="text-align:center">' . $b->qty . '</td>';
                echo '<td style="text-align:right">' . number_to_currency($b->harga, 'IDR') . '</td>';
                echo '<td style="text-align:right">' . number_to_currency($b->harga * $b->qty, 'IDR') . '</td>';
                echo '</tr>';
            }
            ?>
            <tr>
                <td>&nbsp;</td>
            </tr>
            <tr>
                <td>&nbsp;</td>
            </tr>
            <tr>
                <td>&nbsp;</td>
            </tr>
        </tbody>

        <tfoot>
            <tr>
                <td colspan="2"></td>
                <td align="right">Subtotal</td>
                <td align="right"><?= number_to_currency($harga_barang, 'IDR') ?></td>
            </tr>

            <?php
            foreach ($invoice->biaya as $c) {
                $wajib_bayar += $c->nominal; ?>
                <tr>
                    <td colspan="2"></td>
                    <td align="right">
                        <?php
                        $biaya = 'Lainnya';
                        if ($c->biaya_id === '1') {
                            $biaya = 'Ongkir';
                        }
                        $label = $c->label;
                        if ($c->label !== 'null' && $c->biaya_id !== '1') {
                            $biaya = $c->label . ' ';
                            $label = '';
                        } elseif ($c->label === 'null') {
                            $label = '';
                        } ?>
                        <?= $biaya; ?>
                        <?= $label; ?>
                    </td>
                    <td align="right" class="<?= ($c->nominal < 0 ? 'text-danger' : 'normal'); ?>">
                        <?= number_to_currency($c->nominal, 'IDR'); ?>
                    </td>
                </tr>
            <?php
            }
            ?>
            <tr style="font-size: 1.5em !important;">
                <td colspan="2"></td>
                <td align="right">Total</td>
                <td align="right" class="gray"><?= number_to_currency($wajib_bayar, 'IDR') ?></td>
            </tr>

            <tr>
                <td colspan="2"></td>
                <td align="right">DP/dibayar</td>
                <td align="right"><?= number_to_currency($dibayarkan, 'IDR') ?></td>
            </tr>
            <?php $kekurangan = $wajib_bayar - $dibayarkan;

            if ($kekurangan > 0) {
            ?>
                <tr class="text-danger">
                    <td colspan="2"></td>
                    <td align="right">Kekurangan</td>
                    <td align="right"><?= number_to_currency($kekurangan, 'IDR') ?></td>
                </tr>
            <?php
            } ?>

        </tfoot>
    </table>

    <?php $anggota = $invoice->anggota ?? []; ?>
    <?php if ($anggota !== []) { ?>
        <?php
        // ukuran anggota belum ditautkan per barang, jadi produk orderan ini
        // yang jadi acuan di judul lampiran
        $produk = [];

        foreach ($invoice->barang as $b) {
            $produk[] = strtoupper($b->kode) . ' (' . strtoupper($b->ukuran) . ') x ' . $b->qty;
        }
        ?>
        <div class="lampiran">
            <h4>Lampiran Ukuran Rombongan</h4>
            <p>
                No Invoice: #<?= esc($invoice->seri) ?><br>
                Produk: <?= esc(implode(', ', $produk)) ?>
            </p>

            <table width="100%" class="inv">
                <thead style="background-color: lightgray;">
                    <tr>
                        <th width="6%">No</th>
                        <th width="26%">Nama</th>
                        <th width="12%">Nomor</th>
                        <th>Ukuran</th>
                        <th width="20%">Catatan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($anggota as $i => $o) { ?>
                        <tr>
                            <td align="center"><?= $i + 1 ?></td>
                            <td><?= esc($o->nama) ?></td>
                            <td><?= esc($o->nomor ?: '-') ?></td>
                            <td>
                                <?php if (empty($o->nilai)) { ?>
                                    <em>belum diukur</em>
                                <?php } else { ?>
                                    <?php foreach ($o->nilai as $j => $u) { ?>
                                        <?= $j > 0 ? '; ' : '' ?><?= esc($u[0]) ?>: <strong><?= esc($u[1]) ?></strong>
                                    <?php } ?>
                                <?php } ?>
                            </td>
                            <td><?= esc($o->catatan ?: '-') ?></td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    <?php } ?>

</body>

</html>
