<div class="fixed-bottom">
    <div class="container-xxl d-flex flex-row-reverse">
        <button type="button" class="btn btn-danger btn-sm rounded-0 rounded-top" data-bs-toggle="modal" data-bs-target="#feedbackModal">Kirim Masukan</button>
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

                <div class="mb-3">
                    <?= form_label('Detail', 'keterangan', ['class' => 'form-label']) ?>
                    <?= form_textarea([
                        'name'        => 'keterangan',
                        'class'       => 'form-control',
                        'id'          => 'keterangan',
                        'placeholder' => 'Ketikkan detail masukan / permintaan fitur',
                        'rows'        => '3',
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
