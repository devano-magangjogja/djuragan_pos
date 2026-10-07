<?php
/**
 * Berkas cetak laporan (PDF).
 *
 * Dibaca Dompdf lewat App\Libraries\LaporanEkspor::pdf(), jadi tidak memakai
 * template utama dan tidak punya aset dari luar. Nilainya diformat lewat library
 * yang sama dengan berkas Excel supaya keduanya menyebut angka yang sama.
 */

use App\Libraries\LaporanEkspor;
use App\Models\LaporanModel;

$ekspor = new LaporanEkspor();

// satu nilai mentah -> teks sel; kolom uang ditulis "Rp 1.234", sisanya lewat
// formatter yang sama dengan berkas Excel
$sel = static function (array $kolom, $mentah) use ($ekspor): string {
    if ($kolom[2] === 'rp') {
        return $ekspor->rupiah($mentah);
    }

    $nilai = $ekspor->nilai($kolom[2], $mentah);

    return $nilai === null ? '-' : (string) $nilai;
};
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title><?= esc($judul) ?></title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 9pt; color: #1f2430; margin: 0; }
        h1 { font-size: 15pt; margin: 0 0 6pt; }
        h2 { font-size: 10.5pt; margin: 14pt 0 4pt; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #d8dce5; padding: 3pt 4pt; }
        th { background: #f1f3f8; font-weight: bold; text-align: left; }
        .keteran td { border: none; padding: 1pt 4pt 1pt 0; }
        .keteran td.label { width: 90pt; font-weight: bold; }
        .kanan { text-align: right; }
        .kecil { font-size: 7.5pt; color: #6b7280; }
        .catatan { margin-top: 10pt; }
    </style>
</head>
<body>
    <h1><?= esc($judul) ?></h1>

    <table class="keteran">
        <?php foreach ($keteran as $label => $nilai) { ?>
            <tr>
                <td class="label"><?= esc($label) ?></td>
                <td><?= esc($nilai) ?></td>
            </tr>
        <?php } ?>
    </table>

    <?php foreach ($blok as $bagian) { ?>
        <h2><?= esc($bagian['judul']) ?></h2>

        <?php if ($bagian['baris'] === [] && $bagian['total'] === null) { ?>
            <div class="kecil">Tidak ada baris pada rentang dan filter ini.</div>

        <?php } else { ?>
            <table>
                <thead>
                    <tr>
                        <?php foreach ($bagian['kolom'] as [$label, , , $rata]) { ?>
                            <th class="<?= $rata === 'kanan' ? 'kanan' : '' ?>"><?= esc($label) ?></th>
                        <?php } ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($bagian['baris'] as $isi) { ?>
                        <tr>
                            <?php foreach ($bagian['kolom'] as $kolom) { ?>
                                <?php $nilai = $sel($kolom, $isi[$kolom[1]] ?? null); ?>
                                <td class="<?= $kolom[3] === 'kanan' ? 'kanan' : '' ?>"><?= esc($nilai) ?></td>
                            <?php } ?>
                        </tr>
                    <?php } ?>

                    <?php if ($bagian['total'] !== null) { ?>
                        <tr>
                            <?php foreach ($bagian['kolom'] as $i => $kolom) { ?>
                                <?php $nilai = $i === 0 ? 'Total' : $sel($kolom, $bagian['total'][$i] ?? null); ?>
                                <td class="<?= $kolom[3] === 'kanan' ? 'kanan' : '' ?>">
                                    <?php if (($bagian['total'][$i] ?? null) !== null || $i === 0) { ?>
                                        <strong><?= esc($nilai) ?></strong>
                                    <?php } ?>
                                </td>
                            <?php } ?>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        <?php } ?>
    <?php } ?>

    <?php if ($catatan !== '') { ?>
        <div class="kecil catatan"><?= esc($catatan) ?></div>
    <?php } ?>

    <div class="kecil catatan">
        Kolom Masuk hanya menghitung transfer yang sudah dicek; Tercatat menghitung semua catatan pembayaran.
        Ringkasan dibatasi <?= LaporanModel::KAPASITAS ?> baris dan detail nota <?= LaporanEkspor::BATAS_PDF ?> baris terbaru.
    </div>
</body>
</html>
