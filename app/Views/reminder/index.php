<?php

/**
 * Halaman Reminder Penjahit. Data dikirim Reminder::index() lewat view() lalu
 * di-extract CI4 saat render, jadi tidak ada assignment yang terlihat analis
 * statis; blok @var ini memberi jenisnya.
 *
 * @var string $title
 * @var int    $hari        ambang H hari sebelum deadline (pengaturan Superadmin)
 * @var array  $reminder    daftar orderan dari InvoiceModel::pengingatPenjahit()
 * @var bool   $superadmin  boleh mengubah ambang hari?
 */

$level        = (string) session()->get('level');
$base_lihat   = $level === 'cs' ? site_url('user/invoices/lihat') : site_url('admin/invoices/lihat/semua');
$hari_ini     = date('Y-m-d');
$lunas        = ['5', '6'];
$sukses       = session()->getFlashdata('sukses');
?>
<?= $this->extend('template/main') ?>

<?= $this->section('css') ?>
<style>
    .reminder-halaman { max-width: 940px; }
    .reminder-head {
        display: flex;
        flex-wrap: wrap;
        align-items: flex-end;
        justify-content: space-between;
        gap: .75rem;
        margin: 1.75rem 0 1.25rem;
    }
    .reminder-head h1 {
        margin: 0;
        font-size: 1.6rem;
        font-weight: 800;
        letter-spacing: -.01em;
        color: #0f172a;
    }
    .reminder-head p { margin: .3rem 0 0; font-size: .9rem; color: #64748b; }
    .reminder-periode {
        display: inline-flex;
        align-items: center;
        gap: .45rem;
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 999px;
        padding: .45rem .85rem;
        font-size: .78rem;
        font-weight: 600;
        color: #475569;
        box-shadow: 0 1px 2px rgba(15, 23, 42, .04);
        white-space: nowrap;
    }
    .reminder-periode i { color: #da251d; }

    .reminder-kartu-kecil {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 1.15rem;
        box-shadow: 0 1px 2px rgba(15, 23, 42, .04), 0 8px 24px -12px rgba(15, 23, 42, .12);
    }
    .reminder-setelan {
        padding: 1.1rem 1.25rem;
        margin-bottom: 1.1rem;
    }
    .reminder-setelan h2 {
        margin: 0 0 .2rem;
        font-size: .82rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .06em;
        color: #334155;
    }
    .reminder-setelan .hint { margin: 0 0 .85rem; font-size: .82rem; color: #64748b; }
    .reminder-setelan-baris { display: flex; flex-wrap: wrap; align-items: center; gap: .6rem; }
    .reminder-setelan-baris .input-group { max-width: 220px; }
    .reminder-setelan-baris input { text-align: center; }

    .reminder-list { display: grid; gap: .8rem; }
    .reminder-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        padding: .95rem 1.15rem;
        text-decoration: none;
        transition: box-shadow .15s ease, border-color .15s ease;
    }
    .reminder-item:hover { border-color: #cbd5e1; box-shadow: 0 1px 2px rgba(15, 23, 42, .05), 0 12px 28px -14px rgba(15, 23, 42, .2); }
    .reminder-item.lewat { border-left: 3px solid #da251d; }
    .reminder-item.dekat { border-left: 3px solid #f59e0b; }
    .reminder-item.normal { border-left: 3px solid #0ea5e9; }
    .reminder-kiri { min-width: 0; }
    .reminder-seri { font-weight: 700; color: #0f172a; font-size: .98rem; }
    .reminder-meta { margin-top: .15rem; font-size: .82rem; color: #64748b; display: flex; flex-wrap: wrap; gap: .35rem .75rem; }
    .reminder-meta span { display: inline-flex; align-items: center; gap: .3rem; }
    .reminder-meta i { color: #94a3b8; }
    .reminder-kanan { text-align: right; flex-shrink: 0; }
    .reminder-tanggal { font-size: .82rem; color: #475569; font-weight: 600; }
    .reminder-pill {
        display: inline-block;
        margin-top: .35rem;
        padding: .2rem .6rem;
        border-radius: 999px;
        font-size: .74rem;
        font-weight: 700;
        white-space: nowrap;
    }
    .reminder-pill.lewat { background: #fdf1f0; color: #b81c16; }
    .reminder-pill.dekat { background: #fef3e2; color: #b45309; }
    .reminder-pill.normal { background: #e6f4fb; color: #0369a1; }
    .reminder-kosong { padding: 3rem 1rem; text-align: center; color: #94a3b8; }
    .reminder-kosong i { font-size: 2.2rem; display: block; margin-bottom: .6rem; }
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?= $this->include($level === 'cs' ? 'user/navbar' : 'admin/navbar') ?>

<div class="container-xxl reminder-halaman">

    <div class="reminder-head">
        <div>
            <h1><i class="fal fa-cut me-1 text-brand-500"></i> <?= esc($title) ?></h1>
            <p>Orderan pembuatan/jual yang tenggatnya sudah dekat — hubungi penjahit sebelum lewat.</p>
        </div>
        <span class="reminder-periode">
            <i class="fal fa-hourglass-half"></i> muncul mulai H-<?= (int) $hari ?> sebelum deadline
        </span>
    </div>

    <?php if ($sukses !== null) : ?>
        <div class="alert alert-success py-2" data-flash><?= esc($sukses) ?></div>
    <?php endif; ?>

    <?php if ($superadmin) : ?>
        <div class="reminder-kartu-kecil reminder-setelan">
            <h2><i class="fal fa-sliders-h me-1 text-brand-500"></i> Pengaturan Reminder</h2>
            <p class="hint">Tentukan berapa hari sebelum deadline sebuah orderan mulai muncul di sini. Makin besar angkanya, makin awal reminder muncul.</p>
            <?= form_open('admin/reminder/pengaturan', ['class' => 'reminder-setelan-baris']) ?>
                <div class="input-group">
                    <input type="number" name="hari" class="form-control form-control-sm" min="0" max="90" value="<?= (int) $hari ?>" required>
                    <span class="input-group-text">hari sebelum deadline</span>
                </div>
                <button type="submit" class="btn btn-sm btn-primary"><i class="fal fa-save me-1"></i> Simpan</button>
            <?= form_close() ?>
        </div>
    <?php endif; ?>

    <?php if ($reminder === []) : ?>
        <div class="reminder-kartu-kecil">
            <div class="reminder-kosong">
                <i class="fal fa-circle-check text-success"></i>
                Tidak ada orderan yang mendekati deadline. Aman!
            </div>
        </div>
    <?php else : ?>
        <div class="reminder-list">
            <?php foreach ($reminder as $r) : ?>
                <?php
                $deadline = (string) ($r['deadline'] ?? '');
                $selisih  = (int) round((strtotime($deadline) - strtotime($hari_ini)) / 86400); // hitung hari kalender

                if ($selisih < 0) {
                    $kelas = 'lewat';
                    $label = 'Lewat ' . abs($selisih) . ' hari';
                } elseif ($selisih === 0) {
                    $kelas = 'lewat';
                    $label = 'Jatuh tempo hari ini';
                } elseif ($selisih <= 2) {
                    $kelas = 'dekat';
                    $label = $selisih . ' hari lagi';
                } else {
                    $kelas = 'normal';
                    $label = $selisih . ' hari lagi';
                }

                $sudah_lunas = in_array((string) ($r['status_pembayaran'] ?? ''), $lunas, true);
                ?>
                <a class="reminder-kartu-kecil reminder-item <?= $kelas ?>"
                    href="<?= esc($base_lihat . '?cari=' . rawurlencode((string) $r['seri'])) ?>">
                    <div class="reminder-kiri">
                        <div class="reminder-seri"><?= esc($r['seri']) ?></div>
                        <div class="reminder-meta">
                            <span><i class="fal fa-user"></i> <?= esc($r['nama_pelanggan'] ?: 'Tanpa nama') ?></span>
                            <span><i class="fal fa-store"></i> <?= esc($r['nama_juragan'] ?: '-') ?></span>
                            <span>
                                <i class="fal fa-<?= $sudah_lunas ? 'check-circle text-success' : 'clock text-warning' ?>"></i>
                                <?= $sudah_lunas ? 'Lunas' : 'Belum lunas' ?>
                            </span>
                        </div>
                    </div>
                    <div class="reminder-kanan">
                        <div class="reminder-tanggal"><i class="fal fa-calendar-day me-1"></i><?= esc(tanggal_rincian($deadline)) ?></div>
                        <span class="reminder-pill <?= $kelas ?>"><?= esc($label) ?></span>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

</div>

<?= $this->endSection() ?>

<?= $this->section('js') ?>
<script defer src="https://kit.fontawesome.com/22a9d93fa2.js" data-auto-replace-svg="nest" crossorigin="anonymous"></script>
<?= $this->endSection() ?>
