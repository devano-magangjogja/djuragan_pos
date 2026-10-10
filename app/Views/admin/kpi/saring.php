<?php
/*
 * Form saringan halaman KPI, dipakai ringkasan dan detail supaya kedua halaman
 * selalu membaca periode, peran, toko, dan karyawan yang sama.
 *
 * Yang sampai ke sini hanya data dari controller: $s, $periode, $peran, $pegawai,
 * $juragans. Alamat tujuan dan field tambahan dibaca sendiri dari URL, jadi form
 * ini tidak perlu disuapi ulang oleh tiap halaman.
 */

$aksi    = trim(uri_string(), '/');
$permintaan = \Config\Services::request();

// pilihan yang sedang aktif di luar form (indikator grafik & jumlah baris) ikut disimpan
$ikut = array_filter([
    'banding' => $permintaan->getGet('banding'),
    'batas'   => $permintaan->getGet('batas'),
], static fn ($v) => $v !== null && $v !== '');
?>
<div class="card mb-3">
    <div class="card-body py-3">
        <?= form_open($aksi, ['method' => 'get', 'class' => 'row g-2 align-items-end']) ?>
        <?php foreach ($ikut as $nama => $nilai) { ?>
            <?= form_input(['type' => 'hidden', 'name' => $nama, 'value' => esc($nilai)]) ?>
        <?php } ?>
        <div class="col-6 col-md-2">
            <?= form_label('Tanggal awal', 'saringAwal', ['class' => 'form-label small']) ?>
            <?= form_input(['class' => 'form-control form-control-sm', 'type' => 'date', 'id' => 'saringAwal', 'name' => 'awal', 'value' => $s['awal']]) ?>
        </div>
        <div class="col-6 col-md-2">
            <?= form_label('Tanggal akhir', 'saringAkhir', ['class' => 'form-label small']) ?>
            <?= form_input(['class' => 'form-control form-control-sm', 'type' => 'date', 'id' => 'saringAkhir', 'name' => 'akhir', 'value' => $s['akhir']]) ?>
        </div>
        <div class="col-6 col-md-2">
            <?= form_label('Periode', 'saringPeriode', ['class' => 'form-label small']) ?>
            <?= form_dropdown([
                'class'    => 'form-select form-select-sm',
                'id'       => 'saringPeriode',
                'name'     => 'periode',
                'options'  => $periode,
                'selected' => $s['periode'],
            ]) ?>
        </div>
        <div class="col-6 col-md-2">
            <?= form_label('Role', 'peran', ['class' => 'form-label small']) ?>
            <?= form_dropdown([
                'class'    => 'form-select form-select-sm',
                'name'     => 'peran',
                'options'  => $peran,
                'selected' => $s['peran'],
            ]) ?>
        </div>
        <div class="col-6 col-md-2">
            <?= form_label('Karyawan', 'pegawai', ['class' => 'form-label small']) ?>
            <?= form_dropdown([
                'class'    => 'form-select form-select-sm',
                'name'     => 'pegawai',
                'options'  => [0 => 'Semua karyawan'] + $pegawai,
                'selected' => $s['user_id'] ?? 0,
            ]) ?>
        </div>
        <div class="col-6 col-md-2">
            <?= form_label('Toko', 'juragan', ['class' => 'form-label small']) ?>
            <?= form_dropdown([
                'class'    => 'form-select form-select-sm',
                'name'     => 'juragan',
                'options'  => [0 => 'Semua toko milik akun ini'] + $juragans,
                'selected' => $s['juragan_id'],
            ]) ?>
        </div>
        <div class="col-12 text-end pt-1">
            <?= form_submit(['class' => 'btn btn-sm btn-primary', 'value' => 'Terapkan']) ?>
            <a class="btn btn-sm btn-link text-muted" href="<?= site_url($aksi) ?>">Reset</a>
        </div>
        <?= form_close(); ?>
    </div>
</div>
<script>
(function () {
    'use strict';

    var periode = document.getElementById('saringPeriode');
    var awal    = document.getElementById('saringAwal');
    var akhir   = document.getElementById('saringAkhir');

    if (! periode || ! awal || ! akhir) {
        return;
    }

    function cetak(tanggal) {
        var bulan = String(tanggal.getMonth() + 1);
        var hari  = String(tanggal.getDate());

        return tanggal.getFullYear() + '-' + (bulan.length < 2 ? '0' + bulan : bulan)
            + '-' + (hari.length < 2 ? '0' + hari : hari);
    }

    // tanggal akhir yang sedang dipakai jadi acuan; periode lain hanya merentangkannya
    function pilih() {
        var acuan = akhir.value ? new Date(akhir.value + 'T00:00:00') : new Date();

        if (isNaN(acuan.getTime())) {
            acuan = new Date();
        }

        var mulai = new Date(acuan.getTime());
        var sampai = new Date(acuan.getTime());

        if (periode.value === 'mingguan') {
            var posisi = (mulai.getDay() + 6) % 7;
            mulai.setDate(mulai.getDate() - posisi);
            sampai.setDate(sampai.getDate() + (6 - posisi));
        } else if (periode.value === 'bulanan') {
            mulai.setDate(1);
            sampai.setMonth(sampai.getMonth() + 1);
            sampai.setDate(0);
        }

        awal.value  = cetak(mulai);
        akhir.value = cetak(sampai);
    }

    periode.addEventListener('change', pilih);
}());
</script>
