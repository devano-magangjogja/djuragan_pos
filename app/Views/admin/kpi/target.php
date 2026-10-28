<?php
/**
 * Target dan bobot KPI.
 *
 * Yang boleh diubah hanya angka target, bobot, dan aktif/tidaknya
 * indikator. Keterangan di sini dibuat satu kalimat biasa; definisi teknis dan
 * rumus hitungnya hanya muncul saat selnya ditahan (tooltip) dan di halaman
 * detail tiap indikator.
 *
 * Variabel di bawah disuntik runtime oleh Admin\Kpi::target() lewat
 * view('admin/kpi/target', $data). CI4 meng-extract array itu saat render,
 * jadi tidak ada assignment di file ini dan Intelephense (P1008)
 * menyangkanya tak terdefinisi. Blok @var ini hanya penanda tipe untuk
 * analyzer, bukan kode yang dijalankan.
 *
 * @var array  $target   daftar indikator: [['indikator','nama','peran','arah','satuan','target','bobot','aktif','definisi','aturan','updated_at','updated_by'], ...]
 * @var bool   $boleh    pengguna boleh mengubah target; khusus Superadmin (admin
 *                       sudah dibelokkan ke Ringkasan oleh Kpi::target(), jadi
 *                       mode baca di bawah ini cuma cadangan kalau rutenya berubah)
 * @var string $kabar    pesan flash hasil simpan, kosong jika tidak ada
 * @var array  $pembaru  [user_id => nama] untuk mencantumkan siapa yang mengubah
 */

/** Bahasa sehari-hari per indikator, tanpa istilah teknis. */
$sederhana = [
    'first_response_time'   => 'Berapa lama pelanggan menunggu balasan pertama.',
    'konversi_lead'         => 'Berapa persen chat baru yang jadi order.',
    'resolution_time'       => 'Berapa lama komplain sampai benar-benar selesai.',
    'gmv_cs'                => 'Total uang masuk dari nota yang dipegang orang ini. Punya dua target: per CS (satu orang) dan Tim CS (total seluruh tim).',
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
                                        <?php if ($t['indikator'] === 'gmv_cs') : ?>
                                            <div class="small text-muted mb-1">Per CS</div>
                                        <?php endif; ?>
                                        <?= form_input([
                                            'class'       => 'form-control form-control-sm',
                                            'type'        => 'number',
                                            'step'        => '0.01',
                                            'name'        => 'target[' . $t['indikator'] . ']',
                                            'placeholder' => $t['satuan'],
                                            'value'       => $t['target'],
                                        ]) ?>
                                        <?php if ($t['indikator'] === 'gmv_cs') : ?>
                                            <div class="small text-muted mt-2 mb-1">Tim CS (total)</div>
                                            <?= form_input([
                                                'class'       => 'form-control form-control-sm',
                                                'type'        => 'number',
                                                'step'        => '0.01',
                                                'name'        => 'target_tim[' . $t['indikator'] . ']',
                                                'placeholder' => $t['satuan'],
                                                'value'       => $t['target_tim'] ?? '',
                                            ]) ?>
                                        <?php endif; ?>
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
                                    <td class="text-end">
                                        <?= $isi($t['target'], $t['satuan']) ?>
                                        <?php if ($t['indikator'] === 'gmv_cs') : ?>
                                            <div class="small text-muted">
                                                tim: <?= $isi($t['target_tim'] ?? null, $t['satuan']) ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
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
            Hanya Superadmin yang boleh menentukan target dan bobot. Akun Anda hanya bisa membaca angka yang berlaku.
        </p>
    <?php } ?>

    <p class="text-muted small">
        Bobot cuma dipakai untuk skor gabungan di halaman Ringkasan. Tidak harus berjumlah 100.
    </p>
</div>
<?= $this->endSection() ?>
