<?php
/**
 * Laporan akumulasi poin gaji (khusus Superadmin).
 *
 * @var array<string, mixed>          $f         Filter aktif: awal, akhir, juragan, user, cari
 * @var list<array<string, mixed>>    $akumulasi Rekap poin per pegawai
 * @var array<string, int>            $ringkas   Total poin, unit, nota, pegawai
 * @var list<array<string, mixed>>    $rincian   Pemberian poin per item (drill-down)
 * @var array<int, list<array>>       $per_pegawai user_id => rincian produk yang dijualnya
 * @var int                           $total     Jumlah baris rincian (untuk pager)
 * @var list<array<string, mixed>>    $pegawai   Pilihan pegawai pada filter
 * @var array<int, string>            $juragans  Pilihan toko pada filter
 * @var int                           $limit     Baris rincian per halaman
 * @var int                           $page      Nomor halaman rincian aktif
 */

$pager = \Config\Services::pager();

$rupiah  = static fn (int $n): string => number_format($n, 0, ',', '.');
$tanggal = static fn ($ts): string => $ts ? date('d/m/Y', (int) $ts) : '-';

// daftar produk di panel detail dibatasi; sisanya diringkas satu baris supaya
// totalnya tetap sama dengan angka di baris pegawai di atasnya
$detail_batas = 12;
?>
<?= $this->extend('template/main') ?>

<?= $this->section('css') ?>
<style>
    /* Tab laporan poin memakai .nav-pills yang sudah distyle global di style.css.
       Di layar paling sempit strip pill dibuat bisa digulir mendatar supaya tidak
       melengkung ke baris kedua dan memakan tempat konten. */
    #poinTabs {
        flex-wrap: nowrap;
        overflow-x: auto;
        overflow-y: hidden;
        padding-bottom: 0.25rem;
        scrollbar-width: thin;
    }
    #poinTabs .nav-link { white-space: nowrap; }
    #poinTabs .nav-link i { margin-right: 0.35rem; }
    @media (min-width: 576px) {
        #poinTabs { flex-wrap: wrap; overflow: visible; }
    }

    /* Baris filter "telanjang" tanpa kartu, meniru .cod-saring di Pencatatan COD:
       kolom dibagi rata dan input tanggal boleh menyusut di bawah lebar isinya,
       jadi tidak ada padding card-body yang berlebihan di sekelilingnya. */
    .poin-saring > div,
    .poin-saring input[type="date"] {
        min-width: 0;
    }
    .poin-saring .form-label { letter-spacing: 0.01em; }

    /* Baris rekap pegawai sekaligus pemicu jendela rincian. Detailnya sudah pindah
       ke modal, jadi tidak ada lagi chevron yang berputar — ikon di depan nama
       hanya menandai baris itu bisa ditekan. */
    .poin-user-baris { cursor: pointer; }
    .poin-user-baris .poin-user-ikon { color: var(--color-ink-400); font-size: 0.75rem; }
    .poin-user-baris:focus-visible {
        outline: 2px solid var(--color-brand-500);
        outline-offset: -2px;
    }

    /* Isi jendela rincian: modal sudah memberi batas dan padding sendiri, jadi
       tabelnya polos tanpa pembungkus berlatar seperti dulu. */
    .poin-user-rinci table { font-size: 0.82rem; }
    .poin-user-rinci thead th {
        color: var(--color-ink-500);
        font-size: 0.72rem;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        white-space: nowrap;
    }
    .poin-user-rumus { color: var(--color-ink-500); font-variant-numeric: tabular-nums; }
    .poin-user-jumlah td { border-top: 1px solid var(--color-ink-200); font-weight: 700; }

    /* Kolom cari: isian memanjang, ikon Terapkan lalu tombol Filter di kanannya.
       Kedua tombol hanya muncul di layar sempit (lihat media query), jadi di
       desktop kolom ini tetap berisi satu isian seperti sebelumnya. */
    .poin-cari-baris {
        display: flex; gap: .3rem; align-items: stretch;
    }
    .poin-cari-baris .form-control { flex: 1 1 auto; min-width: 0; }
    .poin-cari-terapkan,
    .poin-saring-tombol {
        display: none; flex: 0 0 auto; align-items: center; gap: .3rem;
    }
    .poin-saring-angka {
        display: inline-flex; align-items: center; justify-content: center;
        min-width: 1.05rem; height: 1.05rem; padding: 0 .25rem; border-radius: 999px;
        background: #fde047; color: #101828; font-size: .66rem; font-weight: 700;
    }
    .poin-saring .poin-tutup { display: none; }
    .poin-saring.buka .poin-buka { display: none; }
    .poin-saring.buka .poin-tutup { display: inline-flex; }

    /* Dua tombol aksi berbagi lebar kolomnya: Terapkan dan ikon Ulangi sama
       besar, sama tinggi — di desktop maupun di dalam popus. */
    .poin-aksi-baris { display: flex; gap: .3rem; align-items: stretch; }
    .poin-aksi-baris > .btn { flex: 1 1 0; justify-content: center; }

    /* Di desktop kedua pembungkus popus tidak terlihat (display: contents),
       sehingga keempat field-nya tetap duduk satu baris di dalam .row. */
    .poin-saring-lipat,
    .poin-saring-kotak { display: contents; }

    /* Layar sempit: baris filter tinggal kolom cari dengan ikon Terapkan dan
       tombol Filter di kanannya; keempat saringan lain dibuka lewat popus yang
       duduk di TENGAH layar, sama seperti popus halaman lain. */
    @media (max-width: 575.98px) {
        .poin-cari-terapkan,
        .poin-saring-tombol { display: inline-flex; }

        .poin-saring-lipat {
            display: none; position: fixed; inset: 0; z-index: 1050;
            align-items: center; justify-content: center; padding: 1rem;
            background: rgba(15, 23, 42, .35);
            /* .row > * menyisipkan margin-top seukuran gutter; pada lapisan yang
               dipatok inset:0 margin itu menggesernya 8px ke bawah, sehingga
               seiris layar paling atas tidak terredup dan tidak bisa diklik */
            margin-top: 0;
        }
        .poin-saring.buka .poin-saring-lipat { display: flex; }

        .poin-saring-kotak {
            display: flex; flex-wrap: wrap; gap: .55rem .6rem;
            width: 100%; max-width: 24rem; flex: 0 0 auto; padding: .9rem 1rem;
            background: #fff; border: 1px solid #e2e8f0; border-radius: 1rem;
            box-shadow: 0 1.2rem 2.4rem rgba(15, 23, 42, .22);
            max-height: 82vh; overflow-y: auto;
        }
        /* .col-* bawaan Bootstrap membawa gutter 0.75rem di kiri-kanannya; di
           dalam kartu jaraknya diatur lewat gap, jadi padding-nya dinetralkan */
        .poin-saring-kotak > div {
            flex: 1 1 100%; width: auto; padding: 0; min-width: 0;
        }
        /* dua tanggal berpasangan satu baris, toko dan pegawai sebaris penuh */
        .poin-saring-kotak > .poin-setengah { flex: 1 1 calc(50% - .3rem); }
    }
</style>
<?php $this->endSection() ?>

<?= $this->section('content') ?>
<?= $this->include('admin/navbar') ?>

<div class="container-xxl">
    <h1 class="h3 mt-5">Akumulasi Poin Gaji</h1>
    <p class="text-muted small mb-4">
        Rekap poin yang terkumpul dari penjualan produk yang sudah <strong>Lunas</strong>. Poin diberikan
        kepada pembuat nota; angka di sini dipakai sebagai dasar perhitungan gaji.
        Klik baris pegawai untuk membuka rinciannya dalam jendela pop-up.
    </p>

    <div class="row gx-2 gy-3 mb-4">
        <?php
        $ringkasan = [
            ['Total poin', $rupiah((int) ($ringkas['poin'] ?? 0)), 'fa-coins'],
            ['Unit berpoin', $rupiah((int) ($ringkas['unit'] ?? 0)), 'fa-boxes-stacked'],
            ['Nota lunas', (string) (int) ($ringkas['nota'] ?? 0), 'fa-receipt'],
            ['Pegawai tercatat', (string) (int) ($ringkas['pegawai'] ?? 0), 'fa-users'],
        ];

        foreach ($ringkasan as [$label, $nilai, $ikon]) { ?>
            <div class="col-6 col-md-3">
                <div class="h-100 rounded-3 border border-ink-200 bg-white px-3 py-2">
                    <div class="text-muted small text-uppercase text-truncate">
                        <i class="fal <?= $ikon ?>"></i> <?= esc($label) ?>
                    </div>
                    <div class="fs-5 fw-bold"><?= esc($nilai) ?></div>
                </div>
            </div>
        <?php } ?>
    </div>

    <?php
    // berapa saringan yang sedang aktif di balik tombol Filter (layar kecil).
    // Rentang tanggal baru dihitung kalau memang menyimpang dari bawaannya
    // (awal bulan s/d hari ini), sebab kedua field itu tidak pernah kosong.
    $saringAktif = ($f['awal'] !== date('Y-m-01') || $f['akhir'] !== date('Y-m-d')) ? 1 : 0;
    $saringAktif += (int) $f['juragan'] > 0 ? 1 : 0;
    $saringAktif += (int) $f['user'] > 0 ? 1 : 0;
    ?>
    <?= form_open('admin/poin', ['method' => 'get', 'class' => 'row g-2 align-items-end mb-4 poin-saring']) ?>
    <div class="col-12 col-md-4 col-xl">
        <?= form_label('Cari nama', 'cari', ['class' => 'form-label small mb-1']) ?>
        <div class="poin-cari-baris">
            <?= form_input([
                'type'        => 'search',
                'class'       => 'form-control form-control-sm',
                'name'        => 'cari',
                'id'          => 'cari',
                'value'       => $f['cari'],
                'placeholder' => 'nama pegawai',
            ]) ?>
            <?php /* sama seperti kolom cari halaman lain: kaca pembesar, bukan corong
               — corong hanya milik tombol Filter supaya keduanya tidak kembar */ ?>
            <button class="btn btn-sm btn-dark poin-cari-terapkan" type="submit" title="Terapkan" aria-label="Terapkan">
                <i class="fal fa-search"></i>
            </button>
            <button class="btn btn-sm btn-dark poin-saring-tombol" type="button" id="poin-saring-buka"
                aria-expanded="false" aria-controls="poin-saring-lipat">
                <span class="poin-buka"><i class="fal fa-filter"></i> Filter</span>
                <span class="poin-tutup"><i class="fal fa-xmark"></i> Tutup</span>
                <?php if ($saringAktif > 0) { ?><span class="poin-saring-angka"><?= $saringAktif ?></span><?php } ?>
            </button>
        </div>
    </div>

    <?php /* Di layar sempit keempat saringan ini dilipat ke dalam popus; kedua
       pembungkusnya display: contents di desktop jadi field-nya tetap satu baris */ ?>
    <div class="poin-saring-lipat" id="poin-saring-lipat">
        <div class="poin-saring-kotak">
            <div class="col-6 col-md-4 col-xl poin-setengah">
                <?= form_label('Dari tanggal', 'awal', ['class' => 'form-label small mb-1']) ?>
                <?= form_input(['type' => 'date', 'class' => 'form-control form-control-sm', 'name' => 'awal', 'value' => $f['awal']]) ?>
            </div>
            <div class="col-6 col-md-4 col-xl poin-setengah">
                <?= form_label('Sampai tanggal', 'akhir', ['class' => 'form-label small mb-1']) ?>
                <?= form_input(['type' => 'date', 'class' => 'form-control form-control-sm', 'name' => 'akhir', 'value' => $f['akhir']]) ?>
            </div>
            <div class="col-6 col-md-4 col-xl">
                <?= form_label('Toko', 'juragan', ['class' => 'form-label small mb-1']) ?>
                <?= form_dropdown([
                    'class'    => 'form-select form-select-sm',
                    'name'     => 'juragan',
                    'options'  => [0 => 'Semua toko'] + $juragans,
                    'selected' => $f['juragan'],
                ]) ?>
            </div>
            <div class="col-6 col-md-4 col-xl">
                <?= form_label('Pegawai', 'user', ['class' => 'form-label small mb-1']) ?>
                <?= form_dropdown([
                    'class'    => 'form-select form-select-sm',
                    'name'     => 'user',
                    'options'  => [0 => 'Semua pegawai'] + array_column($pegawai, 'nama', 'user_id'),
                    'selected' => $f['user'],
                ]) ?>
            </div>
            <div class="col-6 col-md-4 col-xl">
                <div class="poin-aksi-baris">
                    <button class="btn btn-sm btn-dark" type="submit"><i class="fal fa-filter me-1"></i>Terapkan</button>
                    <button class="btn btn-sm btn-dark d-inline-flex" type="button" data-saring-reset=""
                        title="Kosongkan saringan" aria-label="Kosongkan saringan">
                        <i class="fal fa-sync"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
    <?= form_close() ?>

    <div data-saring-wadah>
    <div class="card mb-4">
        <div class="card-header bg-white border-bottom py-2 px-2 px-sm-3">
            <ul class="nav nav-pills" id="poinTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="btn-tab-rekap" data-bs-toggle="pill" data-bs-target="#tab-rekap" type="button" role="tab" aria-controls="tab-rekap" aria-selected="true">
                        <i class="fal fa-trophy"></i>Rekap per Pegawai
                        <span class="badge bg-light text-dark border ms-1"><?= count($akumulasi) ?></span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="btn-tab-rincian" data-bs-toggle="pill" data-bs-target="#tab-rincian" type="button" role="tab" aria-controls="tab-rincian" aria-selected="false">
                        <i class="fal fa-list"></i>Rincian Pemberian Poin
                        <span class="badge bg-light text-dark border ms-1"><?= (int) $total ?></span>
                    </button>
                </li>
            </ul>
        </div>
        <div class="card-body p-2 p-sm-3">
            <div class="tab-content">
                <!-- Tab 1: Rekap per pegawai -->
                <div class="tab-pane fade show active" id="tab-rekap" role="tabpanel" aria-labelledby="btn-tab-rekap">
                    <?php if ($akumulasi === []) { ?>
                        <div class="text-center text-muted py-4">
                            <i class="fal fa-coins fa-2x mb-2 d-block"></i>
                            Belum ada poin pada periode/filter ini.
                        </div>
                    <?php } else { ?>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover align-middle mb-0">
                                <thead>
                                    <tr class="small text-muted">
                                        <th>Pegawai</th>
                                        <th class="text-end">Nota</th>
                                        <th class="text-end">Unit</th>
                                        <th class="text-end">Poin</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($akumulasi as $a) {
                                        $id_pedia = (int) $a['user_id'];
                                        $detail   = $per_pegawai[$id_pedia] ?? [];
                                        $tampil   = array_slice($detail, 0, $detail_batas);
                                        $sisa     = array_slice($detail, $detail_batas);
                                        $sisa_poin = array_sum(array_map(static fn (array $d): int => (int) $d['poin'], $sisa));
                                        $poin     = (int) $a['poin'];
                                        ?>
                                        <tr class="poin-user-baris" data-bs-toggle="modal" data-bs-target="#poinUserModal"
                                            data-poin-user="<?= $id_pedia ?>" data-nama="<?= esc($a['nama'], 'attr') ?>"
                                            role="button" tabindex="0" aria-haspopup="dialog">
                                            <td>
                                                <i class="fal fa-list-ul poin-user-ikon me-2"></i><?= esc($a['nama']) ?>
                                            </td>
                                            <td class="text-end"><?= (int) $a['nota'] ?></td>
                                            <td class="text-end"><?= $rupiah((int) $a['unit']) ?></td>
                                            <td class="text-end fw-bold"><?= $rupiah($poin) ?></td>
                                        </tr>
                                        <?php /* rincian pegawai disimpan di sini, bukan di baris tabel:
                                           jendela pop-up mengambil isinya saat baris ini ditekan */ ?>
                                        <template id="poin-user-<?= $id_pedia ?>">
                                            <?php if ($detail === []) { ?>
                                                <div class="text-muted small">Belum ada rincian produk untuk pegawai ini.</div>
                                            <?php } else { ?>
                                                <div class="small text-muted mb-2">
                                                    <i class="fal fa-boxes-stacked me-1"></i>
                                                    <?= count($detail) ?> produk &middot; <?= $rupiah((int) $a['unit']) ?> unit &middot; <?= (int) $a['nota'] ?> nota lunas
                                                </div>

                                                <div class="table-responsive">
                                                    <table class="table table-sm align-middle mb-0">
                                                        <thead>
                                                            <tr>
                                                                <th>Produk</th>
                                                                <th class="text-end">Nota</th>
                                                                <th class="text-end">Unit</th>
                                                                <th class="text-end">Poin/satuan</th>
                                                                <th class="text-end">Perhitungan</th>
                                                                <th class="text-end">Poin</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php foreach ($tampil as $d) {
                                                                $ukuran = trim((string) ($d['ukuran'] ?? ''));
                                                                $unit   = (int) $d['unit'];
                                                                $satuan = (int) $d['poin_satuan'];
                                                                ?>
                                                                <tr>
                                                                    <td>
                                                                        <span class="fw-semibold"><?= esc($d['kode']) ?></span>
                                                                        <?php if ($ukuran !== '') { ?><span class="text-muted">/ <?= esc($ukuran) ?></span><?php } ?>
                                                                    </td>
                                                                    <td class="text-end"><?= (int) $d['nota'] ?></td>
                                                                    <td class="text-end"><?= $rupiah($unit) ?></td>
                                                                    <td class="text-end"><?= $rupiah($satuan) ?></td>
                                                                    <td class="text-end poin-user-rumus"><?= $rupiah($unit) . ' × ' . $rupiah($satuan) ?></td>
                                                                    <td class="text-end"><?= $rupiah((int) $d['poin']) ?></td>
                                                                </tr>
                                                            <?php } ?>

                                                            <?php if ($sisa !== []) { ?>
                                                                <tr>
                                                                    <td class="text-muted" colspan="5">
                                                                        &hellip; dan <?= count($sisa) ?> produk/nilai lainnya
                                                                    </td>
                                                                    <td class="text-end text-muted"><?= $rupiah($sisa_poin) ?></td>
                                                                </tr>
                                                            <?php } ?>

                                                            <tr class="poin-user-jumlah">
                                                                <td colspan="5">Total poin pegawai ini pada periode/filter ini</td>
                                                                <td class="text-end"><?= $rupiah($poin) ?></td>
                                                            </tr>
                                                        </tbody>
                                                    </table>
                                                </div>

                                                <div class="small text-muted mt-2">
                                                    <i class="fal fa-circle-info me-1"></i>
                                                    Nilai per satuan dipotret saat nota dilunasi, jadi produk yang sama bisa muncul dua kali
                                                    kalau poin produknya pernah diubah. Barang dari stok terhitung sejak notasinya lunas;
                                                    barang tulisan tangan baru terhitung setelah notasinya masuk Selesai.
                                                </div>
                                            <?php } ?>
                                        </template>
                                    <?php } ?>
                                </tbody>
                            </table>
                        </div>
                    <?php } ?>
                </div>

                <!-- Tab 2: Rincian pemberian poin -->
                <div class="tab-pane fade" id="tab-rincian" role="tabpanel" aria-labelledby="btn-tab-rincian">
                    <?php if ($rincian === []) { ?>
                        <div class="text-center text-muted py-4">
                            <i class="fal fa-inbox fa-2x mb-2 d-block"></i>
                            Tidak ada transaksi berpoin yang cocok.
                        </div>
                    <?php } else { ?>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead>
                                    <tr class="small text-muted">
                                        <th>Tanggal</th>
                                        <th class="d-none d-md-table-cell">Nota</th>
                                        <th>Produk</th>
                                        <th class="d-none d-lg-table-cell">Pegawai</th>
                                        <th class="text-end">Qty</th>
                                        <th class="text-end">Poin</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($rincian as $r) {
                                        $ukuran = (string) ($r['ukuran'] ?? '');
                                        ?>
                                        <tr>
                                            <td class="text-nowrap small"><?= $tanggal($r['created_at']) ?></td>
                                            <td class="small d-none d-md-table-cell"><?= esc($r['seri'] ?? ('#' . (int) $r['invoice_id'])) ?></td>
                                            <td>
                                                <span class="fw-semibold"><?= esc($r['kode']) ?></span>
                                                <?php if ($ukuran !== '') { ?><span class="text-muted small">/ <?= esc($ukuran) ?></span><?php } ?>
                                                <div class="text-muted small">
                                                    <span class="d-lg-none"><i class="fal fa-user me-1"></i><?= esc($r['nama']) ?></span>
                                                    <?= esc($r['nama_juragan'] ?? '') ?>
                                                </div>
                                            </td>
                                            <td class="small d-none d-lg-table-cell"><?= esc($r['nama']) ?></td>
                                            <td class="text-end"><?= (int) $r['qty'] ?></td>
                                            <td class="text-end fw-bold"><?= $rupiah((int) $r['poin_total']) ?></td>
                                        </tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                        </div>

                        <div class="mt-3 d-flex justify-content-center justify-content-md-between flex-wrap gap-2">
                            <span class="small text-muted">Halaman <?= (int) $page ?> &middot; <?= (int) $total ?> pemberian poin</span>
                            <?= $pager->makeLinks($page, $limit, $total, 'front_full') ?>
                        </div>
                    <?php } ?>
                </div>
            </div>
        </div>
    </div>
    </div>
</div>

<?php /* Satu jendela rincian untuk semua baris pegawai — isinya diisi dari
   <template> milik baris yang diklik, sama seperti modal di Reminder Bintang 5.
   Duduknya di luar wadah hasil supaya isi tab boleh ditukar kapan saja tanpa
   ikut merobohkan modal yang sedang terbuka. */ ?>
<div class="modal fade" id="poinUserModal" tabindex="-1" aria-labelledby="poinUserModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="poinUserModalLabel">Rincian poin pegawai</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body poin-user-rinci"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-sm btn-link text-decoration-none" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('js') ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    // popus saringan layar sempit: dibuka dari tombol Filter di samping kolom
    // cari, ditutup lagi begitu yang diklik jatuh di luar kartunya (termasuk
    // latar redupnya) atau Esc ditekan. Desktop tidak pernah melihat tombol ini
    // karena display-nya diatur di media query, jadi tidak perlu cek lebar layar.
    var saring = document.querySelector('.poin-saring');
    var buka   = document.getElementById('poin-saring-buka');
    var kotak  = document.querySelector('.poin-saring-kotak');

    if (saring && buka && kotak) {
        var tutup = function () {
            saring.classList.remove('buka');
            buka.setAttribute('aria-expanded', 'false');
        };

        buka.addEventListener('click', function () {
            var kini = ! saring.classList.contains('buka');

            saring.classList.toggle('buka', kini);
            buka.setAttribute('aria-expanded', kini ? 'true' : 'false');
        });

        document.addEventListener('click', function (peristiwa) {
            if (! saring.classList.contains('buka') || kotak.contains(peristiwa.target) || buka.contains(peristiwa.target)) {
                return;
            }

            tutup();
        });

        document.addEventListener('keydown', function (peristiwa) {
            if (peristiwa.key === 'Escape') {
                tutup();
            }
        });
    }

    // Kepala tab duduk di dalam wadah hasil, jadi tab yang sedang dibuka diingat
    // lewat delegasi di document: setelah saringan direset (isi ditukar tanpa muat
    // ulang) pengguna tidak terlempar lagi ke tab rekap.
    var tabAktif = <?= $page > 1 ? "'btn-tab-rincian'" : "'btn-tab-rekap'" ?>;

    document.addEventListener('click', function (peristiwa) {
        var pemicu = peristiwa.target.closest ? peristiwa.target.closest('#poinTabs .nav-link') : null;

        if (pemicu) {
            tabAktif = pemicu.id;
        }
    });

    document.addEventListener('saring:segar', function () {
        var pemicu = document.getElementById(tabAktif);

        if (pemicu && ! pemicu.classList.contains('active') && window.bootstrap) {
            new bootstrap.Tab(pemicu).show();
        }
    });

    // Rincian satu-satunya tab yang punya paginasi: saat halaman berubah
    // (page > 1), buka tab rincian lagi supaya pengguna tidak terlempar ke rekap.
    <?php if ($page > 1) { ?>
    var btnRincian = document.getElementById('btn-tab-rincian');
    if (btnRincian && window.bootstrap) {
        new bootstrap.Tab(btnRincian).show();
    }
    <?php } ?>
    // Satu jendela rincian dipakai bersama semua baris pegawai: judulnya dan
    // tabel isinya diambil dari <template> milik baris yang diklik, jadi tidak
    // ada satu modal per pegawai. Dipasang di event modal (bukan per baris)
    // karena isi tab ditukar lewat AJAX saat saringan dipakai.
    var jendela = document.getElementById('poinUserModal');

    if (jendela) {
        jendela.addEventListener('show.bs.modal', function (peristiwa) {
            var pemicu = peristiwa.relatedTarget;

            if (! pemicu || ! pemicu.dataset) {
                return;
            }

            var sumber = document.getElementById('poin-user-' + (pemicu.dataset.poinUser || '0'));
            var wadah  = jendela.querySelector('.modal-body');

            wadah.textContent = '';

            if (sumber) {
                wadah.appendChild(sumber.content.cloneNode(true));
            } else {
                wadah.textContent = 'Rincian tidak tersedia.';
            }

            jendela.querySelector('.modal-title').textContent = 'Rincian poin' + (pemicu.dataset.nama ? ' — ' + pemicu.dataset.nama : '');
        });
    }

    // Baris rekap adalah <tr> yang bisa diklik; data API modal Bootstrap 5.2 hanya
    // mendengarkan klik, jadi Enter/Space ditangani sendiri supaya baris dengan
    // role="button" ini tidak sekadar terlihat bisa ditekan.
    document.addEventListener('keydown', function (e) {
        var baris = e.target && e.target.closest ? e.target.closest('#tab-rekap tr.poin-user-baris') : null;

        if (! baris || (e.key !== 'Enter' && e.key !== ' ' && e.key !== 'Spacebar')) {
            return;
        }

        e.preventDefault();
        baris.click();
    });
});
</script>
<?php $this->endSection() ?>
