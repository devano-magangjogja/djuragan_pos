<?= $this->extend('template/main') ?>

<?= $this->section('content') ?>
<?= $this->include('admin/navbar') ?>

<div class="container-xxl py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h3 mb-1">Follow-up Tagihan Belum Lunas</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb p-0 m-0">
                    <li class="breadcrumb-item"><?= anchor('', 'Dasbor') ?></li>
                    <li class="breadcrumb-item"><?= anchor('admin/crm', 'CRM') ?></li>
                    <li class="breadcrumb-item active" aria-current="page">Tagihan</li>
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
            <form method="get" action="<?= site_url('admin/crm/tagihan') ?>" class="row g-2 align-items-center">
                <div class="col-md-5">
                    <div class="input-group">
                        <span class="input-group-text bg-white"><i class="fal fa-search text-muted"></i></span>
                        <input type="text" name="cari" class="form-control" placeholder="Cari nomor invoice, nama, atau no HP..." value="<?= esc($cari) ?>">
                    </div>
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-dark"><i class="fal fa-filter me-1"></i> Filter</button>
                    <?php if (!empty($cari)) : ?>
                        <a href="<?= site_url('admin/crm/tagihan') ?>" class="btn btn-outline-secondary">Reset</a>
                    <?php endif; ?>
                </div>
                <div class="col text-end text-muted small">
                    Ditemukan <strong><?= count($invoices) ?></strong> pesanan belum lunas
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
                        <th>Total Belanja</th>
                        <th>Telah Dibayar</th>
                        <th>Sisa Tagihan</th>
                        <th class="text-center" style="width: 140px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($invoices)) : ?>
                        <tr>
                            <td colspan="8" class="text-center py-5 text-muted">
                                <i class="fal fa-check-circle fa-3x text-success mb-2 d-block"></i>
                                Tidak ada antrean tagihan yang perlu difollow-up.
                            </td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ($invoices as $inv) : ?>
                            <tr>
                                <td>
                                    <strong><?= esc($inv['seri']) ?></strong>
                                    <div class="small text-muted"><?= date('d M Y', strtotime($inv['tanggal_pesan'])) ?></div>
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
                                <td>Rp <?= number_format($inv['total_tagihan'], 0, ',', '.') ?></td>
                                <td>Rp <?= number_format($inv['telah_dibayar'], 0, ',', '.') ?></td>
                                <td>
                                    <span class="badge bg-danger fs-6">
                                        Rp <?= number_format($inv['sisa_tagihan'], 0, ',', '.') ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <button type="button" 
                                            class="btn btn-sm btn-success btn-followup"
                                            data-id="<?= $inv['id_invoice'] ?>"
                                            data-seri="<?= esc($inv['seri']) ?>"
                                            data-nama="<?= esc($inv['nama_pelanggan']) ?>"
                                            data-hp="<?= esc($inv['nomor_hp']) ?>"
                                            data-pelanggan-id="<?= $inv['id_pelanggan'] ?>"
                                            data-total="<?= number_format($inv['total_tagihan'], 0, ',', '.') ?>"
                                            data-sisa="<?= number_format($inv['sisa_tagihan'], 0, ',', '.') ?>"
                                            data-link="<?= site_url('download/invoice/' . $inv['seri']) ?>">
                                        <i class="fab fa-whatsapp me-1"></i> Follow Up
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

<!-- Modal Follow Up Tagihan -->
<div class="modal fade" id="modalFollowup" tabindex="-1" aria-labelledby="modalFollowupLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="modalFollowupLabel">
                    <i class="fab fa-whatsapp me-2"></i>Kirim Pengingat Tagihan via WhatsApp
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="modalAlert" class="alert d-none"></div>

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-bold">Nomor Invoice</label>
                        <input type="text" id="targetSeri" class="form-control" readonly>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold">Nama Pelanggan</label>
                        <input type="text" id="targetNama" class="form-control" readonly>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold">Nomor Tujuan WhatsApp</label>
                        <input type="text" id="targetHp" class="form-control font-monospace" placeholder="628xxx">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold">Sisa Tagihan</label>
                        <input type="text" id="targetSisa" class="form-control text-danger fw-bold" readonly>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-bold">Isi Pesan WhatsApp</label>
                    <textarea id="targetPesan" class="form-control font-monospace" rows="6"></textarea>
                    <div class="form-text small">
                        Pesan akan otomatis diformat dan dikirim melalui WhatsApp API (Kapso/Fonnte).
                    </div>
                </div>

                <div class="form-check p-3 bg-light rounded border">
                    <input class="form-check-input" type="checkbox" id="checkSandbox" checked>
                    <label class="form-check-label small" for="checkSandbox">
                        <strong>Kirim ke Nomor Sandbox Pengujian</strong> (Rekomendasi saat tahap testing Kapso Sandbox agar tidak error bila nomor pelanggan belum didaftarkan di sandbox).
                    </label>
                </div>

                <input type="hidden" id="targetIdInvoice">
                <input type="hidden" id="targetPelangganId">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-success fw-bold" id="btnKirimWa">
                    <i class="fab fa-whatsapp me-1"></i> <span id="btnKirimText">Kirim Pesan WhatsApp</span>
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
    var modal = new bootstrap.Modal(document.getElementById('modalFollowup'));

    $('.btn-followup').on('click', function() {
        var id = $(this).data('id');
        var seri = $(this).data('seri');
        var nama = $(this).data('nama');
        var hp = $(this).data('hp');
        var pelangganId = $(this).data('pelanggan-id');
        var total = $(this).data('total');
        var sisa = $(this).data('sisa');
        var link = $(this).data('link');

        $('#targetIdInvoice').val(id);
        $('#targetPelangganId').val(pelangganId);
        $('#targetSeri').val(seri);
        $('#targetNama').val(nama);
        $('#targetHp').val(hp);
        $('#targetSisa').val('Rp ' + sisa);

        // Render template teks
        var pesan = rawTemplate
            .replace(/\{nama\}/g, nama)
            .replace(/\{invoice\}/g, seri)
            .replace(/\{total\}/g, total)
            .replace(/\{sisa\}/g, sisa)
            .replace(/\{link_invoice\}/g, link);

        $('#targetPesan').val(pesan);
        $('#modalAlert').addClass('d-none').removeClass('alert-success alert-danger').text('');
        $('#btnKirimWa').prop('disabled', false);
        $('#btnKirimText').text('Kirim Pesan WhatsApp');

        modal.show();
    });

    $('#btnKirimWa').on('click', function() {
        var idInvoice = $('#targetIdInvoice').val();
        var pelangganId = $('#targetPelangganId').val();
        var nomor = $('#targetHp').val().trim();
        var pesan = $('#targetPesan').val().trim();
        var forceSandbox = $('#checkSandbox').is(':checked') ? 1 : 0;

        if (!nomor || !pesan) {
            $('#modalAlert').removeClass('d-none alert-success').addClass('alert-danger').text('Nomor tujuan dan isi pesan wajib diisi.');
            return;
        }

        $('#btnKirimWa').prop('disabled', true);
        $('#btnKirimText').text('Mengirim...');

        $.ajax({
            url: '<?= site_url('admin/crm/kirim_wa') ?>',
            type: 'POST',
            dataType: 'json',
            data: {
                id_invoice: idInvoice,
                pelanggan_id: pelangganId,
                nomor: nomor,
                pesan: pesan,
                tipe: 'tagihan',
                force_sandbox: forceSandbox
            },
            success: function(res) {
                if (res.status === 'success') {
                    var pesanSukses = res.message;
                    if (res.notice) {
                        pesanSukses += ' (' + res.notice + ')';
                    }
                    $('#modalAlert').removeClass('d-none alert-danger').addClass('alert-success').text(pesanSukses);
                    $('#btnKirimText').text('Terkirim!');
                    setTimeout(function() {
                        modal.hide();
                    }, 2000);
                } else {
                    $('#modalAlert').removeClass('d-none alert-success').addClass('alert-danger').text(res.message);
                    $('#btnKirimWa').prop('disabled', false);
                    $('#btnKirimText').text('Kirim Pesan WhatsApp');
                }
            },
            error: function(xhr) {
                $('#modalAlert').removeClass('d-none alert-success').addClass('alert-danger').text('Gagal menghubungi server.');
                $('#btnKirimWa').prop('disabled', false);
                $('#btnKirimText').text('Kirim Pesan WhatsApp');
            }
        });
    });
});
</script>
<?= $this->endSection() ?>
