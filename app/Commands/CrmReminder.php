<?php

namespace App\Commands;

use App\Models\TugasModel;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Membuat reminder transaksi yang jatuh tempo dan menutup yang pemicunya hilang.
 *
 * Sama seperti operan shift: halaman Antrean Tugas sudah menjalankannya sendiri
 * paling banyak sekali per lima menit, jadi cron hanya membuat waktunya jadi pasti.
 * Yang dikerjakan perintah ini dan layar itu adalah fungsi yang sama
 * (TugasModel::generateReminder), jadi aturannya tidak punya dua versi.
 *
 * Pakai: php spark crm:reminder
 */
class CrmReminder extends BaseCommand
{
    protected $group       = 'CRM';
    protected $name        = 'crm:reminder';
    protected $description = 'Membuat tugas reminder DP/pelunasan/ulasan dari kondisi nota, dan menutup yang sudah tidak berlaku.';
    protected $usage       = 'crm:reminder';
    protected $arguments   = [];
    protected $options     = [];

    public function run(array $params)
    {
        $hasil = (new TugasModel())->generateReminder();

        CLI::write(
            $hasil['nota'] . ' nota diperiksa: ' . $hasil['dibuat'] . ' reminder baru, '
                . $hasil['ditutup'] . ' reminder ditutup',
            $hasil['dibuat'] + $hasil['ditutup'] > 0 ? 'green' : 'yellow'
        );

        return 0;
    }
}
