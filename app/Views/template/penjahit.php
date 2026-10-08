<?php

/**
 * Lembar kerja penjahit — dipakai satu nota dengan invoice pelanggan, tetapi isinya
 * hanya spesifikasi jahit: tidak ada harga, pembayaran, atau rekening, dan tidak ada
 * alamat/HP pelanggan. Ukuran rombongan justru lengkap di sini karena itu yang dikerjakan.
 */

$tahap    = tahap_produksi();
$terakhir = tahap_terakhir($invoice->status ?? []);
$tenggat  = label_deadline(($invoice->deadline ?? '') ?: deadline_iso($invoice->rincian ?? null));
$pesanan  = daftar_rincian($invoice->rincian ?? null, 'pesanan');

// catatan tiap tahap yang sudah pernah dicatat, dipakai kolom Catatan di checklist
$dicatat = [];

foreach ((array) ($invoice->status ?? []) as $s) {
    $b  = (array) $s;
    $no = (int) ($b['status'] ?? 0);

    if ($no <= 0) {
        continue;
    }

    $dicatat[$no] = trim((string) (($b['keterangan_selesai'] ?? '') ?: ($b['keterangan_masuk'] ?? '')));
}

$baris = static function (string $label, ?string $nilai): string {
    return '<tr><td class="k">' . esc($label) . '</td><td class="v">' . ($nilai === null || $nilai === '' ? '<span class="abu">-</span>' : esc($nilai)) . '</td></tr>';
};
?>
<!doctype html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Lembar Kerja Penjahit <?= esc($invoice->seri); ?></title>

    <style type="text/css">
        /* Dompdf 2 tidak mengenal flex/grid/border-radius; tata letak pakai tabel,
           keterbacaan dicapai lewat warna, garis dan huruf kapital berjarak. */
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
            padding-bottom: 8px;
        }

        .judul {
            font-size: 18px;
            font-weight: bold;
            letter-spacing: 2px;
            color: #12232e;
        }

        .sub {
            color: #8a5216;
            font-size: 9px;
            font-weight: bold;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        .nomor {
            color: #6b7a89;
            font-size: 10px;
        }

        .toko {
            font-size: 13px;
            font-weight: bold;
        }

        .cap {
            color: #6b7a89;
            font-size: 9px;
            font-weight: bold;
            letter-spacing: 1.2px;
            text-transform: uppercase;
        }

        .kotak {
            background: #f5f7f9;
            border-left: 3px solid #12232e;
            padding: 7px 9px;
            vertical-align: top;
        }

        /* peringatan kecil: lembar ini bukan dokumen tagihan */
        .catatan {
            background: #fdf3e3;
            border: 1px solid #ecc79b;
            color: #8a5216;
            font-size: 9px;
            padding: 5px 8px;
        }

        table.info {
            border-collapse: collapse;
            width: 100%;
        }

        table.info td {
            border-bottom: 1px solid #e6eaee;
            padding: 4px 0;
        }

        table.info td.k {
            color: #6b7a89;
            width: 30%;
        }

        table.info td.v {
            font-weight: bold;
        }

        table.rapi {
            border-collapse: collapse;
        }

        table.rapi th {
            background: #12232e;
            color: #ffffff;
            font-size: 9px;
            letter-spacing: 1px;
            padding: 6px 8px;
            text-align: left;
            text-transform: uppercase;
        }

        table.rapi td {
            border-bottom: 1px solid #e6eaee;
            padding: 6px 8px;
            vertical-align: top;
        }

        table.rapi tr.selang {
            background: #f7f9fb;
        }

        /* satu blok per item supaya teks spesifikasi yang panjang tidak dipotong kolom */
        .item {
            border: 1px solid #d6dde4;
            page-break-inside: avoid;
        }

        .item .kepala {
            background: #12232e;
            color: #ffffff;
            font-size: 12px;
            font-weight: bold;
            letter-spacing: 0.5px;
            padding: 6px 9px;
        }

        .item .kepala .qty {
            font-weight: normal;
            letter-spacing: 0;
        }

        .ukuran {
            line-height: 17px;
        }

        .abu {
            color: #6b7a89;
        }

        .tengah {
            text-align: center;
        }

        /* kotak checklist: diisi latar gelap kalau tahapnya sudah selesai */
        .kotak-check {
            border: 1px solid #6b7a89;
            width: 11px;
            height: 11px;
        }

        .cek-isi {
            background: #12232e;
            border-color: #12232e;
        }

        .paraf {
            border: 1px solid #d6dde4;
            height: 26px;
            width: 90px;
        }

        .jeda {
            height: 10px;
        }

        .kaki {
            border-top: 1px solid #e6eaee;
            color: #6b7a89;
            font-size: 9px;
            padding-top: 7px;
        }

        .sect {
            padding-top: 12px;
        }
    </style>

</head>

<body>
    <table class="kop">
        <tr>
            <td valign="bottom" width="62%">
                <div class="judul">LEMBAR KERJA PENJAHIT</div>
                <div class="nomor">Nota <?= esc($invoice->seri) ?> &nbsp;·&nbsp; <?= esc(tanggal_rincian((string) $invoice->tanggal_pesan)) ?> &nbsp;·&nbsp; dicetak <?= esc(date('d M Y')) ?></div>
            </td>
            <td align="right" valign="bottom" width="38%">
                <div class="toko"><?= esc($invoice->juragan?->nama ?? 'Seven Inc') ?></div>
                <div class="nomor"><?= count($invoice->barang) ?> jenis &nbsp;·&nbsp; <?= array_sum(array_map(static fn ($b): int => (int) $b->qty, $invoice->barang)) ?> potong</div>
            </td>
        </tr>
    </table>

    <table>
        <tr>
            <td class="catatan">
                Lembar ini hanya memuat spesifikasi jahit. Harga, pembayaran dan rekening ada di invoice pelanggan &mdash; jangan ditulis ulang di sini.
            </td>
        </tr>
    </table>

    <div class="sect"></div>

    <table>
        <tr>
            <td class="kotak" width="50%">
                <div class="cap">Jadwal</div>
                <table class="info">
                    <?= $baris('Tenggat selesai', $tenggat['teks'] === '-' ? null : $tenggat['teks']) ?>
                    <?php foreach ($pesanan as $p) { ?>
                        <?php if (in_array($p['label'], ['Diambil', 'Kembali', 'Jaminan'], true)) { ?>
                            <?= $baris($p['label'], $p['nilai']) ?>
                        <?php } ?>
                    <?php } ?>
                    <?= $baris('Tahap terakhir', $terakhir === null ? null : $terakhir['nama'] . ' (' . $terakhir['tanggal'] . ', ' . ($terakhir['selesai'] ? 'selesai' : 'berjalan') . ')') ?>
                </table>
            </td>
            <td class="kotak" width="50%">
                <div class="cap">Pesanan</div>
                <table class="info">
                    <?= $baris('Status', label_status_orderan($invoice->status_pesanan)) ?>
                    <?php foreach ($pesanan as $p) { ?>
                        <?php if ($p['label'] === 'Jenis Layanan') { ?>
                            <?= $baris($p['label'], $p['nilai']) ?>
                        <?php } ?>
                    <?php } ?>
                    <?= $baris('Atas nama pemesan', $invoice->pelanggan?->nama) ?>
                    <?= $baris('Penanggung jawab', $invoice->pengguna?->nama) ?>
                </table>
            </td>
        </tr>
    </table>

    <div class="sect"></div>

    <div class="cap">Spesifikasi per produk</div>

    <?php foreach ($invoice->barang as $b) { ?>
        <table class="item">
            <tr>
                <td class="kepala">
                    <?= esc(strtoupper($b->kode)) ?>
                    <?php if (! empty($b->ukuran)) { ?>
                        &nbsp;/&nbsp;<?= esc(strtoupper($b->ukuran)) ?>
                    <?php } ?>
                    <span class="qty" style="float: right;">&nbsp;<?= (int) $b->qty ?> potong</span>
                </td>
            </tr>
            <?php
            // label spesifikasi yang memang dibutuhkan penjahit; ukuran_detail ikut
            // karena isinya catatan tambahan dari form
            foreach (daftar_rincian($b->rincian ?? null, 'produk') as $r) { ?>
                <tr>
                    <td>
                        <span class="cap"><?= esc($r['label']) ?></span><br>
                        <?= esc($r['nilai']) ?>
                    </td>
                </tr>
            <?php } ?>

            <?php $nilai = $b->nilai_ukuran ?? []; ?>
            <?php if ($nilai !== []) { ?>
                <tr>
                    <td>
                        <span class="cap">Ukuran tercatat</span>
                        <div class="ukuran">
                            <?php foreach ($nilai as $u) { ?>
                                <span class="abu"><?= esc($u[0]) ?>:</span> <strong><?= esc($u[1]) ?></strong> &nbsp;·&nbsp;
                            <?php } ?>
                        </div>
                    </td>
                </tr>
            <?php } ?>

            <?php if (daftar_rincian($b->rincian ?? null, 'produk') === [] && $nilai === []) { ?>
                <tr>
                    <td><span class="abu">Belum ada spesifikasi tercatat untuk produk ini &mdash; cek catatannya di layar sebelum menjahit.</span></td>
                </tr>
            <?php } ?>
        </table>
        <div class="jeda"></div>
    <?php } ?>

    <?php $anggota = $invoice->anggota ?? []; ?>
    <?php if ($anggota !== []) { ?>
        <div class="cap">Ukuran per orang &mdash; <?= count($anggota) ?> orang</div>
        <table class="rapi">
            <thead>
                <tr>
                    <th width="5%" class="tengah">No</th>
                    <th width="22%">Nama</th>
                    <th width="9%" class="tengah">Nomor</th>
                    <th>Ukuran</th>
                    <th width="18%">Catatan</th>
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
    <?php } ?>

    <div class="sect"></div>

    <div class="cap">Rangkaian kerja</div>
    <table class="rapi">
        <thead>
            <tr>
                <th width="6%" class="tengah">&nbsp;</th>
                <th width="26%">Tahap</th>
                <th width="14%" class="tengah">Status</th>
                <th>Catatan tahap</th>
                <th width="18%" class="tengah">Paraf</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($tahap as $no => $t) { ?>
                <?php
                $sudah  = $no <= (int) ($terakhir['no'] ?? 0);
                $selesai = ! $sudah ? false : ($no < (int) ($terakhir['no'] ?? 0) || ($terakhir['selesai'] ?? false));
                $label   = $selesai ? 'Selesai' : ($sudah ? 'Berjalan' : 'Belum');
                ?>
                <tr class="<?= $no % 2 ? 'selang' : ''; ?>">
                    <td class="tengah">
                        <table>
                            <tr>
                                <td class="kotak-check <?= $selesai ? 'cek-isi' : ''; ?>"></td>
                            </tr>
                        </table>
                    </td>
                    <td><?= $no ?>. <?= esc($t[1]) ?></td>
                    <td class="tengah"><?= $label ?></td>
                    <td><?= esc($dicatat[$no] ?? '') ?: '<span class="abu">-</span>' ?></td>
                    <td class="tengah">
                        <table>
                            <tr>
                                <td class="paraf"></td>
                            </tr>
                        </table>
                    </td>
                </tr>
            <?php } ?>
        </tbody>
    </table>

    <table class="kaki">
        <tr>
            <td>Perubahan spesifikasi hanya lewat admin, jangan ditambal di kertas ini.</td>
            <td align="right">Nota <?= esc($invoice->seri) ?> &nbsp;·&nbsp; lembar kerja</td>
        </tr>
    </table>

</body>

</html>
