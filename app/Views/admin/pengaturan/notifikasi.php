<?php
/**
 * Variabel dari Admin\Settings\Notifikasi::index() (di-extract oleh CI4 saat runtime).
 *
 * @var string                         $title         Judul halaman
 * @var int                            $terbaca       Notifikasi terbaca milik semua akun
 * @var int                            $belum         Notifikasi yang belum dibaca (tidak pernah disapu)
 * @var int                            $sendiri       Notifikasi terbaca milik akun yang login
 * @var int                            $usang         Perkiraan jumlah yang tersapu pada batas umur sekarang
 * @var array{aktif:bool, umur_hari:int, terakhir:?int, jumlah:int} $setelan Pengaturan yang tersimpan
 * @var ?string                         $sapu_terakhir Waktu sapuan otomatis terakhir, sudah diformat
 * @var ?string                         $sukses
 * @var ?string                         $gagal
 * @var array<string, string>           $errors
 */

$rp = static fn (int $angka): string => number_format($angka, 0, ',', '.');
?>
<?= $this->extend('template/main') ?>

<?= $this->section('css') ?>
<style>
    /* Layar sempit ditata sama seperti halaman daftar lain: tombol kepala
       mengambil satu baris sendiri (tidak menggantung di kanan di bawah
       judul), kartu angka dua per baris, dan kartu terakhir yang ganjil
       memanjang penuh supaya tidak ada separuh baris menganggur. */
    @media (max-width: 575.98px) {
        .pn-head-aksi {
            flex: 1 1 100%;
            justify-content: center;
        }
    }

    /* sepanjang kartu masih col-6 (sampai md aktif di 768px) kartu ketiga yang
       ganjil memanjang penuh — di 576-767px aturannya sama seperti di HP */
    @media (max-width: 767.98px) {
        .pn-angka:last-child:nth-child(odd) {
            flex: 0 0 100%;
            max-width: 100%;
        }
    }
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?= $this->include('admin/navbar') ?>

<div class="container-xxl">
    <?php /* mt-5 menempel di h1, bukan di barisnya: aturan global
       body.has-app-sidebar .h3.mt-5 menormalkan margin itu jadi nol dan sudah
       menyisihkan jarak atas lewat padding container — di sini sebelumnya mt-5
       duduk di pembungkus flex sehingga tidak ikut dinormalkan dan judulnya
       turun ±48px lebih dalam daripada halaman lain */ ?>
    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
        <h1 class="h3 mt-5">Pengaturan Notifikasi</h1>
        <a class="btn btn-sm btn-outline-secondary ms-auto pn-head-aksi" href="<?= site_url(base_user() . '/notifikasi/semua') ?>">
            <i class="fal fa-bell"></i> Buka daftar notifikasi
        </a>
    </div>

    <?php if ($sukses !== null) { ?>
        <div class="alert alert-success py-2" data-flash><?= esc($sukses) ?></div>
    <?php } ?>
    <?php if ($gagal !== null) { ?>
        <div class="alert alert-danger py-2" data-flash><?= esc($gagal) ?></div>
    <?php } ?>
    <?php if ($errors !== []) { ?>
        <div class="alert alert-danger py-2"><?= esc(implode(' ', $errors)) ?></div>
    <?php } ?>

    <div class="row gx-3 gy-3 mb-4">
        <div class="col-6 col-md-4 pn-angka">
            <div class="card h-100 rounded-kartu shadow-kartu">
                <div class="card-body py-3">
                    <div class="text-muted small">Sudah terbaca</div>
                    <div class="fs-5 fw-bold mb-0"><?= esc($rp((int) $terbaca)) ?></div>
                    <div class="small text-muted"><?= esc($rp((int) $sendiri)) ?> di antaranya ada di kotak akun Anda</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-4 pn-angka">
            <div class="card h-100 rounded-kartu shadow-kartu">
                <div class="card-body py-3">
                    <div class="text-muted small">Belum dibaca</div>
                    <div class="fs-5 fw-bold mb-0"><?= esc($rp((int) $belum)) ?></div>
                    <div class="small text-muted">tidak pernah ikut terhapus</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-4 pn-angka">
            <div class="card h-100 rounded-kartu shadow-kartu">
                <div class="card-body py-3">
                    <div class="text-muted small">Melewati batas umur</div>
                    <div class="fs-5 fw-bold mb-0"><?= esc($rp((int) $usang)) ?></div>
                    <div class="small text-muted">
                        <?= $setelan['aktif']
                            ? 'calon sapuan otomatis berikutnya (batas ' . (int) $setelan['umur_hari'] . ' hari)'
                            : 'akan tersapu begitu hapus otomatis dinyalakan' ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row gx-3 gy-3">
        <div class="col-12 col-lg-6">
            <div class="card h-100 rounded-kartu shadow-kartu">
                <div class="card-header fw-semibold">
                    <i class="fal fa-hand-paper text-primary me-1"></i>Hapus manual
                </div>
                <div class="card-body">
                    <?= form_open('admin/settings/notifikasi/hapus') ?>
                    <?= form_label('Cakupan', 'cakupan', ['class' => 'form-label small']) ?>
                    <?= form_dropdown([
                        'class'   => 'form-select form-select-sm',
                        'name'    => 'cakupan',
                        'id'      => 'cakupan',
                        'options' => [
                            'semua'   => 'Semua akun (' . $rp((int) $terbaca) . ' notifikasi terbaca)',
                            'sendiri' => 'Hanya akun saya (' . $rp((int) $sendiri) . ' notifikasi terbaca)',
                        ],
                    ]) ?>
                    <div class="form-text">
                        Riwayat notanya tidak terhapus, hanya notifikasinya saja.
                    </div>
                    <button type="submit" class="btn btn-sm btn-outline-danger mt-3"
                        onclick="return confirm('Hapus semua notifikasi yang sudah terbaca pada cakupan terpilih? Yang belum dibaca dibiarkan.')">
                        <i class="fal fa-trash"></i> Hapus yang sudah terbaca
                    </button>
                    <?= form_close() ?>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-6">
            <div class="card h-100 rounded-kartu shadow-kartu">
                <div class="card-header fw-semibold">
                    <i class="fal fa-clock text-primary me-1"></i>Hapus otomatis
                </div>
                <div class="card-body">
                    <?= form_open('admin/settings/notifikasi/simpan') ?>
                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" name="auto_aktif" id="autoAktif" value="1"
                            <?= $setelan['aktif'] ? 'checked' : '' ?>>
                        <label class="form-check-label" for="autoAktif">Bersihkan kotak notifikasi otomatis</label>
                    </div>
                    <div class="row g-2 align-items-end">
                        <div class="col-6">
                            <?= form_label('Umur maksimal (hari)', 'umur_hari', ['class' => 'form-label small']) ?>
                            <?= form_input([
                                'class' => 'form-control form-control-sm',
                                'type'  => 'number',
                                'name'  => 'umur_hari',
                                'id'    => 'umur_hari',
                                'min'   => \App\Models\NotifikasiModel::UMUR_MIN,
                                'max'   => \App\Models\NotifikasiModel::UMUR_MAKS,
                                'value' => set_value('umur_hari', (string) $setelan['umur_hari']),
                            ]) ?>
                        </div>
                    </div>
                    <div class="small text-muted mt-2">
                        <?php if ($sapu_terakhir !== null) { ?>
                            <i class="fal fa-broom me-1"></i>Pembersihan terakhir: <?= esc($sapu_terakhir) ?>
                            (<?= (int) $setelan['jumlah'] ?> notifikasi terhapus)
                        <?php } else { ?>
                            <i class="fal fa-broom me-1"></i>Belum pernah dijalankan.
                        <?php } ?>
                    </div>
                    <button type="submit" class="btn btn-sm btn-primary mt-3">
                        <i class="fal fa-save"></i> Simpan pengaturan
                    </button>
                    <?= form_close() ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
