<?= $this->extend('template/main') ?>

<?= $this->section('content') ?>
<?= $this->include('admin/navbar') ?>

<div class="container-xxl py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h3 mb-1">Template Pesan WhatsApp CRM</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb p-0 m-0">
                    <li class="breadcrumb-item"><?= anchor('', 'Dasbor') ?></li>
                    <li class="breadcrumb-item"><?= anchor('admin/crm', 'CRM') ?></li>
                    <li class="breadcrumb-item active" aria-current="page">Template Pesan</li>
                </ol>
            </nav>
        </div>
        <div class="d-flex align-items-center gap-2">
            <?= $this->include('admin/crm/tombol_wa') ?>
        </div>
    </div>

    <?= $this->include('admin/crm/nav_crm') ?>

    <div class="alert alert-info border-0 shadow-sm mb-4">
        <i class="fal fa-info-circle me-1"></i>
        <strong>Variabel Dinamis:</strong> Anda dapat menggunakan tag berikut di dalam teks template:
        <code>{nama}</code>, <code>{invoice}</code>, <code>{total}</code>, <code>{sisa}</code>, <code>{link_invoice}</code>, <code>{kurir}</code>, <code>{resi}</code>, <code>{total_order}</code>. Tag ini akan diganti dengan data pesanan secara otomatis saat mengirim pesan.
        <br>
        <small>Di Live Chat dan profil customer, datanya diambil dari nota terakhir yang belum lunas. Tag yang belum ada isinya (misalnya <code>{resi}</code> sebelum pesanan dikirim) sengaja tetap terlihat agar pesannya diedit lebih dulu, bukan terkirim dalam keadaan kosong.</small>
    </div>

    <div class="row g-4">
        <?php foreach ($templates as $tpl) : ?>
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                        <div>
                            <span class="badge bg-secondary me-2"><?= strtoupper(esc($tpl['kategori'])) ?></span>
                            <strong class="text-dark"><?= esc($tpl['judul']) ?></strong>
                        </div>
                        <small class="text-muted font-monospace"><?= esc($tpl['kode']) ?></small>
                    </div>
                    <div class="card-body">
                        <div class="alert d-none alert-tpl-<?= $tpl['id_template'] ?>"></div>
                        <form class="form-template" data-id="<?= $tpl['id_template'] ?>">
                            <div class="mb-3">
                                <label class="form-label small fw-bold">Judul Template</label>
                                <input type="text" name="judul" class="form-control" value="<?= esc($tpl['judul']) ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-bold">Format Isi Pesan</label>
                                <textarea name="pesan" class="form-control font-monospace" rows="7" required><?= esc($tpl['pesan']) ?></textarea>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <small class="text-muted">
                                    Diperbarui: <?= date('d M Y H:i', (int) $tpl['updated_at']) ?>
                                </small>
                                <button type="submit" class="btn btn-dark btn-sm fw-bold">
                                    <i class="fal fa-save me-1"></i> Simpan Perubahan
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('js') ?>
<script defer src="https://kit.fontawesome.com/22a9d93fa2.js" data-auto-replace-svg="nest" crossorigin="anonymous"></script>
<script>
$(function() {
    'use strict';

    $('.form-template').on('submit', function(e) {
        e.preventDefault();

        var form = $(this);
        var id = form.data('id');
        var btn = form.find('button[type="submit"]');
        var alertEl = $('.alert-tpl-' + id);

        btn.prop('disabled', true).text('Menyimpan...');

        $.ajax({
            url: '<?= site_url('admin/crm/simpan_template') ?>',
            type: 'POST',
            dataType: 'json',
            data: {
                id_template: id,
                judul: form.find('input[name="judul"]').val(),
                pesan: form.find('textarea[name="pesan"]').val()
            },
            success: function(res) {
                if (res.status === 'success') {
                    alertEl.removeClass('d-none alert-danger').addClass('alert-success').text(res.message);
                    setTimeout(function() {
                        alertEl.addClass('d-none');
                    }, 3000);
                } else {
                    alertEl.removeClass('d-none alert-success').addClass('alert-danger').text(res.message);
                }
                btn.prop('disabled', false).html('<i class="fal fa-save me-1"></i> Simpan Perubahan');
            },
            error: function() {
                alertEl.removeClass('d-none alert-success').addClass('alert-danger').text('Gagal menghubungi server.');
                btn.prop('disabled', false).html('<i class="fal fa-save me-1"></i> Simpan Perubahan');
            }
        });
    });
});
</script>
<?= $this->endSection() ?>
