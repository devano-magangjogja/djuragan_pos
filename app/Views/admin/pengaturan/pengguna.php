<?php
$pager   = \Config\Services::pager();
$session = \Config\Services::session();
?>
<?= $this->extend('template/main') ?>

<?= $this->section('content') ?>
<?= $this->include('admin/navbar') ?>

<div class="container-xxl">

    <h1 class="h3 mt-5">Pengaturan Pengguna</h1>

    <nav aria-label="breadcrumb">
        <ol class="breadcrumb p-0">
            <li class="breadcrumb-item"><?= anchor('', 'Dasbor') ?></li>
            <li class="breadcrumb-item"><?= anchor('admin/settings', 'Pengaturan') ?></li>
            <li class="breadcrumb-item active" aria-current="page">Pengguna</li>
        </ol>
    </nav>
</div>
<div class="container">

    <div class="row">
        <div class="col-sm-8 mb-3">
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Pengguna</th>
                                    <th>Level</th>
                                    <th colspan="2">Status</th>
                                </tr>
                            </thead>
                            <tbody id="listUsers"></tbody>
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
                            <span class="nav-link active" aria-current="true">Tambah Pengguna</span>
                        </li>
                    </ul>
                </div>
                <div class="card-body">

                    <?= form_open('admin/settings/save_pengguna'); ?>
                    <div class="mb-3 row gx-2 bg-warning rounded pb-1">
                        <div class="col-6">
                            <?= form_label('Username', 'username', ['class' => 'form-label']); ?>
                            <?= form_input('username', '', ['class' => 'form-control', 'id' => 'username', 'required' => '', 'placeholder' => 'username']); ?>
                        </div>
                        <div class="col-6">
                            <?= form_label('Password', 'password', ['class' => 'form-label']); ?>
                            <?= form_password('password', '', ['class' => 'form-control', 'id' => 'password', 'required' => '', 'placeholder' => 'password']); ?>
                        </div>
                    </div>
                    <div class="mb-3">
                        <?= form_label('Nama Lengkap', 'nama', ['class' => 'form-label']); ?>
                        <?= form_input('nama', '', ['class' => 'form-control', 'id' => 'nama', 'required' => '', 'placeholder' => 'nama lengkap pengguna']); ?>
                    </div>
                    <div class="mb-3">
                        <?= form_label('Email', 'email', ['class' => 'form-label']); ?>
                        <?= form_input('email', '', ['class' => 'form-control', 'id' => 'email', 'required' => '', 'placeholder' => 'alamat@email'], 'email'); ?>
                    </div>
                    <div class="mb-3 row gx-2">
                        <div class="col-6">
                            <?= form_label('Level', 'level', ['class' => 'form-label']); ?>
                            <?php
                            $options_level = [
                                'superadmin' => 'Superadmin',
                                'admin'      => 'Admin',
                                'cs'         => 'CS',
                                'viewer'     => 'Viewer',
                                'reseller'   => 'Reseller',
                            ];

                            echo form_dropdown('level', $options_level, 'cs', ['class' => 'form-select', 'id' => 'level', 'required' => '']);
                            ?>

                        </div>
                        <div class="col-6">
                            <?= form_label('Status', 'status', ['class' => 'form-label']); ?>
                            <?php
                            $options_status = [
                                'pending'  => 'Pending',
                                'inactive' => 'Tidak Aktif',
                                'active'   => 'Aktif',
                                'blocked'  => 'Blokir',
                            ];

                            echo form_dropdown('status', $options_status, 'active', ['class' => 'form-select', 'id' => 'status', 'required' => '']);
                            ?>
                        </div>
                    </div>
                    <div class="mb-3">
                        <?= form_label('Juragan', 'juragan', ['class' => 'form-label']); ?>
                        <?= /*
                        foreach ($juragans->getResult() as $juragan) {
                            $options_juragan[$juragan->id_juragan] = $juragan->nama_juragan;
                        }
                        */ form_multiselect('juragan[]', [], [], ['class' => 'form-select', 'id' => 'juragan']);
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
<div class="modal fade" id="modalSuntingPengguna" data-backdrop="static" tabindex="-1" aria-labelledby="modalSuntingPenggunaLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <?= form_open('admin/settings/update_pengguna', '', ['id' => '']); ?>
            <div class="modal-header">
                <h5 class="modal-title" id="modalSuntingPenggunaLabel">Update Pengguna</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">

                </button>
            </div>
            <div class="modal-body">

                <div class="mb-3 row gx-2 bg-warning rounded pb-1">
                    <div class="col-6">
                        <?= form_label('Username', 'username_', ['class' => 'form-label']); ?>
                        <?= form_input('username', '', ['class' => 'form-control', 'id' => 'username_', 'required' => '', 'placeholder' => 'username', 'disabled' => '']); ?>
                    </div>
                    <div class="col-6">
                        <?= form_label('Password', 'password_', ['class' => 'form-label']); ?>
                        <?= form_password('password', '', ['class' => 'form-control', 'id' => 'password_', 'placeholder' => 'password - isi jika diganti']); ?>
                    </div>
                </div>
                <div class="mb-3">
                    <?= form_label('Nama Lengkap', 'nama_', ['class' => 'form-label']); ?>
                    <?= form_input('nama', '', ['class' => 'form-control', 'id' => 'nama_', 'required' => '', 'placeholder' => 'nama lengkap pengguna']); ?>
                </div>
                <div class="mb-3">
                    <?= form_label('Email', 'email_', ['class' => 'form-label']); ?>
                    <?= form_input('email', '', ['class' => 'form-control', 'id' => 'email_', 'required' => '', 'placeholder' => 'alamat@email'], 'email'); ?>
                </div>
                <div class="mb-3 row gx-2">
                    <div class="col-6">
                        <?= form_label('Level', 'level_', ['class' => 'form-label']); ?>
                        <?php
                        $options_level = [
                            'superadmin' => 'Superadmin',
                            'admin'      => 'Admin',
                            'cs'         => 'CS',
                            'viewer'     => 'Viewer',
                            'reseller'   => 'Reseller',
                        ];

                        echo form_dropdown('level', $options_level, 'cs', ['class' => 'form-select', 'id' => 'level_', 'required' => '']);
                        ?>

                    </div>
                    <div class="col-6">
                        <?= form_label('Status', 'status_', ['class' => 'form-label']); ?>
                        <?php
                        $options_status = [
                            'pending'  => 'Pending',
                            'inactive' => 'Tidak Aktif',
                            'active'   => 'Aktif',
                            'blocked'  => 'Blokir',
                        ];

                        echo form_dropdown('status', $options_status, 'active', ['class' => 'form-select', 'id' => 'status_', 'required' => '']);
                        ?>
                    </div>
                </div>
                <div class="mb-3">
                    <?= form_label('Juragan', 'juragan_', ['class' => 'form-label']); ?>
                    <?= /*
                    foreach ($juragans->getResult() as $juragan) {
                        $options_juragan[$juragan->id_juragan] = $juragan->nama_juragan;
                    }
                    */ form_multiselect('juragan[]', [], [], ['class' => 'form-select', 'id' => 'juragan_']);
                    ?>
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
$current_user_id       = $session->get('id');
$link_api_juragan      = site_url('api/juragan/by_user/');
$link_api_juragan_all  = site_url('api/juragan/all/');
$link_api_pengguna_all = site_url('api/pengguna/all/');
$link_api_relasi       = site_url('api/juragan/by_user');
$link_invoice          = site_url('admin/invoices/lihat/');
$link_api_notif        = site_url('api/notifikasi/');

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

    	$.getJSON('{$link_api_juragan_all}', function(b){
    		var apnd = '';
    		for (var i in b) {
    			apnd += "<option value = '" + b[i].id + " '>" + b[i].nama + " </option>";
    		}
    		$('[name="juragan[]"]').empty().append(apnd);
    	});

    	$.getJSON('{$link_api_pengguna_all}', function(b){
    		var apnd = '';

    		for (let i = 0; i < b.length; i++) {
    			apnd += `<tr>
    				<td>
    					<div>
    						<div class="lead">`+b[i].nama+`<span class="ms-2 badge rounded-pill bg-secondary fw-normal">`+b[i].username+`</span></div>
    						<div>`+b[i].email+`</div>
    					</div>
    				</td>
    				<td>`+b[i].level+`</td>
    				<td>`+b[i].status+`</td>
    				<td class="text-end">
    					<!-- Example split danger button -->
    					<div class="btn-group">
    						<button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#modalSuntingPengguna" data-id="`+b[i].id+`" data-nama="`+b[i].nama+`" data-email="`+b[i].email+`" data-username="`+b[i].username+`" data-level="`+b[i].level+`" data-status="`+b[i].status+`">Sunting</button>
    						<button type="button" class="btn btn-outline-secondary btn-sm dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown" aria-expanded="false">
    							<span class="visually-hidden">Toggle Dropdown</span>
    						</button>
    						<ul class="dropdown-menu">
    							<li><button class="dropdown-item" href="#">Hapus</button></li>
    						</ul>
    					</div>
    				</td>
    			</tr>`;
    		}
    		$('#listUsers').empty().append(apnd);
    	});

    	var mP=document.getElementById('modalSuntingPengguna');
    	mP.addEventListener('show.bs.modal',function(o){
    		var a=o.relatedTarget;
    		var b=a.getAttribute('data-id');
    		var c=a.getAttribute('data-nama');
    		var d=a.getAttribute('data-username');
    		var e=a.getAttribute('data-email');
    		var f=a.getAttribute('data-level');
    		var g=a.getAttribute('data-status');
    		var h=mP.querySelector('.modal-title');
    		var i=mP.querySelector('#modalSuntingPengguna input[name="id"]');
    		var j=mP.querySelector('#modalSuntingPengguna input[name="nama"]');
    		var k=mP.querySelector('#modalSuntingPengguna input[name="username"]');
    		var l=mP.querySelector('#modalSuntingPengguna input[name="email"]');
    		var m=mP.querySelector('#modalSuntingPengguna select[name="level"]');
    		var n=mP.querySelector('#modalSuntingPengguna select[name="status"]');
    		h.textContent='Perbarui '+c,
    		i.value=b,
    		j.value=c,
    		k.value=d,
    		l.value=e,
    		m.value=f,
    		n.value=g,

    		$.ajax({
    			method:'GET',
    			url:'{$link_api_relasi}',
    			data:{id:b}
    		}).done(function(a){
    			var juragans = a[b].juragan;
    			var arr_lama = [];
    			var opt = [];

    			// $(document).find('option[value='+juragans[x].id+']');
    			var olds = $('select#juragan_ option');
    			// console.log(olds);

    			for (const x in olds) {
    				if (olds[x].value) arr_lama.push({
    					id: parseInt(olds[x].value),
    					nama: olds[x].text
    				});
    			}

    			var arr_baru = [];
    			for (const x in juragans) {
    				arr_baru.push({
    					id: juragans[x].id,
    					nama: juragans[x].nama,
    					selected: true
    				});
    			}
    			// console.log(selected);
    			let new_juragans = Object.assign(arr_lama,arr_baru);
    			// console.log(new_juragans);
    			// $('select#juragan_ option').val(selected);

    			for (let i = 0; i < new_juragans.length; i++) {
    				let _option = new_juragans[i];
    				let selected = '';
    				if (_option.selected) {
    					selected = 'selected="selected"';
    				}
    				opt.push(`
    				<option value="`+_option.id+`" `+selected+`>`+_option.nama+`</option>
    				`);
    			}

    			$('select#juragan_').empty();
    			$(opt.join('')).appendTo('select#juragan_');
    		});
    	});
    });
    JS;

$packer    = new Tholu\Packer\Packer($js, 'Normal', true, false, true);
$packed_js = $packer->pack();
echo '<script>' . $packed_js . '</script>';
?>
<?= $this->endSection() ?>
