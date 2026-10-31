<?php

namespace App\Commands;

use App\Models\TugasModel;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Menyerahkan kembali tugas CS yang jam shift-nya sudah lewat ke dalam antrean.
 *
 * Aplikasi melakukan hal yang sama sendiri, paling banyak satu kali per lima menit,
 * setiap kali halaman Antrean Tugas atau endpoint badge dibuka
 * (TugasModel::operanShiftKalauWaktu()). Perintah ini tetap berguna untuk dua hal:
 * jadwal yang pasti di server yang punya cron, dan jalankan-sekarang tanpa menunggu
 * jeda (--paksa).
 *
 * Pakai: php spark crm:operan [--paksa]
 */
class CrmOperan extends BaseCommand
{
    protected $group       = 'CRM';
    protected $name        = 'crm:operan';
    protected $description = 'Mengembalikan tugas CS yang shift-nya sudah berakhir ke antrean.';
    protected $usage       = 'crm:operan [--paksa]';
    protected $arguments   = [];
    protected $options     = [
        '--paksa' => 'Jalankan sekarang juga, tanpa menghormati jeda lima menit',
    ];

    public function run(array $params)
    {
        $tugas = new TugasModel();

        // spark menyerahkan pilihan sebagai array_merge(getSegments(), getOptions()):
        // '--paksa' muncul sebagai KUNCI 'paksa' (nilainya null), bukan sebagai string.
        // Kedua bentuk diterima supaya perintah ini tidak pecah di versi CLI berbeda.
        $paksa = array_key_exists('paksa', $params) || in_array('--paksa', $params, true);

        $jumlah = $paksa ? $tugas->operanShift() : $tugas->operanShiftKalauWaktu();

        if ($jumlah > 0) {
            CLI::write($jumlah . ' tugas dikembalikan ke antrean karena shift penggarapnya berakhir', 'green');

            return 0;
        }

        $terakhir = $tugas->operanTerakhir();

        CLI::write($paksa
            ? 'Tidak ada tugas yang perlu dioperkan.'
            : 'Tidak ada yang dijalankan: jeda lima menit masih berlaku (sapuan terakhir ' . ($terakhir['waktu'] === null
                ? 'belum pernah'
                : date('d-m-Y H:i', $terakhir['waktu'])) . '). Pakai --paksa untuk memaksa.',
            'yellow');

        return 0;
    }
}
