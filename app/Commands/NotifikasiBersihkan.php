<?php

namespace App\Commands;

use App\Models\NotifikasiModel;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Menyapu notifikasi yang sudah terbaca dan melewati batas umurnya.
 *
 * Aplikasi melakukan hal yang sama sendiri setiap kali halaman atau lonceng
 * notifikasi dibuka (NotifikasiModel::sapuJadwal(), maksimal sekali per
 * JEDA_SAPU jam), jadi cron bukan satu-satunya jalan. Perintah ini berguna untuk
 * dua hal: jadwal yang pasti di server yang memang punya cron, dan pembersihan
 * sekali jalan tanpa sesi login (--hari=N).
 *
 * Pakai: php spark notif:bersihkan [--hari=30] [--paksa]
 */
class NotifikasiBersihkan extends BaseCommand
{
    protected $group       = 'Notifikasi';
    protected $name        = 'notif:bersihkan';
    protected $description = 'Menghapus notifikasi yang sudah terbaca dan lebih tua dari batas umur pengaturan.';
    protected $usage       = 'notif:bersihkan [--hari=30] [--paksa]';
    protected $arguments   = [];
    protected $options     = [
        '--hari'  => 'Batas umur yang dipakai sekali ini, tanpa mengubah pengaturan',
        '--paksa' => 'Tetap menyapu walau hapus otomatis sedang dimatikan',
    ];

    public function run(array $params)
    {
        $umur  = null;
        $paksa = false;

        foreach ($params as $arg) {
            if (str_starts_with($arg, '--hari=')) {
                $umur = (int) substr($arg, strlen('--hari='));
            } elseif ($arg === '--paksa') {
                $paksa = true;
            }
        }

        $notifikasi = new NotifikasiModel();
        $setelan    = $notifikasi->setelan();

        if ($umur === null && ! $setelan['aktif'] && ! $paksa) {
            CLI::write('Hapus otomatis sedang mati. Nyalakan lewat Pengaturan > Notifikasi, atau jalankan sekali jalan dengan --hari=N.', 'yellow');

            return 1;
        }

        $umur     = $notifikasi->umurSah($umur ?? $setelan['umur_hari']);
        $terhapus = $notifikasi->sapuUsang($umur);

        CLI::write(
            $terhapus . ' notifikasi terbaca yang lebih tua dari ' . $umur . ' hari dihapus'
                . ($setelan['aktif'] ? '' : ' (hapus otomatis masih mati; jadwalnya tidak berubah)'),
            $terhapus > 0 ? 'green' : 'yellow'
        );

        return 0;
    }
}
