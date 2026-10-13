<?= $this->extend('template/main') ?>

<?= $this->section('content') ?>
<?= $this->include('admin/navbar') ?>

<div class="container-xxl">
    <h1 class="h3 mt-5">Pengaturan</h1>
</div>
<div class="container">
    <div class="row gx-2">
        <div class="col-sm-6 col-md-4">
            <div class="d-grid gap-2 mb-3">
                <?= anchor('admin/settings/bank', 'Atur Bank', ['class' => 'btn btn-lg btn-outline-dark']) ?>
            </div>
        </div>
        <div class="col-sm-6 col-md-4">
            <div class="d-grid gap-2 mb-3">
                <?= anchor('admin/settings/juragan', 'Atur Juragan', ['class' => 'btn btn-lg btn-outline-dark']) ?>
            </div>
        </div>
        <div class="col-sm-6 col-md-4">
            <div class="d-grid gap-2 mb-3">
                <?= anchor('admin/settings/pengguna', 'Atur Pengguna', ['class' => 'btn btn-lg btn-outline-dark']) ?>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('js') ?>
<script defer src="https://kit.fontawesome.com/22a9d93fa2.js" data-auto-replace-svg="nest" crossorigin="anonymous"></script>
<?php

$current_user_id  = session()->get('id');
$link_api_juragan = site_url('api/juragan/by_user/');
$link_invoice     = site_url('admin/invoices/lihat/');

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


    });
    JS;

$packer    = new Tholu\Packer\Packer($js, 'Normal', true, false, true);
$packed_js = $packer->pack();
echo '<script>' . $packed_js . '</script>';
?>
<?= $this->endSection() ?>
