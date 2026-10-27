<?php

/**
 * JS bersama untuk halaman admin: pemanggil notifikasi. Dulu disalin mentah-mentah
 * di setiap view, lalu di-include tiga halaman saja sehingga lonceng mati di
 * sisanya; sekarang dimuat dari template/main.php supaya notifikasi bisa dibuka
 * dari halaman mana pun.
 */
helper('fungsi'); // base_user() dipakai di bawah, dan helper tidak di-autoload

$current_user_id = (int) session()->get('id');

// halaman tanpa sesi login tidak punya kotak notifikasi
if ($current_user_id === 0) {
    return;
}

$link_api_notif   = site_url('api/notifikasi/');
$link_reminder    = site_url('admin/reminder/jumlah');

$js = <<< JS
    $(function() {
    	'use strict';

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
    					var badge = b.prioritas
    						? '<span class="badge rounded-pill" style="background:#d92d20;color:#fff;font-size:.68rem;font-weight:700;margin-right:.4rem">Prioritas</span>'
    						: '';
    					a.push(`<div class="list-group-item list-group-item-action">
    						<div class="d-flex">
    							<div class="me-auto">
    								<div class="text-muted small">`+b.created_at +`</div>
    								<a class="d-block text-decoration-none" href="`+(b.tautan || '#')+`">`+ badge + b.notif +`</a>
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

    				$('<div class="notif-kosong"><i class="fal fa-bell-slash fa-2x"></i></div>').appendTo('#notifDisini');
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

        var reminderMenu = document.querySelector('[data-reminder-menu="1"]');
        if (reminderMenu) {
            function renderReminderBadge(jumlah) {
                var badge = reminderMenu.querySelector('.badge-reminder');

                if (jumlah === 0) {
                    if (badge) badge.remove();
                    return;
                }

                if (!badge) {
                    badge = document.createElement('span');
                    badge.className = 'badge rounded-pill bg-danger ms-auto badge-reminder';
                    reminderMenu.appendChild(badge);
                }

                badge.textContent = jumlah > 99 ? '99+' : String(jumlah);
                badge.title = jumlah + ' orderan mendekati/lewat deadline';
            }

            function pollReminderBadge() {
                fetch('{$link_reminder}', { credentials: 'same-origin' })
                    .then(function(r) {
                        if (!r.ok) throw new Error('HTTP ' + r.status);
                        return r.json();
                    })
                    .then(function(res) {
                        if (res && res.status === 'success') renderReminderBadge(Number(res.count || 0));
                    })
                    .catch(function() { /* jaringan sesaat: biarkan angka terakhir */ });
            }

            pollReminderBadge();
            setInterval(pollReminderBadge, 5000);
        }

    	var notifikasi = document.getElementById('notifikasi');
        if (! notifikasi) {
            return; // halaman ini tidak punya lonceng, tidak ada yang perlu diikat
        }

        // lonceng tidak lagi menarik offcanvas dari samping: panelnya melayang
        function tampilNotif() {
            if (notifikasi.classList.contains('tampil')) {
                return;
            }

            notifikasi.classList.add('tampil');
            notifikasi.setAttribute('aria-hidden', 'false');
            $('#notifDisini').empty();
            getNotif();
        }

        function sembunyiNotif() {
            notifikasi.classList.remove('tampil');
            notifikasi.setAttribute('aria-hidden', 'true');
        }

        $(document).on('click', '[data-notif-toggle]', function (e) {
            e.preventDefault();

            if (notifikasi.classList.contains('tampil')) {
                sembunyiNotif();
            } else {
                tampilNotif();
            }
        });

        // klik di luar panel atau Esc menutupnya, seperti dropdown biasa
        $(document).on('click', function (e) {
            if ($(e.target).closest('#notifikasi, [data-notif-toggle]').length === 0) {
                sembunyiNotif();
            }
        });

        $(document).on('keydown', function (e) {
            if (e.key === 'Escape') {
                sembunyiNotif();
            }
        });

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

    });
    JS;

echo '<script>' . (new Tholu\Packer\Packer($js, 'Normal', true, false, true))->pack() . '</script>';
