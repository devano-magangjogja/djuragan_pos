<?php
/**
 * Variabel di bawah ini diisi dari Notifikasi::index() lewat view('notifikasi', $data).
 * CodeIgniter memanggil extract() di dalam system/View/View.php, jadi analyzers tidak
 * bisa menelusuri asal variabelnya; blok @var ini hanya memberi tahu Intelephense.
 *
 * @var string                                             $title         Judul halaman
 * @var list<array{id: int, pesan: string, juragan: ?string, seri: ?string, dibaca: bool, waktu: string, tautan: ?string}> $daftar  Baris notifikasi per halaman
 * @var string                                             $tab           Tab aktif: 'belum' atau 'semua'
 * @var int                                                $jumlah_belum  Jumlah notifikasi belum dibaca
 * @var int                                                $jumlah_semua  Total seluruh notifikasi
 * @var int                                                $halaman       Nomor halaman saat ini
 * @var int                                                $halaman_akhir Total halaman
 */
?>
<?= $this->extend('template/main') ?>

<?= $this->section('css') ?>
<style>
/* Notifikasi memakai dasar kartu yang sama dengan panel lonceng: garis tipis,
   sudut 12px, bayangan lembut. */
.notif-halaman .card {
    border: 1px solid #eaecf0 !important;
    border-radius: 12px !important;
    /* Tanpa ini, baris kepala & pager di dalamnya menutupi sudut kartu yang bulat. */
    overflow: hidden;
    box-shadow: 0 1px 3px rgba(16, 24, 40, 0.05) !important;
}

.notif-kepala {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: flex-end;
    gap: 0.5rem;
    padding: 0.7rem 1rem;
    border-bottom: 1px solid #eaecf0;
    background: #ffffff;
}

.notif-tab {
    display: flex;
    gap: 0.35rem;
}

.notif-tab a {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.35rem 0.8rem;
    border: 1px solid #eaecf0;
    border-radius: 9999px;
    background: #ffffff;
    color: #475467;
    font-size: 0.84rem;
    font-weight: 600;
    text-decoration: none;
}

.notif-tab a:hover {
    border-color: #d0d5dd;
    color: #101828;
}

.notif-tab a.aktif {
    background: #101828;
    border-color: #101828;
    color: #ffffff;
}

.notif-baris {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    padding: 0.85rem 1rem;
    border-bottom: 1px solid #f2f4f7;
}

.notif-baris:last-child {
    border-bottom: 0;
}

.notif-baris__isi {
    flex: 1 1 auto;
    min-width: 0;
}

.notif-baris__pesan {
    color: #344054;
    font-size: 0.875rem;
    line-height: 1.45;
}

.notif-baris__pesan a {
    color: #344054;
    text-decoration: none;
}

.notif-baris__pesan a:hover {
    color: #101828;
    text-decoration: underline;
}

.notif-baris__meta {
    margin-top: 0.15rem;
    color: #98a2b3;
    font-size: 0.75rem;
}

.notif-baris--baru {
    background: #ffffff;
}

.notif-baris--baru .notif-baris__pesan {
    color: #101828;
    font-weight: 600;
}

.notif-baris__titik {
    flex: 0 0 auto;
    width: 8px;
    height: 8px;
    margin-top: 0.45rem;
    border-radius: 9999px;
    background: #2563eb;
}

.notif-baris__aksi {
    flex: 0 0 auto;
}

.notif-kosong-halaman {
    padding: 3rem 1rem;
    color: #98a2b3;
    text-align: center;
}

.notif-buntut {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    padding: 0.7rem 1rem;
    border-top: 1px solid #eaecf0;
    background: #f9fafb;
    color: #475467;
    font-size: 0.8rem;
}

.notif-buntut a {
    padding: 0.25rem 0.7rem;
    border: 1px solid #d0d5dd;
    border-radius: 8px;
    color: #344054;
    font-weight: 600;
    text-decoration: none;
}

.notif-buntut a:hover {
    background: #ffffff;
}

.notif-buntut a.nonaktif {
    color: #c3c9d2;
    pointer-events: none;
}

@media (max-width: 575.98px) {
    .notif-baris {
        flex-wrap: wrap;
        padding: 0.75rem 0.8rem;
    }

    .notif-baris__aksi {
        margin-left: 1.25rem;
    }
}
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?php if (in_array(session()->get('level'), ['admin', 'superadmin'], true)) : ?>
    <?= $this->include('admin/navbar') ?>
<?php else : ?>
    <?= $this->include('user/navbar') ?>
<?php endif; ?>

<div class="container-xxl notif-halaman">
    <h1 class="h3 mb-3">Notifikasi</h1>

    <div class="card">
        <div class="notif-kepala">
            <div class="notif-tab">
                <a class="<?= $tab === 'belum' ? 'aktif' : '' ?>" href="<?= site_url(base_user() . '/notifikasi/belum') ?>">
                    Belum dibaca
                    <span class="badge rounded-pill bg-light text-dark border-0"><?= (int) $jumlah_belum ?></span>
                </a>
                <a class="<?= $tab === 'semua' ? 'aktif' : '' ?>" href="<?= site_url(base_user() . '/notifikasi/semua') ?>">
                    Semua
                    <span class="badge rounded-pill bg-light text-dark border-0"><?= (int) $jumlah_semua ?></span>
                </a>
            </div>

            <?php if ($jumlah_belum > 0) : ?>
                <button type="button" class="btn btn-sm btn-outline-secondary notif-tandai-semua">
                    <i class="fal fa-check-double"></i> Tandai semua dibaca
                </button>
            <?php endif; ?>
        </div>

        <?php if (empty($daftar)) : ?>
            <div class="notif-kosong-halaman">
                <i class="fal fa-bell-slash fa-3x mb-2 d-block"></i>
                <?= $tab === 'belum' ? 'Tidak ada notifikasi yang belum dibaca.' : 'Belum ada notifikasi.' ?>
            </div>
        <?php else : ?>
            <?php foreach ($daftar as $item) : ?>
                <div class="notif-baris<?= $item['dibaca'] ? '' : ' notif-baris--baru' ?>">
                    <?php if (! $item['dibaca']) : ?>
                        <span class="notif-baris__titik" aria-hidden="true"></span>
                    <?php endif; ?>

                    <div class="notif-baris__isi">
                        <div class="notif-baris__pesan">
                            <?php /* teks dari config/JuraganConfig, nomor nota ikut ditautkan */ ?>
                            <?php if ($item['tautan'] !== '') : ?>
                                <a href="<?= esc($item['tautan']) ?>"><?= $item['pesan'] ?></a>
                            <?php else : ?>
                                <?= $item['pesan'] ?>
                            <?php endif; ?>
                        </div>
                        <div class="notif-baris__meta">
                            <?= esc($item['waktu']) ?>
                            <?php if (! empty($item['juragan'])) : ?>
                                &middot; <?= esc($item['juragan']) ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="notif-baris__aksi">
                        <?php if (! $item['dibaca']) : ?>
                            <button type="button" class="btn btn-sm btn-link text-decoration-none notif-tandai" data-id="<?= (int) $item['id'] ?>">
                                Tandai dibaca
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <?php if ($halaman_akhir > 1) : ?>
            <div class="notif-buntut">
                <a class="<?= $halaman <= 1 ? 'nonaktif' : '' ?>" href="<?= site_url(base_user() . '/notifikasi/' . $tab) . ($halaman > 1 ? '?page=' . ($halaman - 1) : '') ?>">
                    <i class="fal fa-chevron-left"></i> Sebelumnya
                </a>
                <span>Halaman <?= (int) $halaman ?> dari <?= (int) $halaman_akhir ?></span>
                <a class="<?= $halaman >= $halaman_akhir ? 'nonaktif' : '' ?>" href="<?= site_url(base_user() . '/notifikasi/' . $tab) . ($halaman < $halaman_akhir ? '?page=' . ($halaman + 1) : '') ?>">
                    Berikutnya <i class="fal fa-chevron-right"></i>
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('js') ?>
<script>
    $(function() {
        var markUrl = '<?= site_url('api/notifikasi/mark') ?>';
        var markAllUrl = '<?= site_url('api/notifikasi/mark_all') ?>';

        $(document).on('click', '.notif-tandai', function() {
            var tombol = $(this);
            tombol.prop('disabled', true);

            $.post(markUrl, { action: 'sudahBaca', id: tombol.data('id') }, function() {
                window.location.reload();
            });
        });

        $('.notif-tandai-semua').on('click', function() {
            var tombol = $(this);
            tombol.prop('disabled', true);

            $.post(markAllUrl, {}, function() {
                window.location.reload();
            });
        });
    });
</script>
<?= $this->endSection() ?>
