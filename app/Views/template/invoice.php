<?php

use App\Libraries\Ongkir;

$html_logo = '<img src="' . str_replace('\\', '/', rtrim(FCPATH, '/\\')) . '/assets/img/logo-invoice.png" width="92" height="92" />';
?>
<!doctype html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Invoice <?= esc($invoice->seri); ?></title>

    <style type="text/css">
        /* Dompdf 2 tidak mengenal flex/grid/border-radius, jadi semua tata letak disusun
           dengan tabel; yang dipakai hanya warna, garis dan tipografi. */
        * {
            font-family: Helvetica, Arial, sans-serif;
        }

        body {
            color: #1f2933;
            font-size: 11px;
            margin: 0;
        }

        table {
            width: 100%;
            font-size: 11px;
        }

        .kop {
            border-bottom: 2px solid #12232e;
            padding-bottom: 10px;
        }

        .toko {
            font-size: 15px;
            font-weight: bold;
            letter-spacing: 0.5px;
        }

        .alamat {
            color: #6b7a89;
            font-size: 10px;
            line-height: 15px;
        }

        .judul {
            font-size: 22px;
            font-weight: bold;
            letter-spacing: 3px;
            color: #12232e;
        }

        .nomor {
            color: #6b7a89;
            font-size: 11px;
        }

        .stempel {
            border: 1px solid #d6dde4;
            padding: 7px 12px;
            text-align: center;
            width: 150px;
        }

        .stempel .besar {
            font-size: 13px;
            font-weight: bold;
            letter-spacing: 1px;
            text-transform: uppercase;
        }

        .stempel .kecil {
            font-size: 9px;
        }

        .lunas {
            background: #1b5e37;
            border-color: #1b5e37;
            color: #ffffff;
        }

        .lunas .kecil {
            color: #d3e7da;
        }

        .belum {
            background: #fdf3e3;
            border-color: #ecc79b;
            color: #8a5216;
        }

        .cap {
            color: #6b7a89;
            font-size: 9px;
            font-weight: bold;
            letter-spacing: 1.2px;
            text-transform: uppercase;
        }

        /* kotak keterangan: judul kecil di atas, isinya di bawah */
        .kotak {
            background: #f5f7f9;
            border-left: 3px solid #12232e;
            padding: 8px 10px;
            vertical-align: top;
        }

        .isi {
            line-height: 16px;
            padding-top: 4px;
        }

        table.rapi {
            border-collapse: collapse;
            margin-top: 6px;
        }

        table.rapi th {
            background: #12232e;
            color: #ffffff;
            font-size: 9px;
            letter-spacing: 1px;
            padding: 7px 8px;
            text-align: left;
            text-transform: uppercase;
        }

        table.rapi td {
            border-bottom: 1px solid #e6eaee;
            padding: 7px 8px;
            vertical-align: top;
        }

        table.rapi tr.selang {
            background: #f7f9fb;
        }

        .angka {
            text-align: right;
            white-space: nowrap;
        }

        .tengah {
            text-align: center;
        }

        .kode {
            font-weight: bold;
        }

        .abu {
            color: #6b7a89;
        }

        .gelap {
            background: #12232e;
            color: #ffffff;
            font-size: 13px;
            font-weight: bold;
        }

        .merah {
            color: #b3261e;
        }

        .kaki {
            border-top: 1px solid #e6eaee;
            color: #6b7a89;
            font-size: 9px;
            padding-top: 8px;
        }

        /* ukuran rombongan dicetak di halaman sendiri supaya nota utama tetap pendek */
        .lampiran {
            page-break-before: always;
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
    $tenggat     = label_deadline(($invoice->deadline ?? '') ?: deadline_iso($invoice->rincian ?? null));
    $ongkir      = new Ongkir();
    $pemesan     = $invoice->pelanggan;
    $kirimKe     = $invoice->kirimKe;

    // alamat lengkap sekali saja: kolom per kolom, supaya tidak ada teks yang hilang
    $alamat = static function ($orang) use ($ongkir): string {
        if ($orang->cod === '1') {
            return 'C.O.D';
        }

        $kec  = strtoupper(esc($ongkir->kecamatan($orang->kabupaten, $orang->kecamatan)['subdistrict_name'] ?? ''));
        $kab  = strtoupper(esc($ongkir->kota($orang->provinsi, $orang->kabupaten)['city_name'] ?? ''));
        $prov = strtoupper(esc($ongkir->provinsi($orang->provinsi)['province_name'] ?? ''));

        return esc($orang->alamat) . '<br>' . $kec . ', ' . $kab . '<br>' . $prov
            . (empty($orang->kodepos) || $orang->kodepos === '0' ? '' : ' - ' . esc($orang->kodepos));
    };

    $kontak = static fn ($orang): string => implode(' / ', array_map(
        static fn ($n): string => esc($n),
        (array) json_decode((string) $orang->hp),
    ));
    ?>

    <table class="kop">
        <tr>
            <td valign="middle" width="28%"><?= $html_logo ?></td>
            <td align="right" valign="middle" width="72%">
                <div class="toko"><?= esc($invoice->juragan?->nama ?? 'Seven Inc') ?></div>
                <div class="alamat">Karangjambe, Banguntapan, Bantul<br>D.I. Yogyakarta 55198</div>
            </td>
        </tr>
    </table>

    <table>
        <tr>
            <td width="58%" valign="middle">
                <div class="judul">INVOICE</div>
                <div class="nomor">Nomor <?= esc($invoice->seri) ?> &nbsp;·&nbsp; <?= esc(tanggal_rincian((string) $invoice->tanggal_pesan)) ?></div>
            </td>
            <td align="right" valign="middle" width="42%">
                <table>
                    <tr>
                        <td>
                            <div class="stempel <?= $lunas ? 'lunas' : 'belum'; ?>">
                                <div class="besar"><?= $lunas ? 'Lunas' : 'Belum Lunas' ?></div>
                                <div class="kecil"><?= esc($label_bayar) ?></div>
                            </div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <br>

    <table>
        <tr>
            <td class="kotak" width="50%">
                <div class="cap">Pemesan</div>
                <div class="isi">
                    <?= esc($pemesan->nama) ?><br>
                    <?= $kontak($pemesan) ?><br>
                    <?= $alamat($pemesan) ?>
                </div>
            </td>
            <td class="kotak" width="50%">
                <?php if ($pemesan->id !== $kirimKe->id) { ?>
                    <div class="cap">Kirim Kepada</div>
                    <div class="isi">
                        <?= esc($kirimKe->nama) ?><br>
                        <?= $kontak($kirimKe) ?><br>
                        <?= $alamat($kirimKe) ?>
                    </div>
                <?php } else { ?>
                    <div class="cap">Status Pesanan</div>
                    <div class="isi">
                        <?= esc(label_status_orderan($invoice->status_pesanan)) ?>
                        <?php if ($tenggat['teks'] !== '-') { ?>
                            <br>Tenggat: <?= esc($tenggat['teks']) ?>
                        <?php } ?>
                        <?php if ($tahap !== null) { ?>
                            <br>Tahap terakhir: <?= esc($tahap['nama']) ?> (<?= esc($tahap['tanggal']) ?>, <?= $tahap['selesai'] ? 'selesai' : 'berjalan' ?>)
                        <?php } ?>
                    </div>
                <?php } ?>
            </td>
        </tr>
    </table>

    <br>

    <table class="rapi">
        <thead>
            <tr>
                <th>Pesanan</th>
                <th width="8%" class="tengah">Qty</th>
                <th width="18%" class="angka">Harga</th>
                <th width="18%" class="angka">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $harga_barang = 0;
            $wajib_bayar  = 0;

            foreach ($invoice->barang as $i => $b) :
                $harga_barang += $b->qty * $b->harga;
                $wajib_bayar  += $b->qty * $b->harga;
                $rincian_item  = daftar_rincian($b->rincian ?? null, 'produk');
            ?>
                <tr class="<?= $i % 2 ? 'selang' : ''; ?>">
                    <td>
                        <span class="kode"><?= esc(strtoupper($b->kode)) ?></span>
                        <?php if (! empty($b->ukuran)) { ?>
                            <span class="abu">/ <?= esc(strtoupper($b->ukuran)) ?></span>
                        <?php } ?>
                        <?php if ($rincian_item !== []) { ?>
                            <div class="abu">
                                <?php
                                $bagian = [];

                                foreach ($rincian_item as $item) {
                                    $bagian[] = esc($item['label']) . ': ' . esc($item['nilai']);
                                }

                                echo implode(' &nbsp;·&nbsp; ', $bagian);
                                ?>
                            </div>
                        <?php } ?>
                    </td>
                    <td class="tengah"><?= (int) $b->qty ?></td>
                    <td class="angka"><?= number_to_currency($b->harga, 'IDR') ?></td>
                    <td class="angka"><?= number_to_currency($b->harga * $b->qty, 'IDR') ?></td>
                </tr>
            <?php endforeach; ?>

            <?php foreach ($invoice->biaya as $c) : ?>
                <?php
                // label biaya lama tersimpan di kolom label; ongkir punya nama sendiri
                $nama_biaya = $c->biaya_id === '1'
                    ? 'Ongkir'
                    : ($c->label === 'null' || empty($c->label) ? 'Biaya lainnya' : $c->label);
                ?>
                <tr>
                    <td><span class="abu"><?= esc($nama_biaya) ?></span></td>
                    <td></td>
                    <td></td>
                    <td class="angka <?= $c->nominal < 0 ? 'merah' : ''; ?>"><?= number_to_currency($c->nominal, 'IDR') ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>

        <tfoot>
            <tr>
                <td class="angka abu" colspan="3">Subtotal barang</td>
                <td class="angka"><?= number_to_currency($harga_barang, 'IDR') ?></td>
            </tr>
            <tr>
                <td class="gelap angka" colspan="3">Total tagihan</td>
                <td class="gelap angka"><?= number_to_currency($wajib_bayar, 'IDR') ?></td>
            </tr>
            <tr>
                <td class="angka abu" colspan="3">Dibayar</td>
                <td class="angka"><?= number_to_currency($dibayarkan, 'IDR') ?></td>
            </tr>
            <?php if ($wajib_bayar - $dibayarkan > 0) { ?>
                <tr>
                    <td class="merah angka" colspan="3">Sisa bayar</td>
                    <td class="merah angka"><?= number_to_currency($wajib_bayar - $dibayarkan, 'IDR') ?></td>
                </tr>
            <?php } ?>
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
            <table class="kop">
                <tr>
                    <td width="62%">
                        <div class="cap">Lampiran</div>
                        <div class="judul" style="font-size: 16px;">UKURAN ROMBONGAN</div>
                        <div class="nomor">Nomor <?= esc($invoice->seri) ?> &nbsp;·&nbsp; <?= esc(implode(', ', $produk)) ?></div>
                    </td>
                    <td align="right" class="alamat" valign="bottom">
                        <?= esc($invoice->juragan?->nama ?? 'Seven Inc') ?><br>
                        <?= count($anggota) ?> orang
                    </td>
                </tr>
            </table>

            <table class="rapi">
                <thead>
                    <tr>
                        <th width="6%" class="tengah">No</th>
                        <th width="24%">Nama</th>
                        <th width="10%" class="tengah">Nomor</th>
                        <th>Ukuran</th>
                        <th width="20%">Catatan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($anggota as $i => $o) { ?>
                        <tr class="<?= $i % 2 ? 'selang' : ''; ?>">
                            <td class="tengah"><?= $i + 1 ?></td>
                            <td><?= esc($o->nama) ?></td>
                            <td class="tengah"><?= esc($o->nomor ?: '-') ?></td>
                            <td>
                                <?php if (empty($o->nilai)) { ?>
                                    <span class="abu">belum diukur</span>
                                <?php } else { ?>
                                    <?php foreach ($o->nilai as $j => $u) { ?>
                                        <?= $j > 0 ? '&nbsp;·&nbsp; ' : '' ?><span class="abu"><?= esc($u[0]) ?>:</span> <strong><?= esc($u[1]) ?></strong>
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

    <table class="kaki">
        <tr>
            <td>Terima kasih atas kepercayaan Anda.</td>
            <td align="right">Dicetak <?= esc(date('d M Y')) ?> &nbsp;·&nbsp; dokumen tagihan</td>
        </tr>
    </table>

</body>

</html>
