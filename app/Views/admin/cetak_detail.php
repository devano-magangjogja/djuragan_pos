<?php
/**
 * Berkas cetak detail transaksi (PDF).
 *
 * Dibaca Dompdf lewat App\Libraries\LaporanEkspor::pdfDetail(). Beda dengan
 * laporan_cetak.php yang menyusun tabel daftar, berkas ini menumpuk satu kartu
 * per nota: kepala, keterangan orderan, barang + biaya + total, lalu riwayat
 * pembayarannya. Satu nota diusahakan utuh dalam satu halaman supaya orang yang
 * membacanya tidak perlu menyambung angka ke halaman sebelah.
 *
 * @var string           $judul   Judul yang dicetak di kepala berkas
 * @var array<string, string> $keteran Filter yang aktif: label => nilai
 * @var list<object>     $nota    Nota lengkap dengan barang/biaya/pembayaran menempel
 * @var string           $catatan Catatan tambahan di bawah keterangan filter
 */

use App\Libraries\LaporanEkspor;

helper('fungsi');

$ekspor = new LaporanEkspor();

// satu nilai mentah -> teks rupiah, formatter yang sama dengan berkas Excel
$rp = static fn ($n): string => $ekspor->rupiah($n);

// kolom hp menyimpan JSON array nomor; dokumen cetak menuliskannya sebagai
// daftar nomor biasa, bukan tanda kurung siku
$teks_hp = static function ($hp): string {
    $daftar = json_decode((string) $hp, true);

    if (! is_array($daftar)) {
        $daftar = [(string) $hp];
    }

    return implode(', ', array_filter(array_map('strval', $daftar)));
};

// tanggal orderan berupa datetime, tapi tanggal pembayaran unix timestamp;
// keduanya harus jadi teks yang sama bentuknya di kertas
$tanggal = static function ($mentah): string {
    $mentah = trim((string) $mentah);

    if (ctype_digit($mentah)) {
        return \CodeIgniter\I18n\Time::createFromTimestamp((int) $mentah)->toLocalizedString('d MMMM yyyy');
    }

    return tanggal_rincian($mentah);
};

// kecamatan/kabupaten/provinsi baris lama menyimpan id RajaOngkir, bukan nama;
// bagian yang murni angka tidak berarti apa-apa bagi pembaca kertas
$teks_alamat = static function (object $p): string {
    $bagian = array_filter([
        $p->alamat ?? '',
        $p->kecamatan ?? '',
        $p->kabupaten ?? '',
        $p->provinsi ?? '',
    ], static fn ($b): bool => trim((string) $b) !== '' && ! ctype_digit(trim((string) $b)));

    return trim(implode(', ', $bagian));
};

// label biaya baris lama berisi angka id, bukan nama biaya
$nama_biaya = static function (object $c): string {
    if ((string) $c->biaya_id === '1') {
        return 'Ongkir';
    }

    $label = trim((string) $c->label);

    return ($label === '' || $label === 'null' || ctype_digit($label)) ? 'Biaya lainnya' : $label;
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
        table { border-collapse: collapse; width: 100%; }
        .keteran td { border: none; padding: 1pt 4pt 1pt 0; vertical-align: top; }
        .keteran td.label { width: 90pt; font-weight: bold; }
        .kecil { font-size: 7.5pt; color: #6b7280; }
        .kanan { text-align: right; }
        .tengah { text-align: center; }
        .catatan { margin-top: 8pt; }

        /* satu kartu per nota; halaman baru dimulai tiap nota kecuali yang terakhir */
        .nota { page-break-after: always; padding-top: 10pt; }
        .nota.terakhir { page-break-after: auto; }

        .kepala { border-bottom: 2pt solid #1f2430; padding-bottom: 4pt; margin-bottom: 6pt; }
        .kepala table td { border: none; padding: 0; vertical-align: bottom; }
        .toko { font-size: 11pt; font-weight: bold; }
        .seri { font-size: 12pt; font-weight: bold; }

        .info td { border: none; padding: 1.5pt 4pt 1.5pt 0; vertical-align: top; }
        .info td.label { width: 78pt; font-weight: bold; color: #4b5563; }
        .info td.isi { width: 190pt; }

        .isi-barang { margin-top: 6pt; }
        .isi-barang th, .isi-barang td { border: 1px solid #d8dce5; padding: 3pt 4pt; vertical-align: top; }
        .isi-barang th { background: #f1f3f8; font-weight: bold; text-align: left; }
        .isi-barang td.kode { width: 46%; }
        .isi-barang tr.total td { background: #f7f8fb; font-weight: bold; }
        .isi-barang tr.sisa td { color: #b91c1c; font-weight: bold; }
        .rincian-item { color: #6b7280; font-size: 7.5pt; }

        .bayar { margin-top: 8pt; }
        .bayar th, .bayar td { border: 1px solid #d8dce5; padding: 3pt 4pt; }
        .bayar th { background: #f1f3f8; font-weight: bold; text-align: left; }
        .judul-blok { font-size: 9.5pt; font-weight: bold; margin: 8pt 0 3pt; }
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

    <?php if ($catatan !== '') { ?>
        <div class="kecil catatan"><?= esc($catatan) ?></div>
    <?php } ?>

    <?php foreach ($nota as $i => $n) { ?>
        <?php
        $harga_barang = 0;

        foreach ($n->barang as $b) {
            $harga_barang += (int) $b->qty * (int) $b->harga;
        }

        $tagihan = $harga_barang;

        foreach ($n->biaya as $c) {
            $tagihan += (int) $c->nominal;
        }

        $masuk = 0;

        foreach ($n->pembayaran as $y) {
            if ((string) $y->status === '3') {
                $masuk += (int) $y->nominal;
            }
        }

        $sisa      = max($tagihan - $masuk, 0);
        $tenggat   = label_deadline(($n->deadline ?? '') ?: deadline_iso($n->rincian ?? null));
        $alamat    = $n->pelanggan === null ? '' : $teks_alamat($n->pelanggan);
        ?>
        <div class="nota<?= $i === count($nota) - 1 ? ' terakhir' : '' ?>">
            <div class="kepala">
                <table>
                    <tr>
                        <td>
                            <div class="toko"><?= esc($n->juragan->nama_juragan ?? '-') ?></div>
                            <div class="kecil">Detail transaksi</div>
                        </td>
                        <td class="kanan">
                            <div class="seri">#<?= esc($n->seri) ?></div>
                            <div class="kecil">Dipesan <?= esc(tanggal_rincian((string) $n->tanggal_pesan)) ?></div>
                        </td>
                    </tr>
                </table>
            </div>

            <table class="info">
                <tr>
                    <td class="label">Pemesan</td>
                    <td class="isi">
                        <?= esc($n->pelanggan->nama_pelanggan ?? '-') ?>
                        <?php if (! empty($n->pelanggan->hp)) { ?>
                            <span class="kecil">(&<?= esc($teks_hp($n->pelanggan->hp)) ?>)</span>
                        <?php } ?>
                    </td>
                    <td class="label">Status orderan</td>
                    <td><?= esc(label_status_orderan($n->status_pesanan)) ?></td>
                </tr>
                <?php if ($alamat !== '') { ?>
                    <tr>
                        <td class="label">Alamat</td>
                        <td class="isi"><?= esc($alamat) ?></td>
                        <td class="label">Pembayaran</td>
                        <td><?= esc(status_pembayaran($n->pembayaran, $n->status_pembayaran, 'label')) ?></td>
                    </tr>
                <?php } else { ?>
                    <tr>
                        <td class="label">Pembayaran</td>
                        <td class="isi"><?= esc(status_pembayaran($n->pembayaran, $n->status_pembayaran, 'label')) ?></td>
                        <td></td>
                        <td></td>
                    </tr>
                <?php } ?>
                <tr>
                    <td class="label">Kirim ke</td>
                    <td class="isi"><?= esc($n->kirimKe->nama_pelanggan ?? '-') ?></td>
                    <td class="label">Pengiriman</td>
                    <td><?= esc($ekspor->nilai('kirim', $n->status_pengiriman)) ?></td>
                </tr>
                <tr>
                    <td class="label">Tenggat</td>
                    <td class="isi"><?= esc($tenggat['teks']) ?></td>
                    <td class="label">Dicatat oleh</td>
                    <td><?= esc($n->pengguna->nama ?? '-') ?></td>
                </tr>
            </table>

            <table class="isi-barang">
                <thead>
                    <tr>
                        <th class="kode">Produk</th>
                        <th class="tengah" style="width: 8%;">Qty</th>
                        <th class="kanan" style="width: 22%;">Harga</th>
                        <th class="kanan" style="width: 24%;">Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($n->barang === []) { ?>
                        <tr>
                            <td colspan="4" class="kecil">Tidak ada baris barang pada nota ini.</td>
                        </tr>
                    <?php } ?>

                    <?php foreach ($n->barang as $b) { ?>
                        <?php $rincian_item = daftar_rincian($b->rincian ?? null, 'produk'); ?>
                        <tr>
                            <td class="kode">
                                <?= esc(strtoupper((string) $b->kode)) ?>
                                <?php if (! empty($b->ukuran)) { ?>
                                    / <?= esc(strtoupper((string) $b->ukuran)) ?>
                                <?php } ?>
                                <?php if ($rincian_item !== []) { ?>
                                    <div class="rincian-item">
                                        <?php
                                        $bagian = [];

                                        foreach ($rincian_item as $item) {
                                            $bagian[] = $item['label'] . ': ' . $item['nilai'];
                                        }

                                        echo esc(implode(' · ', $bagian));
                                        ?>
                                    </div>
                                <?php } ?>
                            </td>
                            <td class="tengah"><?= (int) $b->qty ?></td>
                            <td class="kanan"><?= esc($rp($b->harga)) ?></td>
                            <td class="kanan"><?= esc($rp((int) $b->harga * (int) $b->qty)) ?></td>
                        </tr>
                    <?php } ?>

                    <?php foreach ($n->biaya as $c) { ?>
                        <tr>
                            <td class="kode" colspan="3">
                                <span class="kecil"><?= esc($nama_biaya($c)) ?></span>
                            </td>
                            <td class="kanan"><?= esc($rp($c->nominal)) ?></td>
                        </tr>
                    <?php } ?>

                    <tr>
                        <td colspan="3" class="kanan">Subtotal barang</td>
                        <td class="kanan"><?= esc($rp($harga_barang)) ?></td>
                    </tr>
                    <tr class="total">
                        <td colspan="3" class="kanan">Total tagihan</td>
                        <td class="kanan"><?= esc($rp($tagihan)) ?></td>
                    </tr>
                    <tr>
                        <td colspan="3" class="kanan">Dana masuk (transfer tercek)</td>
                        <td class="kanan"><?= esc($rp($masuk)) ?></td>
                    </tr>
                    <?php if ($sisa > 0) { ?>
                        <tr class="sisa">
                            <td colspan="3" class="kanan">Sisa</td>
                            <td class="kanan"><?= esc($rp($sisa)) ?></td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>

            <?php if ($n->pembayaran !== []) { ?>
                <div class="judul-blok">Riwayat pembayaran</div>

                <table class="bayar">
                    <thead>
                        <tr>
                            <th style="width: 22%;">Tanggal</th>
                            <th>Dana</th>
                            <th class="kanan" style="width: 24%;">Nominal</th>
                            <th style="width: 26%;">Status cek</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($n->pembayaran as $y) { ?>
                            <tr>
                                <td><?= esc($tanggal($y->tanggal_bayar)) ?></td>
                                <td>
                                    <?= esc($y->bank ?? '-') ?>
                                    <?php if (! empty($y->atas_nama)) { ?>
                                        <span class="kecil">a/n <?= esc($y->atas_nama) ?></span>
                                    <?php } ?>
                                </td>
                                <td class="kanan"><?= esc($rp($y->nominal)) ?></td>
                                <td><?= esc(label_bayar_cek($y->status)['teks']) ?></td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            <?php } ?>
        </div>
    <?php } ?>

    <?php if ($nota === []) { ?>
        <div class="kecil">Tidak ada nota pada rentang dan filter ini.</div>
    <?php } ?>
</body>
</html>
