<?php

/**
 * Daftar sesi checklist (toko harian + pemeriksaan email).
 *
 * @var array $baris  hasil ChecklistModel::daftar()
 * @var array $s      saringan yang sudah dibersihkan controller
 * @var array $jenis  ChecklistModel::JENIS
 * @var array $toko   id_toko => nama toko yang boleh diakses akun ini
 */

$level   = (string) session()->get('level');
$hari_ini = date('Y-m-d');

$tanya = static function (array $ubah): string {
    $q = array_merge($_GET, $ubah);

    if (! array_key_exists('halaman', $ubah)) {
        unset($q['halaman']);
    }

    foreach (['jenis', 'status', 'tanggal', 'toko', 'batas', 'halaman'] as $k) {
        if (isset($q[$k]) && $q[$k] === '') {
            unset($q[$k]);
        }
    }

    return site_url('admin/checklist') . '?' . http_build_query($q);
};
?>
<?= $this->extend('template/main') ?>

<?= $this->section('css') ?>
<style>
    .ckl-halaman { max-width: 1180px; }
    .ckl-head { display: flex; flex-wrap: wrap; align-items: center; gap: .6rem; margin-bottom: 1rem; }
    .ckl-head p { margin: .3rem 0 0; font-size: .9rem; color: #64748b; }

    .ckl-kartu {
        background: #fff; border: 1px solid #e2e8f0; border-radius: 1.15rem;
        box-shadow: 0 1px 2px rgba(15, 23, 42, .04), 0 8px 24px -12px rgba(15, 23, 42, .12);
        overflow: hidden; margin-bottom: 1rem;
    }
    .ckl-kartu > .ckl-judul {
        padding: .7rem 1rem; border-bottom: 1px solid #eaecf0;
        font-size: .78rem; font-weight: 700; color: #344054;
        text-transform: uppercase; letter-spacing: .05em;
    }

    .ckl-buka { display: flex; flex-wrap: wrap; align-items: flex-end; gap: .55rem; padding: 1rem; }
    .ckl-buka .lapangan { display: flex; flex-direction: column; gap: .2rem; }
    .ckl-buka label { font-size: .7rem; font-weight: 700; color: #667085; text-transform: uppercase; letter-spacing: .04em; }
    .ckl-buka .form-control, .ckl-buka .form-select { min-width: 150px; max-width: 230px; }

    .ckl-saring { display: flex; flex-wrap: wrap; align-items: flex-end; gap: .55rem; padding: 1rem; border-bottom: 1px solid #eaecf0; }
    .ckl-saring .lapangan { display: flex; flex-direction: column; gap: .2rem; }
    .ckl-saring label { font-size: .7rem; font-weight: 700; color: #667085; text-transform: uppercase; letter-spacing: .04em; }
    .ckl-saring .form-control, .ckl-saring .form-select { max-width: 180px; }

    .table.ckl-daftar { margin: 0; }
    .ckl-daftar th { font-size: .72rem; text-transform: uppercase; letter-spacing: .05em; color: #667085; background: #f9fafb; }
    .ckl-daftar td { vertical-align: middle; font-size: .86rem; }
    .ckl-nama { font-weight: 700; color: #0f172a; text-decoration: none; display: block; }
    .ckl-nama:hover { color: #da251d; }
    .ckl-sub { font-size: .76rem; color: #64748b; display: flex; flex-wrap: wrap; gap: .2rem .6rem; margin-top: .1rem; }
    .ckl-kosong { padding: 2.6rem 1rem; text-align: center; color: #94a3b8; }
    .ckl-kosong i { font-size: 2rem; display: block; margin-bottom: .5rem; }

    .pil-ckl { display: inline-block; padding: .18rem .6rem; border-radius: 999px; font-size: .72rem; font-weight: 700; white-space: nowrap; }
    .c-berjalan { background: #fef3e2; color: #b45309; }
    .c-selesai { background: #e7f6ec; color: #14663a; }

    .ckl-meter { display: flex; align-items: center; gap: .5rem; min-width: 130px; }
    .ckl-meter .batang { flex: 1; height: 6px; border-radius: 999px; background: #eef1f6; overflow: hidden; }
    .ckl-meter .isi { display: block; height: 100%; background: #16a34a; }
    .ckl-meter span { font-size: .74rem; color: #475569; white-space: nowrap; }

    .ckl-pager { padding: .8rem 1rem; background: #fff; border-top: 1px solid #eaecf0; }
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?= $this->include($level === 'cs' ? 'user/navbar' : 'admin/navbar') ?>

<div class="container-xxl ckl-halaman">

    <div class="ckl-head">
        <div class="me-auto">
            <h1 class="h3 mt-5"><i class="fal fa-ballot-check me-1 text-brand-500"></i> <?= esc($title) ?></h1>
            <p>Pemeriksaan rutin toko dan kotak email. Satu sasaran satu lembar per tanggal &mdash; sesinya otomatis jadi tugas di antrean CS.</p>
        </div>
        <?php if ($kelola) : ?>
            <a class="btn btn-sm btn-outline-secondary rounded-pill" href="<?= esc(site_url('admin/checklist/aturan')) ?>">
                <i class="fal fa-list-check me-1"></i> Templat Butir
            </a>
        <?php endif; ?>
    </div>

    <?php if ($kabar !== '') : ?>
        <div class="alert alert-success py-2"><?= esc($kabar) ?></div>
    <?php endif; ?>
    <?php if ($galat !== '') : ?>
        <div class="alert alert-danger py-2"><?= esc($galat) ?></div>
    <?php endif; ?>

    <?php if ($toko !== []) : ?>
        <div class="ckl-kartu">
            <div class="ckl-judul">Buka lembar hari ini</div>
            <?= form_open('admin/checklist/mulai', ['class' => 'ckl-buka']) ?>
                <div class="lapangan">
                    <label for="jenis-buka">Jenis</label>
                    <select class="form-select form-select-sm" id="jenis-buka" name="jenis"
                        onchange="var t=document.getElementById('lapangan-toko'),m=document.getElementById('lapangan-email');
                                   t.style.display=this.value==='toko'?'':'none';m.style.display=this.value==='email'?'':'none';">
                        <?php foreach ($jenis as $kode => $nama) : ?>
                            <option value="<?= esc($kode) ?>"><?= esc($nama) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="lapangan" id="lapangan-toko">
                    <label for="toko-buka">Toko</label>
                    <select class="form-select form-select-sm" id="toko-buka" name="toko_id">
                        <?php foreach ($toko as $id_toko => $nama_t) : ?>
                            <option value="<?= (int) $id_toko ?>"><?= esc($nama_t) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="lapangan" id="lapangan-email" style="display:none">
                    <label for="akun-buka">Akun email</label>
                    <input type="email" class="form-control form-control-sm" id="akun-buka" name="akun_email"
                        maxlength="120" placeholder="halo@toko.com">
                </div>
                <div class="lapangan">
                    <label for="tanggal-buka">Tanggal</label>
                    <input type="date" class="form-control form-control-sm" id="tanggal-buka" name="tanggal" value="<?= $hari_ini ?>">
                </div>
                <div class="lapangan">
                    <label>&nbsp;</label>
                    <button type="submit" class="btn btn-sm btn-dark">
                        <i class="fal fa-plus me-1"></i> Buka / Lanjutkan
                    </button>
                </div>
            <?= form_close() ?>
        </div>
    <?php endif; ?>

    <div class="ckl-kartu">
        <form class="ckl-saring" method="get" action="<?= esc(site_url('admin/checklist')) ?>">
            <div class="lapangan">
                <label for="jenis">Jenis</label>
                <select class="form-select form-select-sm" id="jenis" name="jenis">
                    <option value="">Semua jenis</option>
                    <?php foreach ($jenis as $kode => $nama) : ?>
                        <option value="<?= esc($kode) ?>" <?= $s['jenis'] === $kode ? 'selected' : '' ?>><?= esc($nama) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="lapangan">
                <label for="status">Status sesi</label>
                <select class="form-select form-select-sm" id="status" name="status">
                    <option value="">Semua</option>
                    <option value="berjalan" <?= $s['status'] === 'berjalan' ? 'selected' : '' ?>>Berjalan</option>
                    <option value="selesai" <?= $s['status'] === 'selesai' ? 'selected' : '' ?>>Selesai</option>
                </select>
            </div>
            <?php if (count($toko) > 1) : ?>
                <div class="lapangan">
                    <label for="toko">Toko</label>
                    <select class="form-select form-select-sm" id="toko" name="toko">
                        <option value="">Semua toko</option>
                        <?php foreach ($toko as $id_toko => $nama_t) : ?>
                            <option value="<?= (int) $id_toko ?>" <?= (int) $s['toko_id'] === (int) $id_toko ? 'selected' : '' ?>>
                                <?= esc($nama_t) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>
            <div class="lapangan">
                <label for="tanggal">Tanggal</label>
                <input type="date" class="form-control form-control-sm" id="tanggal" name="tanggal" value="<?= esc($s['tanggal']) ?>">
            </div>
            <div class="lapangan">
                <label for="batas">Baris</label>
                <select class="form-select form-select-sm" id="batas" name="batas">
                    <?php foreach ([10, 25, 50] as $n) : ?>
                        <option value="<?= $n ?>" <?= $batas === $n ? 'selected' : '' ?>><?= $n ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="lapangan">
                <label>&nbsp;</label>
                <div class="d-inline-flex gap-2 align-items-center">
                    <button type="submit" class="btn btn-sm btn-dark"><i class="fal fa-search"></i> Cari</button>
                    <a class="btn btn-sm btn-link text-decoration-none" href="<?= esc(site_url('admin/checklist')) ?>">Ulangi</a>
                </div>
            </div>
        </form>

        <?php if ($baris === []) : ?>
            <div class="ckl-kosong">
                <i class="fal fa-circle-check text-success"></i>
                Belum ada sesi di sini. Buka lembar hari ini di form atas.
            </div>
        <?php else : ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle ckl-daftar">
                    <thead>
                        <tr>
                            <th>Sesi</th>
                            <th>Tanggal</th>
                            <th>Progres</th>
                            <th>Dikerjakan</th>
                            <th>Status</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($baris as $r) : ?>
                            <?php
                            $butir = max(1, (int) $r['jumlah_butir']);
                            $beres = $butir - (int) $r['jumlah_belum'];
                            $persen = (int) round($beres * 100 / $butir);
                            ?>
                            <tr>
                                <td>
                                    <a class="ckl-nama" href="<?= esc(site_url('admin/checklist/' . (int) $r['id_run'])) ?>">
                                        <?= $r['jenis'] === 'email'
                            ? 'Email ' . esc($r['akun_email'] ?: '(akun tidak tercatat)')
                            : esc($r['nama_juragan'] ?: 'Toko #' . (int) $r['toko_id']) ?>
                                    </a>
                                    <div class="ckl-sub">
                                        <span><?= esc($jenis[$r['jenis']] ?? $r['jenis']) ?></span>
                                        <span>#<?= (int) $r['id_run'] ?></span>
                                        <?php if ((int) $r['jumlah_tindakan'] > 0) : ?>
                                            <span class="text-danger"><i class="fal fa-triangle-exclamation"></i>
                                                <?= (int) $r['jumlah_tindakan'] ?> butir perlu tindakan</span>
                                        <?php endif; ?>
                                        <?php if ($r['judul_tugas']) : ?>
                                            <span><i class="fal fa-tasks"></i>
                                                <a class="text-decoration-none" href="<?= esc(site_url('admin/tugas/' . (int) $r['tugas_id'])) ?>">
                                                    <?= esc($r['judul_tugas']) ?>
                                                </a>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="text-nowrap"><?= esc(tanggal_rincian((string) $r['tanggal'])) ?></td>
                                <td>
                                    <div class="ckl-meter">
                                        <div class="batang"><i class="isi" style="width: <?= $persen ?>%"></i></div>
                                        <span><?= $beres ?>/<?= (int) $r['jumlah_butir'] ?></span>
                                    </div>
                                </td>
                                <td class="text-muted">
                                    <?= $r['nama_dikerjakan'] ? esc($r['nama_dikerjakan']) : '<span class="text-muted">belum ada</span>' ?>
                                </td>
                                <td>
                                    <span class="pil-ckl c-<?= esc($r['status']) ?>"><?= $r['status'] === 'selesai' ? 'Selesai' : 'Berjalan' ?></span>
                                </td>
                                <td class="text-end">
                                    <a class="btn btn-sm btn-link text-decoration-none"
                                        href="<?= esc(site_url('admin/checklist/' . (int) $r['id_run'])) ?>">
                                        <?= $r['status'] === 'selesai' ? 'Lihat' : 'Isi' ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <?php if ($total > $batas) : ?>
            <div class="ckl-pager d-flex flex-wrap align-items-center gap-2">
                <span class="small text-muted"><?= (int) $total ?> sesi &middot; halaman <?= (int) $page ?></span>
                <div class="ms-auto">
                    <?= \Config\Services::pager()->makeLinks($page, $batas, $total, 'front_full') ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

</div>

<?= $this->endSection() ?>
