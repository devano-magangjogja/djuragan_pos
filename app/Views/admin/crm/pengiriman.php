<?= $this->extend('template/main') ?>

<?= $this->section('content') ?>
<?= $this->include('admin/navbar') ?>

<div class="container-xxl py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h3 mb-1">Notifikasi Resi Pengiriman</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb p-0 m-0">
                    <li class="breadcrumb-item"><?= anchor('', 'Dasbor') ?></li>
                    <li class="breadcrumb-item"><?= anchor('admin/crm', 'CRM') ?></li>
                    <li class="breadcrumb-item active" aria-current="page">Pengiriman</li>
                </ol>
            </nav>
        </div>
        <div class="d-flex align-items-center gap-2">
            <?= $this->include('admin/crm/tombol_wa') ?>
        </div>
    </div>

    <?= $this->include('admin/crm/nav_crm') ?>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="get" action="<?= site_url('admin/crm/pengiriman') ?>" class="row g-2 align-items-center">
                <div class="col-md-5">
                    <div class="input-group">
                        <span class="input-group-text bg-white"><i class="fal fa-search text-muted"></i></span>
                        <input type="text" name="cari" class="form-control" placeholder="Cari invoice, resi, kurir, nama pelanggan..." value="<?= esc($cari) ?>">
                    </div>
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-dark"><i class="fal fa-filter me-1"></i> Filter</button>
                    <?php if (!empty($cari)) : ?>
                        <a href="<?= site_url('admin/crm/pengiriman') ?>" class="btn btn-outline-secondary">Reset</a>
                    <?php endif; ?>
                </div>
                <div class="col text-end text-muted small">
                    Ditemukan <strong><?= count($invoices) ?></strong> pengiriman dengan resi
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Invoice</th>
                        <th>Juragan</th>
                        <th>Pelanggan</th>
                        <th>Nomor HP</th>
                        <th>Kurir</th>
                        <th>Nomor Resi</th>
                        <th>Tanggal Kirim</th>
                        <th class="text-center" style="width: 140px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($invoices)) : ?>
                        <tr>
                            <td colspan="8" class="text-center py-5 text-muted">
                                <i class="fal fa-box-open fa-3x text-muted mb-2 d-block"></i>
                                Belum ada pengiriman dengan nomor resi.
                            </td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ($invoices as $inv) : ?>
                            <tr>
                                <td>
                                    <strong><?= esc($inv['seri']) ?></strong>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border"><?= esc($inv['nama_juragan']) ?></span>
                                </td>
                                <td>
                                    <strong><?= esc($inv['nama_pelanggan']) ?></strong>
                                </td>
                                <td>
                                    <span class="font-monospace"><?= esc($inv['nomor_hp'] ?: '-') ?></span>
                                </td>
                                <td>
                                    <span class="badge bg-primary"><?= strtoupper(esc($inv['kurir'])) ?></span>
                                </td>
                                <td>
                                    <span class="font-monospace fw-bold text-dark"><?= esc($inv['resi']) ?></span>
                                </td>
                                <td>
                                    <small class="text-muted">
                                        <?= !empty($inv['tanggal_kirim']) ? date('d M Y', (int) $inv['tanggal_kirim']) : '-' ?>
                                    </small>
                                </td>
                                <td class="text-center">
                                    <button type="button" 
                                            class="btn btn-sm btn-primary btn-notif-resi"
                                            data-id="<?= $inv['id_invoice'] ?>"
                                            data-seri="<?= esc($inv['seri']) ?>"
                                            data-nama="<?= esc($inv['nama_pelanggan']) ?>"
                                            data-hp="<?= esc($inv['nomor_hp']) ?>"
                                            data-pelanggan-id="<?= $inv['id_pelanggan'] ?>"
                                            data-kurir="<?= strtoupper(esc($inv['kurir'])) ?>"
                                            data-resi="<?= esc($inv['resi']) ?>">
                                        <i class="fab fa-whatsapp me-1"></i> Notif Resi
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Notifikasi Resi Pengiriman -->
<div class="modal fade" id="modalResi" tabindex="-1" aria-labelledby="modalResiLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="modalResiLabel">
                    <i class="fab fa-whatsapp me-2"></i>Kirim Notifikasi Resi via WhatsApp
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="modalAlertResi" class="alert d-none"></div>

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-bold">Nomor Invoice</label>
                        <input type="text" id="targetSeriResi" class="form-control" readonly>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold">Nama Pelanggan</label>
                        <input type="text" id="targetNamaResi" class="form-control" readonly>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-bold">Ekspedisi Kurir</label>
                        <input type="text" id="targetKurir" class="form-control" readonly>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-bold">Nomor Resi</label>
                        <input type="text" id="targetResi" class="form-control font-monospace fw-bold" readonly>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-bold">Nomor WhatsApp Tujuan</label>
                        <input type="text" id="targetHpResi" class="form-control font-monospace" placeholder="628xxx">
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-bold">Isi Pesan WhatsApp</label>
                    <textarea id="targetPesanResi" class="form-control font-monospace" rows="6"></textarea>
                </div>

                <?php if (!empty($isSandbox)) : ?>
                <div class="form-check p-3 bg-light rounded border">
                    <input class="form-check-input" type="checkbox" id="checkSandboxResi" checked>
                    <label class="form-check-label small" for="checkSandboxResi">
                        <strong>Kirim ke Nomor Sandbox Pengujian</strong> (Gunakan opsi ini saat testing Kapso Sandbox).
                    </label>
                </div>
                <?php else : ?>
                <input type="hidden" id="checkSandboxResi" value="0">
                <?php endif; ?>

                <input type="hidden" id="targetIdInvoiceResi">
                <input type="hidden" id="targetPelangganIdResi">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-primary fw-bold" id="btnKirimResi">
                    <i class="fab fa-whatsapp me-1"></i> <span id="btnKirimResiText">Kirim Info Resi</span>
                </button>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('js') ?>
<script defer src="https://kit.fontawesome.com/22a9d93fa2.js" data-auto-replace-svg="nest" crossorigin="anonymous"></script>
<script>
$(function() {
    'use strict';

    var rawTemplate = <?= json_encode($template) ?>;
    var modal = new bootstrap.Modal(document.getElementById('modalResi'));

    $('.btn-notif-resi').on('click', function() {
        var id = $(this).data('id');
        var seri = $(this).data('seri');
        var nama = $(this).data('nama');
        var hp = $(this).data('hp');
        var pelangganId = $(this).data('pelanggan-id');
        var kurir = $(this).data('kurir');
        var resi = $(this).data('resi');

        $('#targetIdInvoiceResi').val(id);
        $('#targetPelangganIdResi').val(pelangganId);
        $('#targetSeriResi').val(seri);
        $('#targetNamaResi').val(nama);
        $('#targetHpResi').val(hp);
        $('#targetKurir').val(kurir);
        $('#targetResi').val(resi);

        // Render template teks
        var pesan = rawTemplate
            .replace(/\{nama\}/g, nama)
            .replace(/\{invoice\}/g, seri)
            .replace(/\{kurir\}/g, kurir)
            .replace(/\{resi\}/g, resi);

        $('#targetPesanResi').val(pesan);
        $('#modalAlertResi').addClass('d-none').removeClass('alert-success alert-danger').text('');
        $('#btnKirimResi').prop('disabled', false);
        $('#btnKirimResiText').text('Kirim Info Resi');

        modal.show();
    });

    $('#btnKirimResi').on('click', function() {
        var idInvoice = $('#targetIdInvoiceResi').val();
        var pelangganId = $('#targetPelangganIdResi').val();
        var nomor = $('#targetHpResi').val().trim();
        var pesan = $('#targetPesanResi').val().trim();
        var forceSandbox = $('#checkSandboxResi').is(':checked') ? 1 : 0;

        if (!nomor || !pesan) {
            $('#modalAlertResi').removeClass('d-none alert-success').addClass('alert-danger').text('Nomor tujuan dan pesan tidak boleh kosong.');
            return;
        }

        $('#btnKirimResi').prop('disabled', true);
        $('#btnKirimResiText').text('Mengirim...');

        $.ajax({
            url: '<?= site_url('admin/crm/kirim_wa') ?>',
            type: 'POST',
            dataType: 'json',
            data: {
                id_invoice: idInvoice,
                pelanggan_id: pelangganId,
                nomor: nomor,
                pesan: pesan,
                tipe: 'pengiriman',
                force_sandbox: forceSandbox
            },
            success: function(res) {
                if (res.status === 'success') {
                    var msg = res.message;
                    if (res.notice) {
                        msg += ' (' + res.notice + ')';
                    }
                    $('#modalAlertResi').removeClass('d-none alert-danger').addClass('alert-success').text(msg);
                    $('#btnKirimResiText').text('Terkirim!');
                    setTimeout(function() {
                        modal.hide();
                    }, 2000);
                } else {
                    $('#modalAlertResi').removeClass('d-none alert-success').addClass('alert-danger').text(res.message);
                    $('#btnKirimResi').prop('disabled', false);
                    $('#btnKirimResiText').text('Kirim Info Resi');
                }
            },
            error: function() {
                $('#modalAlertResi').removeClass('d-none alert-success').addClass('alert-danger').text('Gagal menghubungi server.');
                $('#btnKirimResi').prop('disabled', false);
                $('#btnKirimResiText').text('Kirim Info Resi');
            }
        });
    });
});
</script>
<?= $this->endSection() ?>
