<?php
/*
 * Target dan bobot KPI.
 *
 * Yang boleh diubah hanya angka target, bobot, dan aktif/tidaknya
 * indikator. Keterangan di sini dibuat satu kalimat biasa; definisi teknis dan
 * rumus hitungnya hanya muncul saat selnya ditahan (tooltip) dan di halaman
 * detail tiap indikator.
 */

/** Bahasa sehari-hari per indikator, tanpa istilah teknis. */
$sederhana = [
    'sla_fulfillment'       => 'Rata-rata lama pesanan disiapkan setelah dibayar.',
    'return_error_rate'     => 'Berapa persen pesanan diretur karena salah input.',
    'kecepatan_master_data' => 'Rata-rata waktu untuk memasukkan barang baru.',
    'error_void_rate'       => 'Berapa persen nota dibatalkan karena salah input.',
    'first_response_time'   => 'Berapa lama pelanggan menunggu balasan pertama.',
    'konversi_lead'         => 'Berapa persen chat baru yang jadi order.',
    'resolution_time'       => 'Berapa lama komplain sampai benar-benar selesai.',
    'gmv_cs'                => 'Total uang masuk dari nota yang dipegang orang ini.',
];

$isi = static function ($nilai, string $satuan): string {
    if ($nilai === null || $nilai === '') {
        return '&mdash;';
    }

    $n = (float) $nilai;

    if ($satuan === 'rupiah') {
        return 'Rp ' . number_format($n, 0, ',', '.');
    }

    return number_format($n, 1, ',', '.') . ' ' . $satuan;
};
?>
<?= $this->extend('template/main') ?>

<?= $this->section('content') ?>
<?= $this->include('admin/navbar') ?>

<div class="container-xxl">
    <h1 class="h3 mt-5">Target dan Bobot KPI</h1>
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb p-0">
            <li class="breadcrumb-item"><?= anchor('admin/dasbor', 'Dasbor') ?></li>
            <li class="breadcrumb-item"><?= anchor('admin/kpi', 'KPI') ?></li>
            <li class="breadcrumb-item active" aria-current="page">Target</li>
        </ol>
    </nav>

    <ul class="nav nav-pills flex-wrap gap-1 mb-3">
        <li class="nav-item">
            <a class="nav-link py-1 px-3" href="<?= site_url('admin/kpi') ?>">
                <i class="fal fa-bullseye me-1"></i>Ringkasan
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link py-1 px-3" href="<?= site_url('admin/kpi/ranking') ?>">
                <i class="fal fa-trophy me-1"></i>Ranking
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link py-1 px-3 active" href="<?= site_url('admin/kpi/target') ?>">
                <i class="fal fa-target me-1"></i>Target dan bobot
            </a>
        </li>
    </ul>

    <?php if ($kabar !== '') { ?>
        <div class="alert alert-success py-2"><?= esc($kabar) ?></div>
    <?php } ?>

    <p class="text-muted small">
        Isi target sesuai keputusan manajemen. Yang masih kosong belum dinilai, jadi kartu di
        halaman Ringkasan menulis "target belum diisi".
    </p>

    <div class="card mb-4">
        <div class="card-body pb-0">
            <?= form_open('admin/kpi/target') ?>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th scope="col">Indikator</th>
                            <th scope="col" style="min-width: 200px;">Keterangan</th>
                            <th scope="col" class="text-center">Arah</th>
                            <?php if ($boleh) { ?>
                                <th scope="col" style="width: 150px;">Target</th>
                                <th scope="col" style="width: 100px;">Bobot %</th>
                                <th scope="col" class="text-center">Aktif</th>
                            <?php } else { ?>
                                <th scope="col" class="text-end">Target</th>
                                <th scope="col" class="text-end">Bobot</th>
                            <?php } ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($target as $t) { ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold"><?= esc($t['nama']) ?></div>
                                    <div class="small text-muted">
                                        role <?= esc($t['peran'] === 'cs' ? 'CS' : ucfirst($t['peran'])) ?>
                                        <?php if ($t['updated_at']) { ?>
                                            &middot; diubah <?= date('j M Y', (int) $t['updated_at']) ?>
                                            <?php if (isset($pembaru[(int) $t['updated_by']])) { ?>
                                                oleh <?= esc($pembaru[(int) $t['updated_by']]) ?>
                                            <?php } ?>
                                        <?php } ?>
                                    </div>
                                </td>
                                <td class="small" title="<?= esc(trim($t['definisi'] . ' ' . $t['aturan'])) ?>">
                                    <?= esc($sederhana[$t['indikator']] ?? $t['definisi']) ?>
                                </td>
                                <td class="text-center small">
                                    <?= $t['arah'] === 'lebih_rendah' ? 'makin kecil makin baik' : 'makin besar makin baik' ?>
                                    <div class="text-muted">satuan: <?= esc($t['satuan']) ?></div>
                                </td>
                                <?php if ($boleh) { ?>
                                    <td>
                                        <?= form_input([
                                            'class'       => 'form-control form-control-sm',
                                            'type'        => 'number',
                                            'step'        => '0.01',
                                            'name'        => 'target[' . $t['indikator'] . ']',
                                            'placeholder' => $t['satuan'],
                                            'value'       => $t['target'],
                                        ]) ?>
                                    </td>
                                    <td>
                                        <?= form_input([
                                            'class' => 'form-control form-control-sm',
                                            'type'  => 'number',
                                            'step'  => '1',
                                            'min'   => '0',
                                            'max'   => '100',
                                            'name'  => 'bobot[' . $t['indikator'] . ']',
                                            'value' => $t['bobot'],
                                        ]) ?>
                                    </td>
                                    <td class="text-center">
                                        <?= form_checkbox([
                                            'name'    => 'aktif[' . $t['indikator'] . ']',
                                            'value'   => '1',
                                            'checked' => (int) $t['aktif'] === 1,
                                            'class'   => 'form-check-input',
                                        ]) ?>
                                    </td>
                                <?php } else { ?>
                                    <td class="text-end"><?= $isi($t['target'], $t['satuan']) ?></td>
                                    <td class="text-end"><?= esc((string) $t['bobot']) ?></td>
                                <?php } ?>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
            <?php if ($boleh) { ?>
                <div class="card-footer bg-white text-end">
                    <?= form_submit(['class' => 'btn btn-sm btn-primary', 'value' => 'Simpan semua target']) ?>
                </div>
            <?php } ?>
            <?= form_close(); ?>
        </div>
    </div>

    <?php if (! $boleh) { ?>
        <p class="alert alert-light border small">
            Halaman ini untuk admin dan superadmin. Akun Anda hanya boleh membaca target yang berlaku.
        </p>
    <?php } ?>

    <p class="text-muted small">
        Bobot cuma dipakai untuk skor gabungan di halaman Ringkasan. Tidak harus berjumlah 100.
    </p>
</div>
<?= $this->endSection() ?>
