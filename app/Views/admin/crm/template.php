<?= $this->extend('template/main') ?>

<?= $this->section('css') ?>
<style>
/* Layar sempit: kepala kartu dan baris "Diperbarui + Simpan" ditumpuk.
   Di baris kepala, kode template (font-monospace, panjang) dulu dipaksa muat di
   samping judul sehingga ia menyelinap di tengah-tengah judul; sekarang turun ke
   barisnya sendiri. Baris aksi: tanggal di atas, tombol melebar penuh di bawah.
   !important diperlukan karena .align-items-center / .justify-content-between di
   markup juga !important. */
/* Accordion "Variabel Dinamis": panahnya berputar saat isinya terbuka. */
.tpl-akordeon .tpl-panah {
    transition: transform 0.15s ease;
}

.tpl-akordeon[aria-expanded="true"] .tpl-panah {
    transform: rotate(180deg);
}

/* Kepala accordion "Variabel Dinamis": tidak ada perubahan apa pun saat disorot,
   diarahkan, atau ditekan. .btn-link bawaan Bootstrap mengubah warna teks,
   menambah garis bawah dan ring fokus, jadi semua state itu dikunci ke tampilan
   yang sama seperti keadaan diam. */
.tpl-akordeon,
.tpl-akordeon:hover,
.tpl-akordeon:focus,
.tpl-akordeon:focus-visible,
.tpl-akordeon:active {
    color: #212529 !important;
    background-color: transparent !important;
    border-color: transparent !important;
    box-shadow: none !important;
    text-decoration: none !important;
}

@media (max-width: 991.98px) {
    .tpl-kepala {
        flex-direction: column;
        align-items: flex-start !important;
        gap: 0.15rem;
    }

    .tpl-buntut {
        flex-direction: column;
        align-items: stretch !important;
        gap: 0.5rem;
    }

    .tpl-buntut .btn {
        width: 100%;
    }

    /* Kepala kartu dipadatkan supaya kolom "Judul Template" naik lagi. Padding
       bawaan .card-header (1rem) dan .card-body (1.25rem) di style.css sama-sama
       !important, jadi di sini juga harus !important. */
    .tpl-kepala {
        padding-top: 0.5rem !important;
        padding-bottom: 0.5rem !important;
    }

    .tpl-kartu > .card-body {
        padding-top: 0.75rem !important;
    }
}
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?= $this->include('admin/navbar') ?>

<div class="container-xxl py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h3 mb-1">Template Pesan WhatsApp CRM</h1>
        </div>
        <div class="d-flex align-items-center gap-2">
            <?= $this->include('admin/crm/tombol_wa') ?>
        </div>
    </div>


    <?php /* penjelasan variabel dinamis diringkas jadi accordion supaya daftar
           template tidak terdorong ke bawah; isinya tetap sama persis */ ?>
    <div class="alert alert-info border-0 shadow-sm mb-4 py-2">
        <button type="button" class="btn btn-link text-dark text-decoration-none w-100 d-flex align-items-center gap-2 p-0 tpl-akordeon"
                data-bs-toggle="collapse" data-bs-target="#rincianVariabel" aria-expanded="false" aria-controls="rincianVariabel">
            <i class="fal fa-info-circle"></i>
            <strong class="flex-grow-1 text-start">Variabel Dinamis</strong>
            <i class="fal fa-chevron-down tpl-panah"></i>
        </button>
        <div class="collapse" id="rincianVariabel">
            <div class="pt-2">
                Anda dapat menggunakan tag berikut di dalam teks template:
                <code>{nama}</code>, <code>{invoice}</code>, <code>{total}</code>, <code>{sisa}</code>, <code>{link_invoice}</code>, <code>{kurir}</code>, <code>{resi}</code>, <code>{total_order}</code>. Tag ini akan diganti dengan data pesanan secara otomatis saat mengirim pesan.
                <br>
                <small>Di Live Chat dan profil customer, datanya diambil dari nota terakhir yang belum lunas. Tag yang belum ada isinya (misalnya <code>{resi}</code> sebelum pesanan dikirim) sengaja tetap terlihat agar pesannya diedit lebih dulu, bukan terkirim dalam keadaan kosong.</small>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <?php foreach ($templates as $tpl) : ?>
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm h-100 tpl-kartu">
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center tpl-kepala">
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
                            <div class="d-flex justify-content-between align-items-center tpl-buntut">
                                <small class="text-muted">
                                    Diperbarui: <?= date('d M Y H:i', (int) $tpl['updated_at']) ?>
                                </small>
                                <button type="submit" class="btn btn-dark btn-sm fw-bold text-nowrap">
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
