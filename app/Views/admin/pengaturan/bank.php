<?= $this->extend('template/main') ?>

<?= $this->section('content') ?>
<?= $this->include('admin/navbar') ?>

<div class="container-xxl">
    <h1 class="h3 mt-5">Pengaturan Bank</h1>
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb p-0">
            <li class="breadcrumb-item">
                <?= anchor('', 'Dasbor') ?>
            </li>
            <li class="breadcrumb-item">
                <?= anchor('admin/settings', 'Pengaturan') ?>
            </li>
            <li class="breadcrumb-item active" aria-current="page">Bank & EDC</li>
        </ol>
    </nav>
</div>
<div class="container">
    <div class="row">
        <div class="col-sm-8 mb-3">
            <div class="card">
                <div class="card-body">
                    <table class="table">
                        <thead>
                            <tr>
                                <th style="width: 110px">Logo</th>
                                <th colspan="2">Rekening</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            foreach ($banks as $bank) { ?>
                                <tr>
                                    <td>
                                        <?= logo_bank($bank->nama_bank) ?>
                                    </td>
                                    <td>
                                        <h5 class="mb-0"><?= esc($bank->rekening) ?></h5>
                                        <div class="text-muted"><?= esc($bank->atas_nama) ?></div>
                                    </td>
                                    <td class="text-end">
                                        <button aria-label="Tombol Sunting bank" data-banks="<?= esc(json_encode($bank)) ?>" data-bs-toggle="modal" data-bs-target="#modalEditBank" class="btn btn-outline-secondary">
                                            <i class="fal fa-pencil"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-sm-4 mb-3">
            <div class="card sticky-top" style="top: 60px">
                <div class="card-header">
                    <ul class="nav nav-tabs card-header-tabs">
                        <li class="nav-item">
                            <span class="nav-link active" aria-current="true">Tambah Bank / EDC</span>
                        </li>
                    </ul>
                </div>
                <div class="card-body">
                    <?= form_open('admin/settings/bank/save') ?>
                    <div class="mb-3">
                        <?= form_label('Tipe Akun', 'nama_bank', ['class' => 'form-label']) ?>
                        <?= form_dropdown([
                            'class'   => 'form-select',
                            'name'    => 'nama_bank',
                            'options' => [
                                ''        => 'Pilih Tipe Akun',
                                'bca'     => 'BCA',
                                'bni'     => 'BNI',
                                'bri'     => 'BRI',
                                'edc'     => 'EDC',
                                'mandiri' => 'Mandiri',
                            ],
                            'required' => '',
                            'value'    => set_value('nama_bank'),
                        ]) ?>
                    </div>
                    <div class="mb-3">
                        <?php
                        $class_nomorRekening = ['form-control'];
                        if ($validation->hasError('nomor_rekening')) {
                            $class_nomorRekening[] = 'is-invalid';
                        }
                        ?>
                        <?= form_label('Nomor Rekening', 'nomor_rekening', ['class' => 'form-label']) ?>
                        <?= form_input([
                            'class'       => implode(' ', $class_nomorRekening),
                            'id'          => 'nomor_rekening',
                            'name'        => 'nomor_rekening',
                            'placeholder' => '123 456 789',
                            'required'    => '',
                            'value'       => set_value('nomor_rekening'),
                        ]) ?>
                        <?php if ($validation->hasError('nomor_rekening')) { ?>
                            <div class="invalid-feedback">
                                <?= $validation->getError('nomor_rekening') ?>
                            </div>
                        <?php } ?>
                    </div>
                    <div class="mb-3">
                        <?php
                        $class_atasNama = ['form-control'];
                        if ($validation->hasError('atas_nama')) {
                            $class_atasNama[] = 'is-invalid';
                        }
                        ?>
                        <?= form_label('Atas Nama', 'atas_nama', ['class' => 'form-label']) ?>
                        <?= form_input([
                            'class'       => implode(' ', $class_atasNama),
                            'id'          => 'atas_nama',
                            'name'        => 'atas_nama',
                            'placeholder' => 'rekening atas nama',
                            'required'    => '',
                            'value'       => set_value('atas_nama'),
                        ]) ?>
                        <?php if ($validation->hasError('atas_nama')) { ?>
                            <div class="invalid-feedback">
                                <?= $validation->getError('atas_nama') ?>
                            </div>
                        <?php } ?>
                    </div>
                    <div class="mb-3">
                        <button class="btn btn-block btn-primary" type="submit"><i class="fal fa-save"></i> Tambahkan</button>
                    </div>
                    <?= form_close() ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('modal') ?>
<!-- Modal edit -->
<div class="modal fade" id="modalEditBank" tabindex="-1" aria-labelledby="modalEditBankLabel" aria-hidden="true">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <?= form_open('admin/settings/bank/update', ['class' => 'modal-content'], ['id_bank' => set_value('id_bank')]) ?>
        <div class="modal-header">
            <h5 class="modal-title" id="modalEditBankLabel">Sunting</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">

            <div class="mb-3">
                <?= form_label('Tipe Akun', 'sunting_nama_bank', ['class' => 'form-label']) ?>
                <?= form_dropdown([
                    'class'   => 'form-select',
                    'name'    => 'sunting_nama_bank',
                    'options' => [
                        ''        => 'Pilih Tipe Akun',
                        'bca'     => 'BCA',
                        'bni'     => 'BNI',
                        'bri'     => 'BRI',
                        'edc'     => 'EDC',
                        'mandiri' => 'Mandiri',
                    ],
                    'required' => '',
                    'selected' => set_value('sunting_nama_bank'),
                ]) ?>
            </div>
            <div class="mb-3">
                <?php
                $class_nomorRekening = ['form-control'];
                if ($validation->hasError('sunting_nomor_rekening')) {
                    $class_nomorRekening[] = 'is-invalid';
                }
                ?>
                <?= form_label('Nomor Rekening', 'sunting_nomor_rekening', ['class' => 'form-label']) ?>
                <?= form_input([
                    'class'       => implode(' ', $class_nomorRekening),
                    'id'          => 'sunting_nomor_rekening',
                    'name'        => 'sunting_nomor_rekening',
                    'placeholder' => '123 456 789',
                    'required'    => '',
                    'value'       => set_value('sunting_nomor_rekening'),
                ]) ?>
                <?php if ($validation->hasError('sunting_nomor_rekening')) { ?>
                    <div class="invalid-feedback">
                        <?= $validation->getError('sunting_nomor_rekening') ?>
                    </div>
                <?php } ?>
            </div>
            <div class="mb-3">
                <?php
                $class_atasNama = ['form-control'];
                if ($validation->hasError('sunting_atas_nama')) {
                    $class_atasNama[] = 'is-invalid';
                }
                ?>
                <?= form_label('Atas Nama', 'sunting_atas_nama', ['class' => 'form-label']) ?>
                <?= form_input([
                    'class'       => implode(' ', $class_atasNama),
                    'id'          => 'sunting_atas_nama',
                    'name'        => 'sunting_atas_nama',
                    'placeholder' => 'rekening atas nama',
                    'required'    => '',
                    'value'       => set_value('sunting_atas_nama'),
                ]) ?>
                <?php if ($validation->hasError('sunting_atas_nama')) { ?>
                    <div class="invalid-feedback">
                        <?= $validation->getError('sunting_atas_nama') ?>
                    </div>
                <?php } ?>
            </div>
        </div>

        <div class="modal-footer flex-nowrap p-0">
            <button type="button" data-bs-dismiss="modal" class="btn btn-lg btn-link fs-6 text-decoration-none col m-0 rounded-0">Batal</button>
            <button type="submit" class="btn btn-lg btn-link fs-6 text-decoration-none col m-0 rounded-0 border-start">Simpan</button>
        </div>
        <?= form_close() ?>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('js') ?>
<script defer src="https://kit.fontawesome.com/22a9d93fa2.js" data-auto-replace-svg="nest" crossorigin="anonymous"></script>
<?php

$current_user_id      = session()->get('id');
$link_api_juragan     = site_url('api/juragan/by_user/');
$link_invoice         = site_url('admin/invoices/lihat/');
$link_api_notif       = site_url('api/notifikasi/');
$showModalEditOnError = (($validation->hasError('sunting_nama_bank') || $validation->hasError('sunting_nomor_rekening') || $validation->hasError('sunting_atas_nama')) ? 'true' : 'false');

$js = <<< JS
    $(function() {
    	'use strict';

        const suntingErr = {$showModalEditOnError};
    	// sidebar

    	var juragan = document.getElementById('juragan');
        if(juragan) {
            juragan.addEventListener('show.bs.offcanvas', function () {
                var id = {$current_user_id};
                $('#listLi').empty();
                $.getJSON('{$link_api_juragan}', { id: id }, function(b){
                    var a=[];
                    // akun yang cuma pegang satu toko tidak perlu memilih
                    var ada = 0;
                    $.each(b[id].juragan, function() { ada++; });
                    if (ada > 1) {
                        a.push('<a class="list-group-item text-light list-group-item-action" href="{$link_invoice}semua"><i class="fal fa-user-circle"></i> Semua Juragan</a>');
                    }

                    $.each(b[id].juragan,function(c,b){
                        a.push('<a href="{$link_invoice}'+b.slug+'" class="list-group-item text-light list-group-item-action"><i class="fal fa-user-circle"></i> '+b.nama+'</a>');
                    }),

                    $(a.join('')).appendTo('#listLi');
                });
            });
        }

        // notifikasi

    	function getNotif(page = 1, dibaca = 0){
    		var id = {$current_user_id};
    		$.getJSON('{$link_api_notif}' + 'get', { id: id, page: page, dibaca: dibaca }, function(b){
    			var a=[],
    				data = b.results;

    			if (b.count > 0) {
    				$('.btnSettingNotif').show();
    				a.push('');

    				$.each(data,function(c,b){
    					a.push(`<div class="list-group-item list-group-item-action">
    						<div class="d-flex">
    							<div class="me-auto">
    								<div class="text-muted small">`+b.created_at +`</div>
    								<a class="d-block text-decoration-none" href="{$link_invoice}semua/semua?cari[kolom]=faktur&cari[q]=`+b.invoice+`">`+ b.notif+`</a>
    							</div>
    							<div class="d-flex justify-content-right flex-column actionNotif">
    								<button data-id="`+b.id+`" class="markAs border-0 bg-transparent text-primary small" type="button"><i class="fal fa-circle"></i></button>
    							</div>
    						</div>
    					</div>`);
    				}),
    				$(a.join('')).appendTo('#notifDisini');
    				var next = '';

    				if (b.next) {
    					$('<button type="button" class="list-group-item list-group-item-action text-center lanjutNotif" data-page="'+ (b.page+1) +'">lanjut ...</button>').appendTo('#notifDisini');
    				}
    			}
    			else {
    				$('.btnSettingNotif').hide();

    				$('<div class="d-flex justify-content-center align-items-center" style="height: 90vh"><i class="fal fa-bell-slash fa-5x"></i></div>').appendTo('#notifDisini');
    			}
    		});
    	}

    	$(document).on('click', '.lanjutNotif',function(){
    		getNotif($(this).data('page'));
    		$(this).attr('disabled', true).html(`<div class="text-center">
    			<div class="spinner-border" role="status">
    				<span class="visually-hidden">Loading...</span>
    			</div>
    		</div>`).remove();
    	});

    	var notifikasi = document.getElementById('notifikasi');
        if(notifikasi) {
            notifikasi.addEventListener('show.bs.offcanvas', function () {

                var id = {$current_user_id};
                $('#notifDisini').empty(),
                getNotif();
            });
        }

    	counter_notif();

    	setInterval(function(){
    		counter_notif();
    	}, 10000);

    	function counter_notif() {
    		var id = {$current_user_id};
    		$.getJSON('{$link_api_notif}' + 'get', { id: id }, function(b){
    			$('.counter').text(b.count_text);
    		});
    	}

    	$(document).on('click', '.actionNotif .markAs', function() { // markAs
    		var action = '';

    		if ($(this).hasClass( "text-primary" )) {
    			$(this).removeClass('text-primary').addClass('text-muted');
    			action = 'sudahBaca';
    		}
    		else {
    			$(this).removeClass('text-muted').addClass('text-primary');
    			action = 'belumBaca';
    		}

    		$.post( '{$link_api_notif}' + 'mark', { action: action, id: $(this).data('id') });
    	});

    	$('.tandaiSemuaTerbaca').on('click',function(){
    		var id = {$current_user_id};

    		$.post( '{$link_api_notif}' + 'mark_all', { id: id }, function() {
    			//
    			$('.actionNotif .markAs').removeClass('text-primary').addClass('text-muted');
    		});
    	});

        // modal #modalEditBank
        const modalEditBank = document.getElementById('modalEditBank');
        modalEditBank.addEventListener('show.bs.modal', event => {
            // Button that triggered the modal
            const button = event.relatedTarget;
            // Extract info from data-bs-* attributes
            const recipient = button.getAttribute('data-banks');
            // If necessary, you could initiate an AJAX request here
            // and then do the updating in a callback.

            // parse JSON
            var data = JSON.parse(recipient);

            //
            // Update the modal's content.
            // const modalTitle = modalEditBank.querySelector('.modal-title');
            const id_bank = modalEditBank.querySelector('[name="id_bank"]');
            const tipe_bank = modalEditBank.querySelector('[name="sunting_nama_bank"]');
            const nomor_rekening = modalEditBank.querySelector('[name="sunting_nomor_rekening"]');
            const atas_nama = modalEditBank.querySelector('[name="sunting_atas_nama"]');

            id_bank.value = data.id_bank;
            tipe_bank.value = data.nama_bank;
            nomor_rekening.value = data.rekening;
            atas_nama.value = data.atas_nama;
        });

        const modalSunting = document.getElementById('modalEditBank');

        if(suntingErr) {
            const suntingModal = new bootstrap.Modal(modalSunting);
            suntingModal.toggle();
        }

        modalSunting.addEventListener('hidden.bs.modal', event => {
            // remove element with class.invalid-feedback
            const errorFeedback = modalSunting.querySelector('.invalid-feedback');
            errorFeedback.parentNode.removeChild(errorFeedback);

            // remove classname .is-invalid from .form-control
            const formField = modalSunting.querySelector('.form-control');
            formField.classList.remove('is-invalid');

        });
    });
    JS;

$packer    = new Tholu\Packer\Packer($js, 'Normal', true, false, true);
$packed_js = $packer->pack();
echo '<script>' . $packed_js . '</script>';
?>
<?= $this->endSection() ?>
