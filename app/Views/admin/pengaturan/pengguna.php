<?php
$pager   = \Config\Services::pager();
$session = \Config\Services::session();

// isian + error datang dari flashdata controller: ['kode' => 'pesan', ...]
// kegagalan dari modal sunting dikenali dari kolom id yang ikut terkirim;
// galatnya jangan ditempel ke form tambah supaya tidak menyesatkan
$isian        = $isian ?? [];
$dari_sunting = ($isian['id'] ?? '') !== '';
$err          = static fn (string $kolom): ?string => ! $dari_sunting && isset($errors[$kolom]) ? (string) $errors[$kolom] : null;
$isi          = static fn (string $kolom, string $awalan = ''): string => (string) ($isian[$kolom] ?? $awalan);

// Popup dibuka lagi lewat JavaScript, jadi penanda dan isian sebelumnya dilewat
// sebagai atribut data di HTML, bukan disisipkan ke dalam string JS: skrip halaman
// dikemas Packer, jadi nilai PHP di dalamnya sulit dipastikan.
$buka_tambah = (! $dari_sunting && $errors !== []) ? '1' : '0';
$juragan_isi = json_encode(array_values(array_map('intval', (array) ($isian['juragan'] ?? []))));
$per_halaman = (int) config('Pager')->perPage;
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

    <?php if ($sukses !== null) { ?>
        <div class="alert alert-success py-2"><?= esc($sukses) ?></div>
    <?php } ?>
    <?php if ($gagal !== null) { ?>
        <div class="alert alert-danger py-2"><?= esc($gagal) ?></div>
    <?php } ?>
    <?php if ($dari_sunting && $errors !== []) { ?>
        <div class="alert alert-danger py-2">
            <strong class="d-block">Perubahan belum tersimpan.</strong>
            <?php foreach ($errors as $pesan) { ?>
                <div class="small"><?= esc($pesan) ?></div>
            <?php } ?>
        </div>
    <?php } ?>

    <div class="card mb-5">
        <div class="card-header d-flex flex-wrap align-items-center gap-2">
            <div class="input-group input-group-sm" style="width: 18rem">
                <span class="input-group-text"><i class="fal fa-search"></i></span>
                <input type="search" class="form-control form-control-sm" id="cariPengguna" placeholder="nama, username, email, level" autocomplete="off">
            </div>
            <span class="text-muted small ms-auto" id="jumlahPengguna">memuat ...</span>
            <button type="button" class="btn btn-sm btn-dark" data-bs-toggle="modal" data-bs-target="#modalTambahPengguna">
                <i class="fal fa-user-plus"></i> Tambah Pengguna
            </button>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th scope="col">Pengguna</th>
                            <th scope="col" style="width: 9rem">Level</th>
                            <th scope="col" style="width: 9rem">Status</th>
                            <th scope="col" class="text-end" style="width: 10rem">Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="listUsers"></tbody>
                </table>
            </div>
            <div id="pagPengguna" data-per-halaman="<?= $per_halaman ?>"></div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('modal') ?>
<div class="modal fade" id="modalTambahPengguna" data-bs-backdrop="static" data-buka="<?= $buka_tambah ?>" tabindex="-1" aria-labelledby="modalTambahPenggunaLabel" aria-hidden="true">
    <!-- kelas pembungkus dialog dipasang pada elemen formulirnya sendiri, bukan pada div
         di atasnya: baru dengan begitu badan form yang menggulir sementara judul dan
         tombol tetap terlihat -->
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <?= form_open('admin/settings/save_pengguna', ['class' => 'modal-content']); ?>
            <div class="modal-header">
                <h5 class="modal-title" id="modalTambahPenggunaLabel"><i class="fal fa-user-plus"></i> Tambah Pengguna</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <h6 class="text-uppercase small text-muted mb-3">Akun</h6>
                <div class="mb-3 row gx-2">
                    <div class="col-6">
                        <?= form_label('Username', 'username', ['class' => 'form-label']); ?>
                        <?= form_input([
                            'class'       => 'form-control' . ($err('username') ? ' is-invalid' : ''),
                            'name'        => 'username',
                            'id'          => 'username',
                            'required'    => '',
                            'placeholder' => 'username',
                            'value'       => $isi('username'),
                        ]); ?>
                        <?php if ($err('username')) { ?>
                            <div class="invalid-feedback d-block"><?= esc($err('username')) ?></div>
                        <?php } ?>
                    </div>
                    <div class="col-6">
                        <?= form_label('Password', 'password', ['class' => 'form-label']); ?>
                        <div class="input-group">
                            <?= form_password([
                                'class'       => 'form-control' . ($err('password') ? ' is-invalid' : ''),
                                'name'        => 'password',
                                'id'          => 'password',
                                'required'    => '',
                                'placeholder' => 'minimal 6 karakter',
                            ]); ?>
                            <button class="btn btn-outline-secondary" type="button" data-lihat="password" aria-label="Tampilkan atau sembunyikan kata sandi"><i class="fal fa-eye"></i></button>
                        </div>
                        <?php if ($err('password')) { ?>
                            <div class="invalid-feedback d-block"><?= esc($err('password')) ?></div>
                        <?php } ?>
                    </div>
                </div>
                <div class="mb-3">
                    <?= form_label('Nama Lengkap', 'nama', ['class' => 'form-label']); ?>
                    <?= form_input([
                        'class'       => 'form-control' . ($err('nama') ? ' is-invalid' : ''),
                        'name'        => 'nama',
                        'id'          => 'nama',
                        'required'    => '',
                        'placeholder' => 'nama lengkap pengguna',
                        'value'       => $isi('nama'),
                    ]); ?>
                    <?php if ($err('nama')) { ?>
                        <div class="invalid-feedback d-block"><?= esc($err('nama')) ?></div>
                    <?php } ?>
                </div>
                <div class="mb-3">
                    <?= form_label('Email', 'email', ['class' => 'form-label']); ?>
                    <?= form_input([
                        'class'       => 'form-control' . ($err('email') ? ' is-invalid' : ''),
                        'type'        => 'email',
                        'name'        => 'email',
                        'id'          => 'email',
                        'required'    => '',
                        'placeholder' => 'alamat@email',
                        'value'       => $isi('email'),
                    ]); ?>
                    <?php if ($err('email')) { ?>
                        <div class="invalid-feedback d-block"><?= esc($err('email')) ?></div>
                    <?php } ?>
                    <div class="form-text">Surel dipakai untuk tautan reset kata sandi.</div>
                </div>
                <hr class="my-4">
                <h6 class="text-uppercase small text-muted mb-3">Akses</h6>
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

                        echo form_dropdown('level', $options_level, $isi('level', 'cs'), ['class' => 'form-select' . ($err('level') ? ' is-invalid' : ''), 'id' => 'level', 'required' => '']);
                        ?>
                        <?php if ($err('level')) { ?>
                            <div class="invalid-feedback d-block"><?= esc($err('level')) ?></div>
                        <?php } ?>

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

                        echo form_dropdown('status', $options_status, $isi('status', 'active'), ['class' => 'form-select' . ($err('status') ? ' is-invalid' : ''), 'id' => 'status', 'required' => '']);
                        ?>
                        <?php if ($err('status')) { ?>
                            <div class="invalid-feedback d-block"><?= esc($err('status')) ?></div>
                        <?php } ?>
                    </div>
                </div>
                <div class="mb-3">
                    <?= form_label('Juragan', 'pilihJuragan', ['class' => 'form-label']); ?>
                    <div class="gulir-pilihan border rounded-3 p-2 d-flex flex-wrap gap-2<?= $err('juragan') ? ' border-danger' : '' ?>" id="pilihJuragan" data-juragan="<?= $juragan_isi ?>">
                        <div class="text-muted small p-1">memuat daftar juragan ...</div>
                    </div>
                    <?php if ($err('juragan')) { ?>
                        <div class="invalid-feedback d-block"><?= esc($err('juragan')) ?></div>
                    <?php } ?>
                    <div class="form-text">klik untuk memilih, boleh lebih dari satu.</div>
                    <div class="form-text text-danger d-none" data-hint-cs>Level CS wajib punya minimal satu juragan.</div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-link text-decoration-none" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary"><i class="fal fa-save"></i> Tambahkan</button>
            </div>
        <?= form_close(); ?>
    </div>
</div>

<div class="modal fade" id="modalSuntingPengguna" data-backdrop="static" tabindex="-1" aria-labelledby="modalSuntingPenggunaLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <?= form_open('admin/settings/update_pengguna', ['class' => 'modal-content'], ['id' => '']); ?>
            <div class="modal-header">
                <h5 class="modal-title" id="modalSuntingPenggunaLabel">Update Pengguna</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">

                </button>
            </div>
            <div class="modal-body">
                <h6 class="text-uppercase small text-muted mb-3">Akun</h6>

                <div class="mb-3 row gx-2 bg-warning rounded pb-1">
                    <div class="col-6">
                        <?= form_label('Username', 'username_', ['class' => 'form-label']); ?>
                        <?= form_input('username', '', ['class' => 'form-control', 'id' => 'username_', 'required' => '', 'placeholder' => 'username', 'disabled' => '']); ?>
                    </div>
                    <div class="col-6">
                        <?= form_label('Password', 'password_', ['class' => 'form-label']); ?>
                        <div class="input-group">
                            <?= form_password('password', '', ['class' => 'form-control', 'id' => 'password_', 'placeholder' => 'password - isi jika diganti']); ?>
                            <button class="btn btn-outline-secondary" type="button" data-lihat="password_" aria-label="Tampilkan atau sembunyikan kata sandi"><i class="fal fa-eye"></i></button>
                        </div>
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
                <hr class="my-4">
                <h6 class="text-uppercase small text-muted mb-3">Akses</h6>
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
                    <?= form_label('Juragan', 'pilihJuraganSunting', ['class' => 'form-label']); ?>
                    <div class="gulir-pilihan border rounded-3 p-2 d-flex flex-wrap gap-2" id="pilihJuraganSunting">
                        <div class="text-muted small p-1">memuat daftar juragan ...</div>
                    </div>
                    <div class="form-text">klik untuk memilih, boleh lebih dari satu.</div>
                    <div class="form-text text-danger d-none" data-hint-cs>Level CS wajib punya minimal satu juragan.</div>
                </div>

            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-link text-decoration-none" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary"><i class="fal fa-save"></i> Simpan</button>
            </div>
        <?= form_close(); ?>
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

    	// daftar penuh sudah dikirim sekali, jadi pencarian dan pagination cukup di sini
    	var semuaPengguna = [];
    	var kunciCari     = '';
    	var halaman       = 1;
    	var wadahPag      = document.getElementById('pagPengguna');
    	var perHalaman    = parseInt(wadahPag.getAttribute('data-per-halaman'), 10);

    	var lokaLevel = {
    		superadmin: 'bg-dark',
    		admin: 'bg-primary',
    		cs: 'bg-secondary',
    		viewer: 'bg-light text-dark border',
    		reseller: 'bg-light text-dark border'
    	};
    	var lokaStatus = {
    		active: 'bg-success',
    		pending: 'bg-warning text-dark',
    		inactive: 'bg-light text-dark border',
    		blocked: 'bg-danger'
    	};

    	function aman(teks) {
    		var peta = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' };

    		return ('' + teks).replace(/[&<>"']/g, function (c) { return peta[c]; });
    	}

    	var semuaJuragan = [];

    	// api juragan menjawab sebagai objek yang dipetakan dari id, bukan daftar,
    	// jadi diratakan dulu supaya bisa dihitung dan diurutkan seperti array
    	function keDaftar(bahan) {
    			var daftar = [];

    			for (var i in bahan) {
    					if (bahan[i] && bahan[i].id) { daftar.push(bahan[i]); }
    			}

    			return daftar;
    	}

    	function punyaId(daftar, id) {
    			for (var i = 0; i < daftar.length; i++) {
    					if ('' + daftar[i].id === '' + id) { return true; }
    			}

    			return false;
    	}

    	// satu pil = satu <input name="juragan[]">: jalur simpan controller tidak berubah,
    	// dan kelas btn-check dari Bootstrap yang mewarnai pilnya jadi tidak perlu tombol CTRL
    	function pilJuragan(item, terpilih) {
    			var id = 'pil-juragan-' + item.id;

    			return '<input class="btn-check" type="checkbox" name="juragan[]" value="' + aman(item.id) + '"'
    				+ ' id="' + id + '" autocomplete="off"' + (terpilih ? ' checked' : '') + '>'
    				+ '<label class="btn btn-sm rounded-pill btn-outline-secondary" for="' + id + '">' + aman(item.nama) + '</label>';
    	}

    	function gambarJuragan(wadah, punya) {
    			var daftar = semuaJuragan.slice();

    			// juragan milik akun yang sudah tidak ada di daftar master tetap ikut ditampilkan,
    			// kalau tidak, relasi yang sedang disunting akan ikut terhapus saat form disimpan
    			for (var i = 0; i < punya.length; i++) {
    					if (punya[i].nama !== '' && !punyaId(daftar, punya[i].id)) { daftar.push(punya[i]); }
    			}

    			var html = [];
    			for (var k = 0; k < daftar.length; k++) {
    					html.push(pilJuragan(daftar[k], punyaId(punya, daftar[k].id)));
    			}

    			wadah.html(html.length ? html.join('') : '<div class="text-muted small p-1">belum ada juragan</div>');
    	}

    	$.getJSON('{$link_api_juragan_all}', function(b){
    			semuaJuragan = keDaftar(b);

    			// popup dibuka lagi setelah validasi gagal: pilihan sebelumnya ikut dipulihkan
    			var dipilih = JSON.parse($('#pilihJuragan').attr('data-juragan') || '[]');
    			var punya   = [];
    			for (var i = 0; i < dipilih.length; i++) { punya.push({ id: dipilih[i], nama: '' }); }

    			gambarJuragan($('#pilihJuragan'), punya);
    	});

    	function saringPengguna() {
    		var k = $.trim(kunciCari).toLowerCase();
    		var hasil = [];

    		for (var i = 0; i < semuaPengguna.length; i++) {
    			var u = semuaPengguna[i];
    			var teks = [u.nama, u.username, u.email, u.level, u.status].join(' ').toLowerCase();

    			if (k === '' || teks.indexOf(k) !== -1) { hasil.push(u); }
    		}

    		return hasil;
    	}

    	function barisPengguna(u) {
    		return '<tr>'
    			+ '<td><div class="fw-semibold">' + aman(u.nama)
    			+ '<span class="ms-2 badge rounded-pill bg-light text-dark border fw-normal">' + aman(u.username) + '</span></div>'
    			+ '<div class="text-muted small">' + aman(u.email) + '</div></td>'
    			+ '<td><span class="badge rounded-pill ' + (lokaLevel[u.level] || 'bg-light text-dark border') + '">' + aman(u.level) + '</span></td>'
    			+ '<td><span class="badge rounded-pill ' + (lokaStatus[u.status] || 'bg-light text-dark border') + '">' + aman(u.status) + '</span></td>'
    			+ '<td class="text-end"><div class="btn-group">'
    			+ '<button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#modalSuntingPengguna"'
    			+ ' data-id="' + aman(u.id) + '" data-nama="' + aman(u.nama) + '" data-email="' + aman(u.email) + '"'
    			+ ' data-username="' + aman(u.username) + '" data-level="' + aman(u.level) + '" data-status="' + aman(u.status) + '">Sunting</button>'
    			+ '<button type="button" class="btn btn-outline-secondary btn-sm dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown" aria-expanded="false">'
    			+ '<span class="visually-hidden">Toggle Dropdown</span></button>'
    			+ '<ul class="dropdown-menu"><li><button class="dropdown-item" href="#">Hapus</button></li></ul>'
    			+ '</div></td></tr>';
    	}

    	function tombolHalaman(n, label, keadaan) {
    		if (keadaan === 'aktif') {
    			return '<li class="page-item active" aria-current="page"><span class="page-link">' + label + '</span></li>';
    		}
    		if (keadaan === 'mati') {
    			return '<li class="page-item disabled"><span class="page-link">' + label + '</span></li>';
    		}

    		return '<li class="page-item"><a class="page-link" href="#" data-halaman="' + n + '">' + label + '</a></li>';
    	}

    	function gambarHalaman(jumlah, terakhir) {
    		if (jumlah <= perHalaman) {
    			$('#pagPengguna').empty();

    			return;
    		}

    		var dari   = Math.max(1, Math.min(halaman - 2, terakhir - 4));
    		var sampai = Math.min(terakhir, dari + 4);
    		var item   = [];

    		item.push(tombolHalaman(1, '<i class="fal fa-arrow-alt-to-left"></i>', halaman === 1 ? 'mati' : ''));
    		item.push(tombolHalaman(halaman - 1, '<i class="fal fa-arrow-alt-left"></i>', halaman === 1 ? 'mati' : ''));

    		for (var n = dari; n <= sampai; n++) {
    			item.push(tombolHalaman(n, '' + n, n === halaman ? 'aktif' : ''));
    		}

    		item.push(tombolHalaman(halaman + 1, '<i class="fal fa-arrow-alt-right"></i>', halaman === terakhir ? 'mati' : ''));
    		item.push(tombolHalaman(terakhir, '<i class="fal fa-arrow-alt-to-right"></i>', halaman === terakhir ? 'mati' : ''));

    		$('#pagPengguna').html('<nav class="mt-3" aria-label="Navigasi halaman pengguna"><ul class="pagination pagination-sm justify-content-end mb-0">' + item.join('') + '</ul></nav>');
    	}

    	function gambarPengguna() {
    		var hasil    = saringPengguna();
    		var terakhir = Math.max(1, Math.ceil(hasil.length / perHalaman));

    		if (halaman > terakhir) { halaman = terakhir; }

    		var mulai = (halaman - 1) * perHalaman;
    		var isi   = hasil.slice(mulai, mulai + perHalaman);
    		var baris = [];

    		for (var i = 0; i < isi.length; i++) { baris.push(barisPengguna(isi[i])); }

    		if (isi.length === 0) {
    			$('#listUsers').html('<tr><td colspan="4" class="text-center text-muted py-4">Tidak ada pengguna yang cocok'
    				+ (kunciCari !== '' ? ' dengan "' + aman($.trim(kunciCari)) + '"' : '') + '.</td></tr>');
    			$('#jumlahPengguna').text('0 pengguna');
    		} else {
    			$('#listUsers').html(baris.join(''));
    			$('#jumlahPengguna').text((mulai + 1) + '-' + (mulai + isi.length) + ' dari ' + hasil.length + ' pengguna');
    		}

    		gambarHalaman(hasil.length, terakhir);
    	}

    	$.getJSON('{$link_api_pengguna_all}', function(b){
    		semuaPengguna = b || [];
    		gambarPengguna();
    	});

    	$('#cariPengguna').on('input change keyup', function(){
    		kunciCari = $(this).val();
    		halaman   = 1;
    		gambarPengguna();
    	});

    	$('#pagPengguna').on('click', 'a[data-halaman]', function(e){
    		e.preventDefault();
    		halaman = parseInt($(this).attr('data-halaman'), 10) || 1;
    		gambarPengguna();
    	});

    	// tombol mata di kolom kata sandi: tipe input diganti, ikonnya ikut bertukar
    	$(document).on('click', 'button[data-lihat]', function () {
    			var input  = document.getElementById($(this).attr('data-lihat'));
    			var tampil = input.type === 'password';

    			input.type = tampil ? 'text' : 'password';
    			$(this).find('i').attr('class', 'fal ' + (tampil ? 'fa-eye-slash' : 'fa-eye'));
    	});

    	// aturan wajib juragan hanya berlaku untuk level cs, hint-nya ikut disembunyikan
    	function sinkronHintLevel(popup) {
    			var opsi = popup.querySelector('select[name="level"]');
    			var hint = popup.querySelector('[data-hint-cs]');

    			if (opsi && hint) { hint.classList.toggle('d-none', opsi.value !== 'cs'); }
    	}

    	$(document).on('change', 'select[name="level"]', function () {
    			sinkronHintLevel($(this).closest('.modal').get(0));
    	});

    	$('#modalTambahPengguna, #modalSuntingPengguna').each(function () {
    			sinkronHintLevel(this);
    	});

    	var popupTambah = document.getElementById('modalTambahPengguna');
    	if (popupTambah.getAttribute('data-buka') === '1') {
    		bootstrap.Modal.getOrCreateInstance(popupTambah).show();
    	}

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
    		sinkronHintLevel(mP),

    		$.ajax({
    			method:'GET',
    			url:'{$link_api_relasi}',
    			data:{id:b}
    		}).done(function(a){
    				var punya = keDaftar((a[b] && a[b].juragan) || []);

    				gambarJuragan($('#pilihJuraganSunting'), punya);
    		});
    	});
    });
    JS;

$packer    = new Tholu\Packer\Packer($js, 'Normal', true, false, true);
$packed_js = $packer->pack();
echo '<script>' . $packed_js . '</script>';
?>
<?= $this->endSection() ?>
