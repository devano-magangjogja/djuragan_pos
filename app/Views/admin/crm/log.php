<?= $this->extend('template/main') ?>

<?= $this->section('content') ?>
<?= $this->include('admin/navbar') ?>

<div class="container-xxl py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h3 mb-1">Riwayat Pengiriman Pesan WhatsApp</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb p-0 m-0">
                    <li class="breadcrumb-item"><?= anchor('', 'Dasbor') ?></li>
                    <li class="breadcrumb-item"><?= anchor('admin/crm', 'CRM') ?></li>
                    <li class="breadcrumb-item active" aria-current="page">Riwayat Pesan</li>
                </ol>
            </nav>
        </div>
    </div>

    <?= $this->include('admin/crm/nav_crm') ?>

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
                        <th>Isi Pesan</th>
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
                                <td class="small text-muted font-monospace">
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
                                <td>
                                    <div class="small font-monospace" style="max-width: 450px; white-space: pre-wrap; word-break: break-word;"><?= esc($log['isi_pesan']) ?></div>
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
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('js') ?>
<script defer src="https://kit.fontawesome.com/22a9d93fa2.js" data-auto-replace-svg="nest" crossorigin="anonymous"></script>
<?= $this->endSection() ?>
