<?php
/*
 * Daftar baris pembentuk satu angka KPI.
 *
 * Isi pesan WhatsApp dan nama pelanggan tidak ditampilkan di sini: yang dibutuhkan
 * hanya identitas singkat (nomor nota, 4 digit terakhir nomor WA, waktu) supaya
 * halaman ini bisa dibuka tanpa membocorkan percakapan.
 */

$rp    = static fn ($n): string => 'Rp ' . number_format((int) $n, 0, ',', '.');
$tw    = static fn ($v): string => ($v === null || $v === '') ? '&mdash;' : date('j M Y H:i', (int) $v);
$th    = static fn ($v): string => ($v === null || $v === '') ? '&mdash;' : esc((string) $v);
$angka = static fn ($n): string => number_format((float) $n, fmod((float) $n, 1.0) === 0.0 ? 0 : 1, ',', '.');
$durasi = static fn ($v, string $satuan): string => $v === null
    ? '&mdash;'
    : $angka($v) . ' ' . $satuan;

$label_alasan = array_map(static fn ($a) => $a['label'], alasan_batal());

// satu baris = definisi kolom, label, dan cara menampilkan
$kolom = match ($indikator) {
    'sla_fulfillment' => [
        ['id_invoice', 'Nota', $th],
        ['juragan_id', 'Toko', static fn ($v) => $juragans[(int) $v] ?? (string) $v],
        ['tanggal_pesan', 'Tanggal pesan', $th],
        ['waktu_bayar', 'Pembayaran terverifikasi', $tw],
        ['waktu_fu', 'Packing / kirim', $tw],
        ['durasi_jam', 'Durasi', static fn ($v) => $durasi($v, 'jam')],
        ['pelaku', 'Pelaksana', $th],
    ],
    'error_void_rate' => [
        ['id_invoice', 'Nota', $th],
        ['seri', 'Nomor seri', $th],
        ['tanggal_pesan', 'Tanggal pesan', $th],
        ['deleted_at', 'Dihapus', $tw],
        ['alasan_batal', 'Alasan', static fn ($v) => esc($label_alasan[$v] ?? (string) $v)],
        ['pelaku', 'Dihapus oleh', $th],
    ],
    'first_response_time' => [
        ['percakapan_id', 'Percakapan', $th],
        ['nomor', 'Nomor (4 akhir)', $th],
        ['waktu_masuk', 'Pesan masuk', $tw],
        ['waktu_balas', 'Balasan pertama', $tw],
        ['frt_menit', 'Respons', static fn ($v) => $durasi($v, 'menit')],
        ['pelaku', 'Dibalas oleh', $th],
    ],
    'konversi_lead' => [
        ['pelanggan_id', 'Pelanggan', $th],
        ['waktu_mulai', 'Percakapan mulai', $tw],
        ['terkonversi', 'Jadi order', static fn ($v) => (int) $v === 1 ? 'ya' : 'belum'],
        ['pelaku', 'Ditangani', $th],
    ],
    'resolution_time' => [
        ['id_followup', 'Tindak lanjut', $th],
        ['judul', 'Judul', $th],
        ['kategori', 'Kategori', $th],
        ['created_at', 'Dibuat', $tw],
        ['selesai_at', 'Selesai', $tw],
        ['durasi_jam', 'Durasi', static fn ($v) => $durasi($v, 'jam')],
        ['pelaku', 'Ditutup oleh', $th],
    ],
    default => [
        ['id_invoice', 'Nota', $th],
        ['seri', 'Nomor seri', $th],
        ['tanggal_pesan', 'Tanggal pesan', $th],
        ['qty', 'Qty', $th],
        ['gmv', 'Nilai transaksi', static fn ($v) => $rp($v)],
        ['dibayar', 'Dibayar', static fn ($v) => $rp($v)],
        ['status_pembayaran', 'Status bayar', static fn ($v) => esc(status_pembayaran([], (int) $v, 'label'))],
        ['pembuat', 'Pembuat nota', $th],
    ],
};

$t = static function (array $ubah = []) use ($s, $indikator): string {
    $q = array_merge([
        'awal'    => $s['awal'],
        'akhir'   => $s['akhir'],
        'periode' => $s['periode'],
        'peran'   => $s['peran'],
        'pegawai' => $s['user_id'],
        'juragan' => $s['juragan_id'],
    ], $ubah);

    return site_url('admin/kpi/detail/' . $indikator) . '?' . http_build_query(array_filter(
        $q,
        static fn ($v) => $v !== null && $v !== '' && $v !== 0
    ));
};

$total_halaman = max(1, (int) ceil($jumlah / $batas));
$nomor         = $halaman + 1;
$awal_judul    = $jumlah === 0 ? 0 : $halaman * $batas + 1;
$akhir_judul   = min($jumlah, $halaman * $batas + $batas);

$kembali = site_url('admin/kpi') . '?' . http_build_query(array_filter([
    'awal'    => $s['awal'],
    'akhir'   => $s['akhir'],
    'periode' => $s['periode'],
    'peran'   => $s['peran'],
    'pegawai' => $s['user_id'],
    'juragan' => $s['juragan_id'],
], static fn ($v) => $v !== null && $v !== '' && $v !== 0));
?>
<?= $this->extend('template/main') ?>

<?= $this->section('content') ?>
<?= $this->include('admin/navbar') ?>

<div class="container-xxl">
    <h1 class="h3 mt-5">Detail KPI &mdash; <?= esc($aturan['nama'] ?? $indikator) ?></h1>
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb p-0">
            <li class="breadcrumb-item"><?= anchor('admin/dasbor', 'Dasbor') ?></li>
            <li class="breadcrumb-item"><?= anchor('admin/kpi', 'KPI') ?></li>
            <li class="breadcrumb-item active" aria-current="page"><?= esc($indikator) ?></li>
        </ol>
    </nav>

    <div class="alert alert-light border small">
        <div><strong>Definisi:</strong> <?= esc($aturan['definisi'] ?? 'Indikator ini tidak punya baris target, rumusnya mengikuti model.') ?></div>
        <?php if (! empty($aturan['aturan'])) { ?>
            <div class="mt-1"><strong>Aturan hitung:</strong> <?= esc($aturan['aturan']) ?></div>
        <?php } ?>
        <?php if ($aturan !== null && $aturan['target'] !== null) { ?>
            <div class="mt-1">
                <strong>Target:</strong>
                <?= esc(($aturan['arah'] === 'lebih_rendah' ? 'maks ' : 'min ') . $angka($aturan['target']) . ' ' . $aturan['satuan']) ?>
                (bobot <?= esc((string) $aturan['bobot']) ?>)
            </div>
        <?php } ?>
        Periode terbaca: <?= esc($s['awal']) ?> &ndash; <?= esc($s['akhir']) ?>
        <?= $s['user_id'] ? ', karyawan ' . esc($pegawai[$s['user_id']] ?? '-') : '' ?>
    </div>

    <?= $this->include('admin/kpi/saring') ?>

    <div class="card">
        <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
            <span class="fw-semibold small">
                <?= $awal_judul ?>&ndash;<?= $akhir_judul ?> dari <?= (int) $jumlah ?> baris
            </span>
            <a class="btn btn-sm btn-outline-secondary" href="<?= $kembali ?>">
                kembali ke ringkasan
            </a>
        </div>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <?php foreach ($kolom as [$nama, $label]) { ?>
                            <th scope="col"><?= esc($label) ?></th>
                        <?php } ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($baris === []) { ?>
                        <tr>
                            <td colspan="<?= count($kolom) ?>" class="text-center text-muted py-4">
                                Tidak ada baris pada saringan ini. Kalau angka di kartu tetap muncul,
                                jejak yang dibutuhkan untuk daftar ini belum tercatat — bukan berarti
                                tidak terjadi apa-apa.
                            </td>
                        </tr>
                    <?php } ?>
                    <?php foreach ($baris as $b) { ?>
                        <tr>
                            <?php foreach ($kolom as [$nama, , $format]) { ?>
                                <td><?= $format($b[$nama] ?? null) ?></td>
                            <?php } ?>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
        <?php if ($total_halaman > 1) { ?>
            <div class="card-footer bg-white">
                <nav aria-label="Halaman detail">
                    <ul class="pagination pagination-sm mb-0">
                        <?php for ($i = 1; $i <= $total_halaman; $i++) { ?>
                            <?php if ($i === 1 || $i === $total_halaman || abs($i - $nomor) <= 2) { ?>
                                <li class="page-item <?= $i === $nomor ? 'active' : '' ?>">
                                    <a class="page-link" href="<?= $t(['halaman' => $i]) ?>"><?= $i ?></a>
                                </li>
                            <?php } elseif (abs($i - $nomor) === 3) { ?>
                                <li class="page-item disabled"><span class="page-link">&hellip;</span></li>
                            <?php } ?>
                        <?php } ?>
                    </ul>
                </nav>
            </div>
        <?php } ?>
    </div>
</div>
<?= $this->endSection() ?>
