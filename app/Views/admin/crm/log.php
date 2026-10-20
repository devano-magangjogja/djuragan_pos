<?= $this->extend('template/main') ?>

<?= $this->section('css') ?>
<style>
/* ruang halaman dikecilkan. style.css memaksa padding-top/bottom container dengan
   !important, jadi di sini pakai !important + selector yang lebih spesifik */
body.has-app-sidebar > div.container-xxl.log-halaman {
    padding-top: 0.75rem !important;
    padding-right: 0.5rem !important;
    padding-bottom: 1rem !important;
    padding-left: 0.5rem !important;
}
.log-halaman .card-header {
    padding: 0.5rem 0.75rem !important;
}
.log-halaman .table thead th,
.log-halaman .table tbody td {
    padding: 0.45rem 0.6rem !important;
}

/* Layar sempit: kolom Isi Pesan butuh ruang selebar layar. Tanpa ini teksnya
   terjerat di kolom 89px (sebaris cuma ~10 karakter, barisnya jadi 127px
   tingginya) sementara tabelnya memang sudah bisa digeser ke samping. */
@media (max-width: 767.98px) {
    .log-halaman .kolom-isi {
        min-width: calc(100vw - 2rem);
    }
}
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?= $this->include('admin/navbar') ?>

<div class="container-xxl log-halaman">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h3 mb-0">Riwayat Pengiriman Pesan WhatsApp</h1>
        </div>
        <div class="d-flex align-items-center gap-2">
            <?= $this->include('admin/crm/tombol_wa') ?>
        </div>
    </div>


    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white py-3">
            <h6 class="mb-0 fw-bold"><i class="fal fa-history me-2 text-primary"></i>Log Pengiriman Pesan</h6>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width: 150px;">Waktu</th>
                        <th>Tujuan</th>
                        <th>Provider</th>
                        <th>Tipe</th>
                        <?php /* width:100% membuat kolom ini menyerap sisa lebar tabel,
                           jadi isi pesan memanjang ke kanan alih-alih berhenti di
                           tengah; kolom lain otomatis menyusut ke lebar konten minimumnya */ ?>
                        <th style="width: 100%;" class="kolom-isi">Isi Pesan</th>
                        <th>Pengirim</th>
                        <th style="width: 110px;">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logPesan)) : ?>
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="fal fa-comment-slash fa-3x text-muted mb-2 d-block"></i>
                                Belum ada riwayat pesan WhatsApp yang tercatat.
                            </td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ($logPesan as $log) : ?>
                            <tr>
                                <td class="small text-muted font-monospace text-nowrap">
                                    <?= date('d/m/Y H:i:s', (int) $log['created_at']) ?>
                                </td>
                                <td>
                                    <strong><?= esc($log['nama_pelanggan'] ?: 'Umum') ?></strong>
                                    <div class="small text-muted font-monospace"><?= esc($log['nomor_tujuan']) ?></div>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border">
                                        <?= strtoupper(esc($log['provider'])) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-secondary">
                                        <?= strtoupper(esc($log['tipe_pesan'])) ?>
                                    </span>
                                </td>
                                <td class="kolom-isi">
                                    <?php /* tanpa max-width: kolom ini yang menyerap sisa lebar tabel, jadi isinya memanjang ke kanan */ ?>
                                    <div class="small font-monospace" style="white-space: pre-wrap; word-break: break-word;"><?= esc($log['isi_pesan']) ?></div>
                                    <?php if (!empty($log['invoice_seri'])) : ?>
                                        <div class="small text-primary mt-1">Invoice: <strong><?= esc($log['invoice_seri']) ?></strong></div>
                                    <?php endif; ?>
                                    <?php if (!empty($log['error_message'])) : ?>
                                        <div class="small text-danger mt-1">Error: <?= esc($log['error_message']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="small text-muted">
                                    <?= esc($log['nama_pengirim'] ?: 'Sistem') ?>
                                </td>
                                <td>
                                    <?php if ($log['status'] === 'terkirim') : ?>
                                        <span class="badge bg-success"><i class="fal fa-check me-1"></i> Terkirim</span>
                                    <?php else : ?>
                                        <span class="badge bg-danger"><i class="fal fa-times me-1"></i> Gagal</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if ($totalPages > 1) : ?>
            <?php
            $akhir = (int) $totalPages;

            // Jendela 5 nomor di sekitar halaman aktif, plus halaman pertama/terakhir
            // dengan penanda "..." supaya halaman terjauh tetap bisa diklik.
            $dari   = max(1, min($page - 2, $akhir - 4));
            $sampai = min($akhir, $dari + 4);

            $nomorHalaman = [];
            if ($dari > 1) {
                $nomorHalaman[] = 1;
                if ($dari > 2) {
                    $nomorHalaman[] = null;
                }
            }
            for ($n = $dari; $n <= $sampai; $n++) {
                $nomorHalaman[] = $n;
            }
            if ($sampai < $akhir) {
                if ($sampai < $akhir - 1) {
                    $nomorHalaman[] = null;
                }
                $nomorHalaman[] = $akhir;
            }
            ?>
            <div class="card-footer bg-white d-flex justify-content-between align-items-center py-3 crm-pager">
                <div class="small text-muted">
                    Menampilkan <?= count($logPesan) ?> dari <?= number_format($total) ?> pesan terkirim &middot; Halaman <?= (int) $page ?> dari <?= $akhir ?>
                </div>
                <ul class="pagination pagination-sm mb-0">
                    <?php if ($page > 1) : ?>
                        <li class="page-item">
                            <a class="page-link" href="<?= site_url('admin/crm/log' . ($page - 1 > 1 ? '?page=' . ($page - 1) : '')) ?>">&laquo; Sebelumnya</a>
                        </li>
                    <?php else : ?>
                        <li class="page-item disabled">
                            <span class="page-link">&laquo; Sebelumnya</span>
                        </li>
                    <?php endif; ?>

                    <?php foreach ($nomorHalaman as $n) : ?>
                        <?php if ($n === null) : ?>
                            <li class="page-item disabled"><span class="page-link">&hellip;</span></li>
                        <?php elseif ($n === $page) : ?>
                            <li class="page-item active" aria-current="page"><span class="page-link"><?= $n ?></span></li>
                        <?php else : ?>
                            <li class="page-item"><a class="page-link" href="<?= site_url('admin/crm/log' . ($n > 1 ? '?page=' . $n : '')) ?>"><?= $n ?></a></li>
                        <?php endif; ?>
                    <?php endforeach; ?>

                    <?php if ($page < $akhir) : ?>
                        <li class="page-item">
                            <a class="page-link" href="<?= site_url('admin/crm/log?page=' . ($page + 1)) ?>">Berikutnya &raquo;</a>
                        </li>
                    <?php else : ?>
                        <li class="page-item disabled">
                            <span class="page-link">Berikutnya &raquo;</span>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        <?php endif; ?>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('js') ?>
<script defer src="https://kit.fontawesome.com/22a9d93fa2.js" data-auto-replace-svg="nest" crossorigin="anonymous"></script>
<?= $this->endSection() ?>
