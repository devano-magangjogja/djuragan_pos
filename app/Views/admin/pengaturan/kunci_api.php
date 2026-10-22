<?php

/**
 * Variabel dari Admin\Settings\KunciApi::index() (di-extract oleh CI4 saat runtime).
 *
 * @var list<object> $kunci   Daftar kunci; ->kunci berisi hash, ->token_asli token
 *                            mentah dari salinan ter-enkrip (null untuk kunci lama)
 * @var int $tak_terpakai     Jumlah kunci yang belum pernah dipakai satu kali pun
 * @var list<object> $toko    Master juragan untuk pil cakupan
 * @var array<string, string> $hak  Peta hak => label (KunciApiModel::HAK)
 * @var ?string $token        Kunci mentah baru, hanya ada satu request setelah dibuat
 * @var ?string $sukses
 * @var ?string $gagal
 * @var array<string, string> $errors
 */

$pil_hak = static function (string $awalan, array $punya, array $hak): string {
    $html = '';

    foreach ($hak as $slug => $label) {
        $id     = 'pil-hak-' . $awalan . '-' . $slug;
        $cekat  = in_array($slug, $punya, true) ? ' checked' : '';

        $html .= '<input class="btn-check" type="checkbox" name="hak[]" value="' . $slug . '" id="' . $id . '"'
            . ' autocomplete="off"' . $cekat . '>'
            . '<label class="btn btn-sm rounded-pill btn-outline-secondary" for="' . $id . '">' . esc($label) . '</label>';
    }

    return $html;
};

$pil_toko = static function (string $awalan, array $punya, array $toko): string {
    $html = '';

    foreach ($toko as $t) {
        $id    = 'pil-toko-' . $awalan . '-' . (int) $t->id_juragan;
        $cekat = in_array((int) $t->id_juragan, $punya, true) ? ' checked' : '';

        $html .= '<input class="btn-check" type="checkbox" name="juragan[]" value="' . (int) $t->id_juragan . '"'
            . ' id="' . $id . '" autocomplete="off"' . $cekat . '>'
            . '<label class="btn btn-sm rounded-pill btn-outline-secondary" for="' . $id . '">'
            . esc($t->nama_juragan) . '</label>';
    }

    return $html;
};
?>
<?= $this->extend('template/main') ?>

<?= $this->section('css') ?>
<style>
/* Kunci API di kartu: nilainya memang ada di halaman (untuk tombol "lihat"),
   tapi dikaburkan dulu supaya tidak terbaca dari jauh atau tercopy tanpa sengaja.
   Klik untuk membuka, klik lagi untuk menyembunyikan; yang perlu disalin punya
   tombolnya sendiri, jadi kabur ini hanya menjaga bacaan sekilas. */
.kunci-rahasia {
    filter: blur(5px);
    user-select: none;
    cursor: pointer;
    background: #f9fafb;
    border: 1px solid #eaecf0;
    border-radius: 8px;
    padding: 0.15rem 0.6rem;
    transition: filter 0.15s ease;
}
.kunci-rahasia.terlihat {
    filter: none;
    user-select: text;
}

/* Judul "Kunci API" dibuat sama persis dengan header "Pencatatan COD".
   style.css menormalkan h1.h3.mt-5 ke 1.5rem, tapi judul di halaman ini duduk
   di dalam baris flex (mt-5 ada di wadah, bukan di <h1>) sehingga tidak kena
   aturan itu dan ikut ukuran h3 responsif Bootstrap yang lebih besar. Nilai
   tipografinya ditiru di sini; jarak atas cukup dari padding container. */
.judul-halaman {
    font-size: 1.5rem !important;
    font-weight: 700 !important;
    letter-spacing: -0.025em !important;
    line-height: 1.25 !important;
    color: #101828 !important;
}
@media (max-width: 575.98px) {
    .judul-halaman {
        font-size: 1.25rem !important;
    }
}
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?= $this->include('admin/navbar') ?>

<div class="container-xxl">
    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
        <h1 class="h3 mb-0 judul-halaman">Kunci API</h1>
        <button type="button" class="btn rounded-pill border-0 bg-brand-500 font-semibold text-white" data-bs-toggle="modal" data-bs-target="#modalBuatKunci" title="Buat kunci API baru">
            <i class="fal fa-plus"></i> Buat Kunci API
        </button>
    </div>

    <?php if ($sukses !== null) { ?>
        <div class="alert alert-success py-2" data-flash><?= esc($sukses) ?></div>
    <?php } ?>
    <?php if ($gagal !== null) { ?>
        <div class="alert alert-danger py-2" data-flash><?= esc($gagal) ?></div>
    <?php } ?>
    <?php if ($errors !== []) { ?>
        <div class="alert alert-danger py-2">
            <?= esc(implode(' ', $errors)) ?>
        </div>
    <?php } ?>

    <?php if ($token !== null && $token !== '') { ?>
        <div class="alert d-flex flex-wrap align-items-center gap-2 bg-amber-100 border border-amber-100 text-amber-800 py-2">
            <i class="fal fa-key"></i>
            <span class="fw-semibold">Kunci baru (hanya muncul sekali, jangan sampai tidak tersimpan):</span>
            <code id="kunci-baru" class="flex-fill text-break"><?= esc($token) ?></code>
            <button type="button" class="btn btn-sm btn-light btn-salin" data-target="kunci-baru">
                <i class="fal fa-copy"></i> Salin
            </button>
        </div>
        <div class="alert alert-info py-2">
            Cara pakai untuk pemanggilnya: tempel di header, bukan di URL.
            <code>curl -H "X-API-Key: <?= esc($token) ?>" "<?= esc(site_url('api/publik/katalog')) ?>"</code>
        </div>
    <?php } ?>

    <div class="card mb-4">
        <div class="card-body">
            <p class="mb-0 text-muted small">
                Empat hal yang bisa dibuka: katalog barang, stok satu varian, ringkasan penjualan, dan status orderan.
                Alamat lengkap, nomor HP, keterangan bebas, ukuran badan, dan riwayat pembayaran per transferan
                <strong>tidak pernah</strong> ikut terkirim — status orderan hanya dibuka kalau nomor nota
                <em>dan</em> kode lacaknya cocok.
            </p>
            <p class="mb-0 text-muted small mt-2">
                <strong>Cabut</strong> mematikan kunci tapi barisnya tetap terlihat di daftar dengan lencana
                "dicabut"; <strong>hapus</strong> mengeluarkan barisnya dari daftar untuk kunci yang sudah
                tidak terpakai. Keduanya tidak pernah membuang jejak aslinya dari tabel.
                Tombol <strong>Salin</strong> menyalin kunci tanpa perlu membuka kabur nilainya.
            </p>
        </div>
    </div>

    <?php if ($tak_terpakai > 0) { ?>
        <?php
        // tombolnya hanya muncul kalau memang ada yang bisa dibersihkan; hitungnya
        // dari daftar yang sama dengan yang tampil di bawah
        $peringatan_sapu = 'Hapus ' . $tak_terpakai . ' kunci yang belum pernah dipakai? Daftar jadi bersih dan arsipnya'
            . ' tetap tertinggal di tabel; kalau ternyata ada situs diam-diam memakainya, ia langsung kena 401.';
        ?>
        <div class="card mb-4">
            <div class="card-body d-flex flex-wrap align-items-center gap-2 py-2">
                <i class="fal fa-broom text-muted" aria-hidden="true"></i>
                <span class="small">
                    Ada <strong><?= (int) $tak_terpakai ?></strong> kunci yang belum pernah dipakai satu kali pun.
                    Tidak dipakai tidak berarti berbahaya, tapi daftar ini lebih enak dibaca tanpa mereka.
                </span>
                <?= form_open('admin/settings/kunci-api/bersihkan', ['class' => 'ms-auto d-inline']) ?>
                <button type="submit" class="btn btn-sm btn-outline-danger"
                    onclick="return confirm('<?= esc($peringatan_sapu, 'js') ?>')">
                    <i class="fal fa-trash"></i> Hapus yang tak terpakai
                </button>
                <?= form_close() ?>
            </div>
        </div>
    <?php } ?>

    <?php if ($kunci === []) { ?>
        <div class="text-center py-5 text-muted">
            <i class="fal fa-key fa-3x mb-2 d-block"></i>
            Belum ada kunci API. Klik <strong>Buat Kunci API</strong> untuk memberikan akses baca ke situs orang lain.
        </div>
    <?php } ?>

    <div class="row gx-3 gy-3">
        <?php foreach ($kunci as $k) :
            $punya_hak  = \App\Models\KunciApiModel::hak($k);
            $punya_toko = \App\Models\KunciApiModel::toko($k);
            $nama_toko  = array_map(static fn (int $i): string => (string) ((new \App\Models\JuraganModel())->find($i)->nama_juragan ?? $i), $punya_toko);

            // menghapus kunci yang terbukti masih aktif dipakai = memutus situs
            // orang, jadi konfirmasinya harus menyebut keadaan itu, bukan cuma
            // "yakin?" kosong
            $peringatan = $k->terakhir_pakai === null
                ? 'Hapus kunci ' . $k->nama . '? Kunci ini belum pernah dipakai, jadi tidak ada situs yang terputus.'
                : 'Hapus kunci ' . $k->nama . '? Terakhir dipakai ' . date('d/m/Y H:i', (int) $k->terakhir_pakai)
                    . ((int) $k->hidup === 1 ? ' dan masih aktif — pemanggilnya langsung kena 401.' : ' — sudah dicabut, jadi tidak ada yang memakai.');
            ?>
            <div class="col-12">
                <div class="card rounded-kartu shadow-kartu">
                    <div class="card-body p-3">
                        <div class="d-flex flex-wrap align-items-center gap-2">
                            <span class="fw-semibold"><?= esc($k->nama) ?></span>
                            <?php if ((int) $k->hidup === 1) { ?>
                                <span class="badge rounded-pill bg-success">aktif</span>
                            <?php } else { ?>
                                <span class="badge rounded-pill bg-danger">dicabut</span>
                            <?php } ?>
                            <span class="ms-auto small text-muted">
                                <?= $k->terakhir_pakai ? 'pemakaian terakhir ' . date('d/m/Y H:i', (int) $k->terakhir_pakai) : 'belum pernah dipakai' ?>
                            </span>
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="collapse"
                                data-bs-target="#sunting-kunci-<?= (int) $k->id_kunci ?>" aria-expanded="false"
                                aria-controls="sunting-kunci-<?= (int) $k->id_kunci ?>"
                                title="Sunting hak dan tokonya"><i class="fal fa-pen"></i></button>
                            <?= form_open('admin/settings/kunci-api/hapus/' . (int) $k->id_kunci, ['class' => 'd-inline']) ?>
                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Hapus kunci ini dari daftar"
                                aria-label="Hapus kunci <?= esc($k->nama, 'attr') ?>"
                                onclick="return confirm('<?= esc($peringatan, 'js') ?>')"><i class="fal fa-trash"></i></button>
                            <?= form_close() ?>
                        </div>

                        <div class="d-flex flex-wrap gap-2 mt-2">
                            <?php foreach ($hak as $slug => $label) { ?>
                                <span class="badge rounded-pill <?= in_array($slug, $punya_hak, true) ? 'bg-primary' : 'bg-light text-dark border' ?>">
                                    <?= esc($label) ?>
                                </span>
                            <?php } ?>
                        </div>

                        <div class="small text-muted mt-2">
                            <i class="fal fa-store me-1"></i><?= esc(implode(', ', $nama_toko) ?: '-') ?>
                            &middot; batas <?= (int) $k->batas_harian === 0 ? 'tanpa batas' : (int) $k->batas_harian . ' permintaan/hari' ?>
                            &middot; <span class="text-break">hash <?= esc(mb_substr((string) $k->kunci, 0, 12)) ?>…</span>
                        </div>

                        <div class="d-flex flex-wrap align-items-center gap-2 mt-2">
                            <i class="fal fa-key small text-muted" aria-hidden="true"></i>
                            <?php if ($k->token_asli !== null) { ?>
                                <code id="kunci-<?= (int) $k->id_kunci ?>" class="kunci-rahasia" role="button" tabindex="0"
                                    title="Klik untuk melihat / menyembunyikan nilainya"><?= esc($k->token_asli) ?></code>
                                <?php /* menyalin tidak perlu membuka kaburnya dulu: nilainya sudah ada di elemen ini */ ?>
                                <button type="button" class="btn btn-sm btn-outline-secondary btn-salin"
                                    data-target="kunci-<?= (int) $k->id_kunci ?>"
                                    title="Salin kunci <?= esc($k->nama, 'attr') ?> ke papan klip">
                                    <i class="fal fa-copy"></i> Salin
                                </button>
                            <?php } else { ?>
                                <span class="small text-muted">nilai kunci lama tidak tersimpan — buat kunci baru bila perlu melihatnya</span>
                            <?php } ?>
                        </div>

                        <div class="collapse mt-3" id="sunting-kunci-<?= (int) $k->id_kunci ?>">
                            <?= form_open('admin/settings/kunci-api/sunting/' . (int) $k->id_kunci, ['class' => 'row g-3']) ?>
                            <div class="col-12 col-md-4">
                                <?= form_label('Nama pemanggil', 'nama_' . (int) $k->id_kunci, ['class' => 'form-label small']) ?>
                                <?= form_input([
                                    'class'       => 'form-control form-control-sm',
                                    'name'        => 'nama',
                                    'id'          => 'nama_' . (int) $k->id_kunci,
                                    'value'       => set_value('nama', (string) $k->nama),
                                    'maxlength'   => 60,
                                    'required'    => '',
                                ]) ?>
                            </div>
                            <div class="col-6 col-md-3">
                                <?= form_label('Batas harian', 'batas_' . (int) $k->id_kunci, ['class' => 'form-label small']) ?>
                                <?= form_input([
                                    'class'  => 'form-control form-control-sm',
                                    'type'   => 'number',
                                    'name'   => 'batas_harian',
                                    'id'     => 'batas_' . (int) $k->id_kunci,
                                    'min'    => 0,
                                    'max'    => 100000,
                                    'value'  => set_value('batas_harian', (string) $k->batas_harian),
                                ]) ?>
                            </div>
                            <div class="col-6 col-md-2">
                                <?= form_label('Status', 'hidup_' . (int) $k->id_kunci, ['class' => 'form-label small']) ?>
                                <?= form_dropdown([
                                    'class'    => 'form-select form-select-sm',
                                    'name'     => 'hidup',
                                    'id'       => 'hidup_' . (int) $k->id_kunci,
                                    'options'  => [1 => 'Aktif', 0 => 'Dicabut'],
                                    'selected' => (int) old('hidup', (string) $k->hidup),
                                ]) ?>
                            </div>
                            <div class="col-12 col-md-3 d-flex align-items-end gap-2">
                                <button type="submit" class="btn btn-sm btn-primary"><i class="fal fa-save"></i> Simpan</button>
                            </div>
                            <div class="col-12">
                                <div class="form-label small mb-1">Hak akses</div>
                                <div class="gulir-pilihan d-flex flex-wrap gap-2"><?= $pil_hak('sunting-' . (int) $k->id_kunci, $punya_hak, $hak) ?></div>
                            </div>
                            <div class="col-12">
                                <div class="d-flex align-items-center gap-2 mb-1">
                                    <div class="form-label small mb-0">Toko yang boleh dibuka</div>
                                    <span class="ms-auto d-inline-flex gap-2">
                                        <button type="button" class="btn btn-sm btn-link p-0 small fw-semibold text-nowrap"
                                            data-pilih-semua="pilihTokoSunting-<?= (int) $k->id_kunci ?>">Pilih semua toko</button>
                                        <button type="button" class="btn btn-sm btn-link p-0 small text-muted text-nowrap"
                                            data-kosongkan="pilihTokoSunting-<?= (int) $k->id_kunci ?>">Kosongkan</button>
                                    </span>
                                </div>
                                <div class="gulir-pilihan d-flex flex-wrap gap-2 rounded-3 p-2 border" id="pilihTokoSunting-<?= (int) $k->id_kunci ?>">
                                    <?= $pil_toko('sunting-' . (int) $k->id_kunci, $punya_toko, $toko) ?>
                                </div>
                            </div>
                            <?= form_close() ?>

                            <?= form_open('admin/settings/kunci-api/cabut') ?>
                            <input type="hidden" name="id_kunci" value="<?= (int) $k->id_kunci ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger mt-3"
                                onclick="return confirm('Cabut kunci <?= esc($k->nama, 'attr') ?>? Situs pemakainya langsung kena 401.')">
                                <i class="fal fa-ban"></i> Cabut kunci ini
                            </button>
                            <?= form_close() ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach ?>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('modal') ?>
<div class="modal fade" id="modalBuatKunci" tabindex="-1" aria-labelledby="modalBuatKunciLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <?= form_open('admin/settings/kunci-api/save', ['class' => 'modal-content']) ?>
        <div class="modal-header">
            <h5 class="modal-title" id="modalBuatKunciLabel">Buat Kunci API</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
            <div class="row g-3">
                <div class="col-12 col-md-7">
                    <?= form_label('Nama pemanggil', 'nama_baru', ['class' => 'form-label']) ?>
                    <?= form_input([
                        'class'       => 'form-control',
                        'name'        => 'nama',
                        'id'          => 'nama_baru',
                        'placeholder' => 'Website Toko A',
                        'maxlength'   => 60,
                        'required'    => '',
                        'value'       => set_value('nama'),
                    ]) ?>
                    <div class="form-text">Dipakai untuk mengenali siapa memakainya nanti.</div>
                </div>
                <div class="col-12 col-md-5">
                    <?= form_label('Batas harian', 'batas_baru', ['class' => 'form-label']) ?>
                    <?= form_input([
                        'class'  => 'form-control',
                        'type'   => 'number',
                        'name'   => 'batas_harian',
                        'id'     => 'batas_baru',
                        'min'    => 0,
                        'max'    => 100000,
                        'value'  => set_value('batas_harian', (string) \App\Models\KunciApiModel::BATAS_BAKU),
                    ]) ?>
                    <div class="form-text">0 = tanpa batas. Kelebihannya dibalas 429.</div>
                </div>
                <div class="col-12">
                    <div class="form-label">Hak akses</div>
                    <div class="gulir-pilihan d-flex flex-wrap gap-2"><?= $pil_hak('baru', [], $hak) ?></div>
                </div>
                <div class="col-12">
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <div class="form-label mb-0">Toko yang boleh dibuka</div>
                        <span class="ms-auto d-inline-flex gap-2">
                            <button type="button" class="btn btn-sm btn-link p-0 small fw-semibold text-nowrap"
                                data-pilih-semua="pilihTokoBaru">Pilih semua toko</button>
                            <button type="button" class="btn btn-sm btn-link p-0 small text-muted text-nowrap"
                                data-kosongkan="pilihTokoBaru">Kosongkan</button>
                        </span>
                    </div>
                    <div class="gulir-pilihan d-flex flex-wrap gap-2 rounded-3 p-2 border" id="pilihTokoBaru">
                        <?= $pil_toko('baru', [], $toko) ?>
                    </div>
                    <div class="form-text">Kunci hanya melihat barang dan angka dari toko yang dicentang di sini.</div>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-link text-decoration-none" data-bs-dismiss="modal">Batal</button>
            <button type="submit" class="btn btn-primary"><i class="fal fa-key"></i> Buat kunci</button>
        </div>
        <?= form_close() ?>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('js') ?>
<script>
$(function () {
    'use strict';

    $(document).on('click', '.kunci-rahasia', function () {
        $(this).toggleClass('terlihat');
    });

    // role="button" + tabindex di atas berarti keyboard juga harus bisa membuka
    // kaburnya, bukan hanya mouse
    $(document).on('keydown', '.kunci-rahasia', function (e) {
        if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            $(this).toggleClass('terlihat');
        }
    });

    /*
     * Menyalin dilakukan dari teks yang memang sudah ada di halaman, bukan dari
     * salinan di atribut data: kuncinya tidak ditulis dua kali di HTML, dan yang
     * sedang dikabur bisa langsung disalin tanpa dibuka dulu.
     *
     * navigator.clipboard hanya ada di halaman aman (https atau localhost), jadi
     * server yang masih http lewat IP lokal ikut disiapkan jalan textareanya.
     */
    function salinCadangan(teks, selesai, gagal) {
        var kotak = document.createElement('textarea');

        kotak.value = teks;
        kotak.setAttribute('readonly', 'readonly');
        kotak.style.position = 'fixed';
        kotak.style.left = '-9999px';
        document.body.appendChild(kotak);
        kotak.select();

        try {
            if (document.execCommand('copy')) {
                selesai();
            } else {
                gagal();
            }
        } catch (e) {
            // peramban menolak menyalin dari sini; jangan menjanjikan "Tersalin"
            // yang tidak pernah terjadi
            gagal();
        }

        document.body.removeChild(kotak);
    }

    $(document).on('click', '.btn-salin', function () {
        var tombol = $(this);
        var target = tombol.data('target');
        var nilai = $.trim($('#' + target).text());

        if (nilai === '') {
            return;
        }

        var selesai = function () {
            var asal = tombol.html();

            tombol.html('<i class="fal fa-check"></i> Tersalin').prop('disabled', true);
            window.setTimeout(function () {
                tombol.html(asal).prop('disabled', false);
            }, 1500);
        };

        // papan klipnya memang tidak bisa dipakai (peramban lama, atau halaman ini
        // dibuka lewat http): kaburnya dibuka saja supaya tinggal Ctrl+C — tombolnya
        // tidak boleh terasa mati
        var gagal = function () {
            var kunci = document.getElementById(target);

            if (kunci) {
                kunci.classList.add('terlihat');
            }
        };

        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(nilai).then(selesai, function () {
                salinCadangan(nilai, selesai, gagal);
            });

            return;
        }

        salinCadangan(nilai, selesai, gagal);
    });

    $(document).on('click', '[data-pilih-semua]', function () {
        $('#' + $(this).data('pilih-semua')).find('input[type="checkbox"]').prop('checked', true);
    });

    $(document).on('click', '[data-kosongkan]', function () {
        $('#' + $(this).data('kosongkan')).find('input[type="checkbox"]').prop('checked', false);
    });
});
</script>
<?= $this->endSection() ?>
