<?php
$pager   = \Config\Services::pager();
$session = \Config\Services::session();
?>
<?= $this->extend('template/main') ?>

<?= $this->section('content') ?>
<?= $this->include('admin/navbar') ?>

<div class="container-xxl">

    <h1 class="h3 mt-5">Pengaturan Juragan</h1>

    <nav aria-label="breadcrumb">
        <ol class="breadcrumb p-0">
            <li class="breadcrumb-item"><?= anchor('', 'Dasbor') ?></li>
            <li class="breadcrumb-item"><?= anchor('admin/settings', 'Pengaturan') ?></li>
            <li class="breadcrumb-item active" aria-current="page">Juragan</li>
        </ol>
    </nav>
</div>
<div class="container">

    <div class="row">
        <div class="col-sm-8 mb-3">
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Juragan</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($juragan as $j) { ?>
                                    <?php
                                    $bank = model('JuraganModel')->getBank($j->id_juragan);
                                    ?>
                                    <tr data-arr="<?= esc(json_encode(compact('bank', 'j'))) ?>">
                                        <td class="d-flex align-items-center">
                                            <span class="lead me-auto"><?= esc($j->nama_juragan) ?></span>
                                            <span class="small">
                                                <span class="badge rounded-pill fw-light bg-dark"><?= count($bank) ?> akun rekening</span>
                                                <?= anchor('admin/invoices/lihat/' . $j->juragan, '<i class="fal fa-ballot-check"></i> Lihat Orderan', ['class' => 'btn btn-link btn-sm ms-2']) ?>
                                                <?= form_button([
                                                    'class'          => 'btn btn-link btn-sm ms-2',
                                                    'content'        => '<i class="fal fa-pencil"></i> Sunting',
                                                    'data-bs-target' => '#modalSuntingJuragan',
                                                    'data-bs-toggle' => 'modal',
                                                ]) ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-4 mb-3">
            <div class="card sticky-top" style="top: 60px">
                <div class="card-header">
                    <ul class="nav nav-tabs card-header-tabs">
                        <li class="nav-item">
                            <span class="nav-link active" aria-current="true">Tambah Juragan</span>
                        </li>
                    </ul>
                </div>
                <div class="card-body">
                    <?= form_open('admin/settings/save_juragan'); ?>
                    <div class="mb-3">
                        <?= form_label('Nama Juragan', 'nama_juragan', ['class' => 'form-label']); ?>
                        <?= form_input('nama_juragan', '', ['class' => 'form-control', 'id' => 'nama_juragan', 'required' => '', 'placeholder' => 'nama juragan']); ?>
                    </div>
                    <div class="mb-3">
                        <?= form_label('Rekening Bank / EDC', 'bank', ['class' => 'form-label']); ?>
                        <?php
                        foreach ($banks as $bank) {
                            $options[$bank->id_bank] = strtoupper($bank->nama_bank) . ' - ' . $bank->atas_nama;
                        }

                        echo form_multiselect('bank[]', $options, [], ['class' => 'form-select', 'required' => '']);
                        ?>
                        <div class="form-text">tekan CTRL untuk memilih lebih dari 1</div>
                    </div>
                    <hr>
                    <div class="mb-3">
                        <button class="btn btn-primary btn-block" type="submit"><i class="fal fa-save"></i> Tambahkan</button>
                    </div>
                    <?= form_close(); ?>

                </div>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('modal') ?>
<div class="modal fade" id="modalSuntingJuragan" data-backdrop="static" tabindex="-1" aria-labelledby="modalSuntingJuraganLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <?= form_open('admin/settings/juragan/update', ['id' => 'mf'], ['id' => '']); ?>
            <div class="modal-header">
                <h5 class="modal-title" id="modalSuntingJuraganLabel">Update Juragan</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">

                </button>
            </div>
            <div class="modal-body">

                <div class="mb-3">
                    <?= form_label('Nama Juragan', 'nama_juragan', ['class' => 'form-label']); ?>
                    <?= form_input('nama_juragan', '', ['class' => 'form-control', 'id' => 'nama_juragan', 'required' => '', 'placeholder' => 'nama juragan']); ?>
                </div>
                <div class="mb-3">
                    <?= form_label('Rekening Bank', 'bank', ['class' => 'form-label']); ?>
                    <?= form_multiselect([
                        'name'     => 'bank[]',
                        'options'  => $options,
                        'class'    => 'form-select',
                        'required' => true,
                        'id'       => 'multipleSelect',
                    ]) ?>
                    <div class="form-text">tekan CTRL untuk memilih lebih dari 1</div>
                </div>

            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-link text-decoration-none" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary"><i class="fal fa-save"></i> Simpan</button>
            </div>
            <?= form_close(); ?>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('js') ?>
<script defer src="https://kit.fontawesome.com/22a9d93fa2.js" data-auto-replace-svg="nest" crossorigin="anonymous"></script>
<?php

$current_user_id  = $session->get('id');
$link_api_juragan = site_url('api/juragan/by_user/');
$link_invoice     = site_url('admin/invoices/lihat/');
$link_api_notif   = site_url('api/notifikasi/');

$js = <<< JS
    $(function() {
    	'use strict';
    	// sidebar

    	var juragan = document.getElementById('juragan');
        if(juragan) {
            juragan.addEventListener('show.bs.offcanvas', function () {
                var id = {$current_user_id};
                $('#listLi').empty();
                $.getJSON('{$link_api_juragan}', { id: id }, function(b){
                    var a=[];
                    a.push('<a class="list-group-item text-light list-group-item-action" href="{$link_invoice}semua"><i class="fal fa-user-circle"></i> Semua Juragan</a>');

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

    	var modalSuntingJuragan=document.getElementById('modalSuntingJuragan');
    	modalSuntingJuragan.addEventListener('show.bs.modal',function(event){
    		const button = event.relatedTarget;
            const parent = button.closest('tr');
            const data = parent.dataset.arr;
            const dataParse = JSON.parse(data);
            const bank = dataParse.bank;
            const juragan = dataParse.j;

            var bankId = [];

            bank.forEach(function(el, index, array){
                bankId.push(el.id);
            });

    		var modalTitle = modalSuntingJuragan.querySelector('.modal-title');
    		var namaJuragan = modalSuntingJuragan.querySelector('#modalSuntingJuragan input#nama_juragan');
    		var juraganId = modalSuntingJuragan.querySelector('#modalSuntingJuragan input[name="id"]');

    		modalTitle.textContent='Perbarui ' + juragan.nama_juragan,
    		namaJuragan.value=juragan.nama_juragan,
            juraganId.value=juragan.id_juragan;

            $('#multipleSelect').val(bankId)
    	}),
    	modalSuntingJuragan.addEventListener('hide.bs.modal',function(a){
    		document.getElementById('mf').reset();
    	});
    });
    JS;

$packer    = new Tholu\Packer\Packer($js, 'Normal', true, false, true);
$packed_js = $packer->pack();
echo '<script>' . $packed_js . '</script>';
?>
<?= $this->endSection() ?>
