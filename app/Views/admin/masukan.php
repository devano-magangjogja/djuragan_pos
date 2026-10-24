<?php
/**
 * Kotak masukan Superadmin.
 *
 * Variabel dari Admin\Masukan::index() (di-extract CI4 saat runtime, jadi
 * dideklarasikan di sini agar analyzer tidak menyangkanya tak terdefinisi).
 *
 * @var string       $title   judul halaman
 * @var list<object> $daftar  masukan pada halaman aktif: [id, nama, isi, created_at]
 * @var string       $cari    kata kunci pencarian (boleh kosong)
 * @var string       $dari    batas bawah rentang tanggal, Y-m-d (boleh kosong)
 * @var string       $sampai  batas atas rentang tanggal, Y-m-d (boleh kosong)
 * @var int          $jumlah  banyaknya masukan sesuai saringan aktif
 * @var int          $semua   banyaknya seluruh masukan, tanpa saringan
 * @var int          $total   banyaknya halaman
 * @var int          $halaman nomor halaman aktif (1-based)
 * @var ?string      $sukses
 * @var ?string      $gagal
 */

$zona     = config('App')->appTimezone;
$sekarang = new \CodeIgniter\I18n\Time('now', $zona);
$hariIni  = $sekarang->toDateString();

// satu baris waktu: label singkat untuk di bawah, jam penuh untuk tooltip
$waktu = static function (?string $iso) use ($zona, $sekarang): array {
    if ($iso === null || $iso === '') {
        return ['lama' => 'tanpa waktu', 'penuh' => '—'];
    }

    // created_at disimpan sebagai DATETIME dalam zona aplikasi, jadi dibaca pada
    // zona itu juga; kalau dianggap UTC dulu, label "X menit lalu" meleset
    $ketika = \CodeIgniter\I18n\Time::parse($iso, $zona);
    $detik  = max(0, $sekarang->getTimestamp() - $ketika->getTimestamp());

    $lama = match (true) {
        $detik < 60     => 'baru saja',
        $detik < 3600   => intdiv($detik, 60) . ' menit lalu',
        $detik < 86400  => intdiv($detik, 3600) . ' jam lalu',
        default         => intdiv($detik, 86400) . ' hari lalu',
    };

    return ['lama' => $lama, 'penuh' => $ketika->toLocalizedString('d MMM yyyy HH:mm')];
};

// alamat daftar dengan saringan yang sedang aktif tetap terbawa
$t = static function (array $ubah = []) use ($cari, $dari, $sampai): string {
    $kueri = http_build_query(array_filter(array_merge([
        'cari'   => $cari,
        'dari'   => $dari,
        'sampai' => $sampai,
    ], $ubah), static fn ($v) => $v !== null && $v !== '' && $v !== 0));

    return site_url('admin/masukan') . ($kueri === '' ? '' : '?' . $kueri);
};

$tersaring = ($cari !== '' || $dari !== '' || $sampai !== '');
?>
<?= $this->extend('template/main') ?>

<?= $this->section('css') ?>
<style>
/* Daftar masukan: satu kartu putih per laporan, pengirim dan waktunya di baris
   atas, isinya di bawah. Teks panjang dipotong di tengah kata supaya kartu tidak
   melebar ke samping. */
.masukan-kartu {
    border: 1px solid #eaecf0;
    border-radius: 14px;
    background: #ffffff;
    padding: 0.9rem 1rem;
    transition: border-color 0.15s ease, box-shadow 0.15s ease;
}
.masukan-kartu:hover {
    border-color: #d0d5dd;
    box-shadow: 0 1px 3px rgba(16, 24, 40, 0.06);
}
.masukan-orang {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.82rem;
    font-weight: 700;
    color: #101828;
    overflow-wrap: anywhere;
}
.masukan-waktu {
    font-size: 0.76rem;
    color: #667085;
}
.masukan-kode {
    font-size: 0.7rem;
    color: #98a2b3;
}
.masukan-isi {
    margin: 0.55rem 0 0;
    font-size: 0.9rem;
    line-height: 1.6;
    color: #344054;
    white-space: pre-wrap;
    overflow-wrap: anywhere;
}
/* Kotak cari memanjang mengisi barisnya; rentang tanggal dikumpulkan dalam satu
   grup supaya bisa duduk di ujung kanan, bukan tersebar di antara dua isian. */
.masukan-hitung {
    font-size: 0.72rem;
    vertical-align: 0.3em;
    font-weight: 600;
}
.masukan-cari {
    flex: 1 1 260px;
    min-width: 0;
}
.masukan-rentang {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.35rem;
    flex: 0 1 auto;
}
.masukan-tanggal {
    width: 148px;
    flex: 1 1 148px;
    min-width: 0;
}
@media (max-width: 767.98px) {
    /* di layar sempit kotak cari mengambil barisnya sendiri, lalu KEDUA tanggal
       berbagi satu baris berikutnya (nowrap + menyusut) dan merapat ke tepi kanan */
    .masukan-cari {
        flex: 1 1 100%;
    }
    .masukan-rentang {
        margin-left: auto;
        flex-wrap: nowrap;
    }
    .masukan-tanggal {
        width: auto;
        flex: 1 1 0;
    }
}
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?= $this->include('admin/navbar') ?>

<div class="container-xxl">
<!-- judul + catatan memakai pola baku halaman admin (h1.h3.mt-5), supaya
     ukuran dan jaraknya sama dengan Produk, Laporan, dan Pencatatan COD;
     penormalnya di style.css:1514 hanya mengenali kelas itu -->
<h1 class="h3 mt-5"><i class="fal fa-headset text-primary me-1"></i>Kotak Masukan
    <span class="masukan-hitung badge rounded-pill bg-light text-dark border"><?= esc((string) $semua) ?> total</span>
</h1>

<p class="text-muted small mb-3">Kendala dan permintaan fitur dari CS dan Admin.</p>

    <?php if ($sukses !== null) { ?>
        <div class="alert alert-success py-2" data-flash><?= esc($sukses) ?></div>
    <?php } ?>
    <?php if ($gagal !== null) { ?>
        <div class="alert alert-danger py-2" data-flash><?= esc($gagal) ?></div>
    <?php } ?>

    <div class="card rounded-kartu shadow-kartu mb-3">
        <div class="card-body py-2 px-3">
            <form action="<?= site_url('admin/masukan') ?>" method="get" class="d-flex flex-wrap align-items-center gap-2">
                <div class="cari-merged masukan-cari">
                    <button type="submit" class="cari-kirim" title="Cari"><i class="fal fa-search"></i></button>
                    <input type="text" name="cari" class="form-control form-control-sm" placeholder="Cari isi atau nama..." value="<?= esc($cari) ?>">
                </div>

                <div class="masukan-rentang">
                    <label class="visually-hidden" for="dari">Dari tanggal</label>
                    <input type="date" class="form-control form-control-sm masukan-tanggal" id="dari" name="dari" value="<?= esc($dari) ?>" max="<?= esc($hariIni) ?>">
                    <span class="small text-muted">s/d</span>
                    <label class="visually-hidden" for="sampai">Sampai tanggal</label>
                    <input type="date" class="form-control form-control-sm masukan-tanggal" id="sampai" name="sampai" value="<?= esc($sampai) ?>" max="<?= esc($hariIni) ?>">
                </div>

                <?php if ($tersaring) { ?>
                    <a class="btn btn-sm btn-link text-decoration-none" href="<?= site_url('admin/masukan') ?>">
                        <i class="fal fa-times"></i> Bersihkan
                    </a>
                <?php } ?>

                <span class="small text-muted ms-auto"><?= esc((string) $jumlah) ?> <?= $tersaring ? 'cocok' : 'masukan' ?></span>
            </form>
        </div>
    </div>

    <?php if ($daftar === []) { ?>
        <div class="text-center py-5 text-muted">
            <i class="fal fa-inbox fa-3x mb-3 opacity-50"></i>
            <h5><?= $tersaring ? 'Tidak ada masukan yang cocok' : 'Belum ada masukan' ?></h5>
            <p class="mb-0 small">
                <?= $tersaring
                    ? 'Coba kata lain, rentang tanggalnya lebarkan, atau bersihkan saringannya.'
                    : 'Semua masukan dari CS dan Admin akan berkumpul di sini.' ?>
            </p>
        </div>
    <?php } else { ?>
        <div class="d-flex flex-column gap-2 mb-4">
            <?php foreach ($daftar as $m) { $w = $waktu($m->created_at ?? null); ?>
                <article class="masukan-kartu">
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <span class="masukan-orang"><i class="fal fa-user-circle text-primary"></i><?= esc($m->nama) ?></span>
                        <span class="masukan-waktu" title="<?= esc($w['penuh']) ?>"><?= esc($w['lama']) ?></span>
                        <span class="masukan-kode font-monospace ms-auto" title="Nomor pengenal masukan">#<?= esc($m->id) ?></span>
                    </div>
                    <p class="masukan-isi"><?= esc($m->isi) ?></p>
                </article>
            <?php } ?>
        </div>

        <?php if ($total > 1) { ?>
            <nav aria-label="Halaman masukan" class="mb-5">
                <ul class="pagination pagination-sm flex-wrap">
                    <li class="page-item<?= $halaman <= 1 ? ' disabled' : '' ?>">
                        <a class="page-link" href="<?= $t(['halaman' => $halaman - 1]) ?>">&laquo; Sebelumnya</a>
                    </li>
                    <?php for ($i = 1; $i <= $total; $i++) { ?>
                        <?php if ($i === 1 || $i === $total || abs($i - $halaman) <= 2) { ?>
                            <li class="page-item<?= $i === $halaman ? ' active' : '' ?>">
                                <a class="page-link" href="<?= $t(['halaman' => $i]) ?>"><?= $i ?></a>
                            </li>
                        <?php } elseif (abs($i - $halaman) === 3) { ?>
                            <li class="page-item disabled"><span class="page-link">&hellip;</span></li>
                        <?php } ?>
                    <?php } ?>
                    <li class="page-item<?= $halaman >= $total ? ' disabled' : '' ?>">
                        <a class="page-link" href="<?= $t(['halaman' => $halaman + 1]) ?>">Berikutnya &raquo;</a>
                    </li>
                </ul>
            </nav>
        <?php } ?>
    <?php } ?>
</div>
<?= $this->endSection() ?>
