<?= $this->extend('template/main') ?>

<?= $this->section('content') ?>
<?= $this->include('admin/navbar') ?>

<div class="container-xxl py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h3 mb-1">Broadcast Promo &amp; Pesan Massal WhatsApp</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb p-0 m-0">
                    <li class="breadcrumb-item"><?= anchor('', 'Dasbor') ?></li>
                    <li class="breadcrumb-item"><?= anchor('admin/crm', 'CRM') ?></li>
                    <li class="breadcrumb-item active" aria-current="page">Broadcast</li>
                </ol>
            </nav>
        </div>
        <div class="d-flex align-items-center gap-2">
            <?= $this->include('admin/crm/tombol_wa') ?>
        </div>
    </div>

    <?= $this->include('admin/crm/nav_crm') ?>

    <?php if (($provider ?? '') === 'fonnte') : ?>
        <div class="alert alert-success border-0 shadow-sm d-flex align-items-center mb-4">
            <div class="me-3 fs-3">
                <i class="fab fa-whatsapp"></i>
            </div>
            <div>
                <h6 class="alert-heading mb-1 fw-bold">Mode Fonnte Gateway Aktif</h6>
                <div class="small">
                    Pesan siaran broadcast akan dikirimkan langsung ke seluruh nomor WhatsApp kontak pelanggan yang telah opt-in menerima promo melalui Fonnte Gateway.
                </div>
            </div>
        </div>
    <?php elseif (!empty($isSandbox)) : ?>
        <div class="alert alert-warning border-0 shadow-sm d-flex align-items-center mb-4">
            <div class="me-3 fs-3">
                <i class="fal fa-flask"></i>
            </div>
            <div>
                <h6 class="alert-heading mb-1 fw-bold">Mode Sandbox Kapso Aktif</h6>
                <div class="small">
                    Untuk mencegah nomor terblokir saat testing, WhatsApp Cloud Sandbox hanya dapat mengirim pesan ke nomor yang telah didaftarkan di sandbox Kapso. Pesan broadcast dalam mode ini akan dikirimkan sebagai sampel uji coba ke nomor sandbox pengujian: <strong><?= esc($sandboxNumber) ?></strong>.
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Form Buat Broadcast -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3">
                    <h6 class="mb-0 fw-bold"><i class="fal fa-paper-plane me-2 text-primary"></i>Buat Pesan Siaran (Broadcast)</h6>
                </div>
                <div class="card-body">
                    <div id="alertBroadcast" class="alert d-none"></div>

                    <form id="formBroadcast">
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Judul Kampanye Promo</label>
                            <input type="text" name="judul" id="broadcastJudul" class="form-control" placeholder="Contoh: Promo Diskon Awal Bulan" value="Promo Spesial Pelanggan Djuragan POS" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold">Target Segmen Pelanggan</label>
                            <select name="segmen" id="broadcastSegmen" class="form-select">
                                <option value="semua">Semua Kontak Pelanggan</option>
                                <option value="repeat">Pelanggan Repeat Order (Loyal > 1 Pesanan)</option>
                                <option value="sekali">Pelanggan Baru (1 Pesanan)</option>
                                <option value="pasif">Pelanggan Pasif (> 60 Hari Tidak Ada Pesanan)</option>
                            </select>
                            <div class="form-text small">Pilih kelompok pelanggan yang ingin dijangkau pesan promosi ini.</div>
                        </div>

                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <label class="form-label small fw-bold mb-0">Isi Pesan WhatsApp</label>
                                <div class="btn-group btn-group-sm">
                                    <button type="button" class="btn btn-outline-secondary btn-sm btn-tag" data-tag="{nama}">+ {nama}</button>
                                    <button type="button" class="btn btn-outline-secondary btn-sm btn-tag" data-tag="{total_order}">+ {total_order}</button>
                                </div>
                            </div>
                            <textarea name="pesan" id="broadcastPesan" class="form-control font-monospace" rows="8" required><?= esc($template) ?></textarea>
                            <div class="form-text small">Gunakan tag dinamis seperti <code>{nama}</code> untuk menyapa pelanggan secara personal.</div>
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-success btn-lg fw-bold" id="btnSubmitBroadcast">
                                <i class="fab fa-whatsapp me-2"></i><span id="btnSubmitText">Kirim Pesan Siaran Sekarang</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Riwayat Broadcast -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white py-3">
                    <h6 class="mb-0 fw-bold"><i class="fal fa-history me-2 text-primary"></i>Riwayat Broadcast Terakhir</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Kampanye</th>
                                    <th>Segmen</th>
                                    <th>Hasil</th>
                                    <th>Waktu</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($riwayatBroadcast)) : ?>
                                    <tr>
                                        <td colspan="4" class="text-center py-4 text-muted">
                                            <i class="fal fa-bullhorn fa-2x mb-2 d-block"></i>
                                            Belum ada riwayat broadcast promo.
                                        </td>
                                    </tr>
                                <?php else : ?>
                                    <?php foreach ($riwayatBroadcast as $bc) : ?>
                                        <tr>
                                            <td>
                                                <div class="fw-bold"><?= esc($bc['judul']) ?></div>
                                                <div class="small text-muted text-truncate" style="max-width: 200px;" title="<?= esc($bc['pesan']) ?>">
                                                    <?= esc($bc['pesan']) ?>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge bg-light text-dark border"><?= strtoupper(esc($bc['segmen'])) ?></span>
                                            </td>
                                            <td>
                                                <span class="badge bg-success"><?= $bc['total_terkirim'] ?> ok</span>
                                                <?php if ($bc['total_gagal'] > 0) : ?>
                                                    <span class="badge bg-danger"><?= $bc['total_gagal'] ?> fail</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="small text-muted">
                                                <?= date('d M Y H:i', (int) $bc['created_at']) ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
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

    $('.btn-tag').on('click', function() {
        var tag = $(this).data('tag');
        var textarea = $('#broadcastPesan');
        var pos = textarea.prop('selectionStart');
        var text = textarea.val();
        textarea.val(text.substring(0, pos) + tag + text.substring(pos));
        textarea.focus();
    });

    $('#formBroadcast').on('submit', function(e) {
        e.preventDefault();

        var judul = $('#broadcastJudul').val().trim();
        var segmen = $('#broadcastSegmen').val();
        var pesan = $('#broadcastPesan').val().trim();

        if (!judul || !pesan) {
            $('#alertBroadcast').removeClass('d-none alert-success').addClass('alert-danger').text('Judul dan isi pesan wajib diisi.');
            return;
        }

        if (!confirm('Apakah Anda yakin ingin mengeksekusi pengiriman broadcast ini?')) {
            return;
        }

        $('#btnSubmitBroadcast').prop('disabled', true);
        $('#btnSubmitText').text('Sedang Memproses Pengiriman...');

        $.ajax({
            url: '<?= site_url('admin/crm/kirim_broadcast') ?>',
            type: 'POST',
            dataType: 'json',
            data: {
                judul: judul,
                segmen: segmen,
                pesan: pesan
            },
            success: function(res) {
                if (res.status === 'success') {
                    $('#alertBroadcast').removeClass('d-none alert-danger').addClass('alert-success').text(res.message);
                    $('#btnSubmitText').text('Selesai Diproses!');
                    setTimeout(function() {
                        window.location.reload();
                    }, 2500);
                } else {
                    $('#alertBroadcast').removeClass('d-none alert-success').addClass('alert-danger').text(res.message);
                    $('#btnSubmitBroadcast').prop('disabled', false);
                    $('#btnSubmitText').text('Kirim Pesan Siaran Sekarang');
                }
            },
            error: function() {
                $('#alertBroadcast').removeClass('d-none alert-success').addClass('alert-danger').text('Terjadi kesalahan saat memproses permintaan.');
                $('#btnSubmitBroadcast').prop('disabled', false);
                $('#btnSubmitText').text('Kirim Pesan Siaran Sekarang');
            }
        });
    });
});
</script>
<?= $this->endSection() ?>
