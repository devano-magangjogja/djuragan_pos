<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class JuraganConfig extends BaseConfig
{
    public $rajaongkir = '75f538ed88e26297a2fabed240ed8bf0'; // ID rajaongkir
    public $size       = [
        'atasan' => [
            's'    => 'S',
            'm'    => 'M',
            'l'    => 'L',
            'xl'   => 'XL',
            'xxl'  => 'XXL',
            'xxxl' => 'XXXL',
        ],
        'bawahan' => [
            '28' => '28',
            '29' => '29',
            '30' => '30',
            '31' => '31',
            '32' => '32',
            '33' => '33',
            '34' => '34',
        ],
        // custom
    ];
    public $kurir = [
        'POS',
        'JNE',
        'COD',
        'First Logistic',
        'TIKI',
        'Wahana',
        'J&T',
        'RPX',
        'lainnya',
    ];
    public $notifikasi = [
        '1'  => '<i class="fal fa-plus-circle text-success"></i> {nama} membuat invoice {invoice}',
        '2'  => '<i class="fal fa-pencil text-info"></i> {nama} merubah invoice {invoice}',
        '3'  => '<i class="fal fa-trash text-danger"></i> {nama} menghapus invoice {invoice}',
        '4'  => '<i class="fal fa-wallet text-info"></i> {nama} menambahkan pembayaran baru pada invoice {invoice}',
        '5'  => '<i class="fal fa-wallet text-success"></i> {nama} men-set "Pembayaran Ada" pada invoice {invoice}',
        '6'  => '<i class="fal fa-wallet text-danger"></i> {nama} men-set "Pembayaran Tidak Ada" pada invoice {invoice}',
        '7'  => '<i class="fal fa-file-alt text-warning"></i> "Data pesanan" invoice {invoice} belum lengkap',
        '8'  => '<i class="fal fa-file-alt text-success"></i> "Data pesanan" invoice {invoice} sudah lengkap',
        '9'  => '<i class="fal fa-layer-group text-warning"></i> "Bahan produk" untuk invoice {invoice} belum ada',
        '10' => '<i class="fal fa-layer-group text-success"></i> "Bahan produk" untuk invoice {invoice} ada',
        '11' => '<i class="fal fa-print text-warning"></i> Orderan pada invoice {invoice} sedang masuk proses sablon',
        '12' => '<i class="fal fa-print text-success"></i> Orderan pada invoice {invoice} selesai proses sablon',
        '13' => '<i class="fal fa-waveform-path fa-rotate-90 text-warning"></i> Orderan pada invoice {invoice} sedang masuk proses bordir',
        '14' => '<i class="fal fa-waveform-path fa-rotate-90 text-success"></i> Orderan pada invoice {invoice} selesai proses bordir',
        '15' => '<i class="fal fa-cut fa-rotate-270 text-warning"></i> Orderan pada invoice {invoice} masuk proses jahit',
        '16' => '<i class="fal fa-cut fa-rotate-270 text-success"></i> Orderan pada invoice {invoice} selesai proses jahit',
        '17' => '<i class="fal fa-tasks text-warning"></i> Orderan pada invoice {invoice} mulai proses QC (Quality Control)',
        '18' => '<i class="fal fa-tasks text-success"></i> Orderan pada invoice {invoice} selesai proses QC (Quality Control)',
        '19' => '<i class="fal fa-box-alt text-warning"></i> Orderan pada invoice {invoice} mulai dipacking',
        '20' => '<i class="fal fa-box-alt text-success"></i> Orderan pada invoice {invoice} selesai packing',
        '21' => '<i class="fal fa-shipping-fast text-warning"></i> Orderan pada invoice {invoice} dikirim beberapa dulu',
        '22' => '<i class="fal fa-shipping-fast text-success"></i> Orderan pada invoice {invoice} telah dikirimkan',
        '23' => '<i class="fal fa-money-check-edit fa-flip-horizontal"></i> Resi Orderan pada invoice {invoice} telah diubah',
    ];
}
