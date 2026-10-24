<?php
/* Tombol melayang "Kirim Masukan". Sengaja tidak ditampilkan untuk Superadmin:
   merekalah tujuan masuknya masukan, bukan yang mengirimnya (dibaca di
   admin/masukan). Berkas ini di-include oleh navbar Admin maupun navbar
   CS, jadi cukup satu pagar di sini untuk kedua layar. */
$kirimMasukan = ! in_array((string) session()->get('level'), ['superadmin'], true);
?>
<?php if ($kirimMasukan) : ?>
<div class="fixed-bottom">
    <div class="container-fluid d-flex justify-content-end px-2">
        <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#feedbackModal" title="Kirim Masukan" aria-label="Kirim Masukan">
            <i class="fal fa-headset fs-5"></i>
        </button>
    </div>
</div>

<!-- Modal -->
<div class="modal fade" id="feedbackModal" tabindex="-1" aria-labelledby="feedbackModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <?= form_open('kirim-masukan') ?>
            <div class="modal-header">
                <h5 class="modal-title" id="feedbackModalLabel">Kirim Masukan / Permintaan Fitur</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted mb-3">
                    Tulis kendala atau permintaan fitur di sini. Isinya langsung masuk ke kotak
                    masukan Superadmin beserta nama akun Anda.
                </p>

                <div class="mb-3">
                    <?= form_label('Detail', 'keterangan', ['class' => 'form-label']) ?>
                    <?= form_textarea([
                        'name'        => 'keterangan',
                        'class'       => 'form-control',
                        'id'          => 'keterangan',
                        'placeholder' => 'Ketikkan detail masukan / permintaan fitur',
                        'rows'        => '3',
                        'required'    => '',
                    ]) ?>
                </div>

            </div>
            <div class="modal-footer">
                <!-- <button type="button" class="btn btn-link" data-bs-dismiss="modal">Batal</button> -->
                <button type="submit" class="btn btn-primary">Kirim</button>
            </div>
            <?= form_close() ?>
        </div>
    </div>
</div>
<?php endif; ?>
