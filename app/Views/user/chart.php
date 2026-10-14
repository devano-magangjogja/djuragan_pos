<?php

use CodeIgniter\I18n\Time;

$sekarang = new Time('now');
$pager    = \Config\Services::pager();
$session  = \Config\Services::session();
?>
<?= $this->extend('template/main') ?>

<?= $this->section('content') ?>
<?= $this->include('user/navbar') ?>

<div class="container-xxl">

    <h1 class="h3 mt-5"><?= esc($title) ?></h1>

    <div class="container-xxl mb-5">
        <div class="row">
            <div class="col-sm-6"><canvas id="jumlah_pesanan" width="400" height="400"></canvas></div>
            <div class="col-sm-6"><canvas id="pesanan_terkirim" width="400" height="400"></canvas></div>
            <div class="col-sm-6"></div>
            <div class="col-sm-6"></div>
        </div>
    </div>

</div>

<?= $this->endSection() ?>

<?= $this->section('js') ?>
<script defer src="https://kit.fontawesome.com/22a9d93fa2.js" data-auto-replace-svg="nest" crossorigin="anonymous"></script>
<script defer src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.9.3/Chart.min.js"></script>
<?php

$current_user_id  = $session->get('id');
$link_api_juragan = site_url('api/juragan/by_user/');
$link_invoice     = site_url('user/invoices/lihat/');

$juragan           = [];
$orderBulanIni     = [];
$orderBulanLalu    = [];
$terkirimBulanIni  = [];
$terkirimBulanLalu = [];

foreach ($counter as $count) {
    $juragan[]           = $count->nama_juragan;
    $orderBulanIni[]     = ($count->jumlahOrderBulanIni !== '' ? $count->jumlahOrderBulanIni : 0);
    $orderBulanLalu[]    = ($count->jumlahOrderBulanLalu !== '' ? $count->jumlahOrderBulanLalu : 0);
    $terkirimBulanIni[]  = ($count->terkirimBulanIni !== '' ? $count->terkirimBulanIni : 0);
    $terkirimBulanLalu[] = ($count->terkirimBulanLalu !== '' ? $count->terkirimBulanLalu : 0);
}

$juragan           = json_encode($juragan);
$orderBulanIni     = json_encode($orderBulanIni);
$orderBulanLalu    = json_encode($orderBulanLalu);
$terkirimBulanIni  = json_encode($terkirimBulanIni);
$terkirimBulanLalu = json_encode($terkirimBulanLalu);

$awalBulanIni  = tanggalDefault('mulai');
$akhirBulanIni = tanggalDefault('akhir');

$js = <<< JS
    $(function() {
    	'use strict';
    	// popover

    	var po = {};
    		po.trigger = 'focus';
    		po.placement = 'right';
    		po.container = 'body';
    		po.html = true;
    		po.selector = '[data-bs-toggle="popHarga"]';
    		po.template = '<div class="popover shadow" role="tooltip"><div class="popover-arrow"></div><div class="popover-body"></div></div>';
    	$('body').popover(po);

    	// tooltips

    	var tt = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    	var ttl = tt.map(function(e) {
    		return new bootstrap.Tooltip(e);
    	});

    	// sidebar

    	var juragan = document.getElementById('juragan');
        if(juragan) {
            juragan.addEventListener('show.bs.offcanvas', function () {
                var id = {$current_user_id};
                $('#listLi').html('');
                $.getJSON('{$link_api_juragan}', { id: id }, function(b){
                    var a=[];
                    $.each(b[id].juragan,function(c,b){
                        a.push('<a href="{$link_invoice}'+b.slug+'" class="list-group-item text-light list-group-item-action"><i class="fal fa-user-circle"></i> '+b.nama+'</a>');
                    }),

                    $(a.join('')).appendTo('#listLi');
                });
            });
        }


    	// graphic chart
    	var chartJumlahPesanan = document.getElementById("jumlah_pesanan").getContext('2d');
        var myChart = new Chart(chartJumlahPesanan, {
            type: 'horizontalBar',
            data: {
                labels: {$juragan},
                datasets: [
                    {
                        label: 'Bulan Lalu',
                        stack: 'Stack 0',
                        data: {$orderBulanLalu},
                        backgroundColor: 'gray',
                        borderColor: 'gray',
                        borderWidth: 1
                    },
    				{
                        label: 'Bulan ini',
                        stack: 'Stack 1',
                        data: {$orderBulanIni},
                        backgroundColor: 'purple',
                        borderColor: 'purple',
                        borderWidth: 1
                    }
                ]
            },
            options: {
                title: {
                    display: true,
                    text: ['Berdasarkan Jumlah Orderan', 'per {$awalBulanIni} | {$akhirBulanIni}']
                },
                scales: {
                    yAxes: [{
                        ticks: {
                            beginAtZero:true
                        },
                        stacked: true
                    }]
                }
            }
        });

        //
        var chartPesananTerkirim = document.getElementById("pesanan_terkirim").getContext('2d');
        var myChart = new Chart(chartPesananTerkirim, {
            type: 'horizontalBar',
            data: {
                labels: {$juragan},
                datasets: [
                    {
                        label: 'Bulan Lalu',
                        stack: 'Stack 0',
                        data: {$terkirimBulanLalu},
                        backgroundColor: 'gray',
                        borderColor: 'gray',
                        borderWidth: 1
                    },
                    {
                        label: 'Bulan Ini',
                        stack: 'Stack 1',
                        data: {$terkirimBulanIni},
                        backgroundColor: 'purple',
                        borderColor: 'purple',
                        borderWidth: 1
                    }
                ]
            },
            options: {
                title: {
                    display: true,
                    text: ['Berdasarkan Jumlah Orderan Terkirim (pcs)', 'per {$awalBulanIni} | {$akhirBulanIni}']
                },
                scales: {
                    yAxes: [{
                        ticks: {
                            beginAtZero:true
                        },
                        stacked: true
                    }]
                }
            }
        });


    });
    JS;

$packer    = new Tholu\Packer\Packer($js, 'Normal', true, false, true);
$packed_js = $packer->pack();
echo '<script>' . $packed_js . '</script>';
?>
<?= $this->endSection() ?>
