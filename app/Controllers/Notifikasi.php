<?php

namespace App\Controllers;

use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\I18n\Time;

class Notifikasi extends BaseController
{
    /**
     * Halaman daftar lengkap notifikasi milik akun yang sedang login.
     * Dibuka dari tautan "Lihat semua notifikasi" di panel lonceng.
     */
    public function index($tab = 'belum')
    {
        if (! in_array($tab, ['belum', 'semua'], true)) {
            throw PageNotFoundException::forPageNotFound();
        }

        $notifikasi = new \App\Models\NotifikasiModel();
        $user_id    = (int) session()->get('id');
        $saring     = $tab === 'belum' ? false : null;

        // sapuan otomatis: paling sering hanya coba sekali per batas waktunya
        // (NotifikasiModel::sapuJadwal), jadi halaman ini tidak menulis apa pun
        // tiap kali dibuka
        $notifikasi->sapuJadwal();

        $limit = config('Pager')->perPage;
        $page  = (int) $this->request->getGet('page');
        $page  = $page < 1 ? 1 : $page;
        $total = $notifikasi->ambil($user_id, $saring)->countAllResults();
        $akhir = max(1, (int) ceil($total / $limit));
        $page  = $page > $akhir ? $akhir : $page;

        $daftar   = [];
        $template = config('JuraganConfig')->notifikasi;
        $zona     = config('App')->appTimezone;

        foreach ($notifikasi->ambil($user_id, $saring, $limit, ($page - 1) * $limit)->get()->getResult() as $notif) {
            // template notifikasi dari config memang berisi markup (ikon), jadi
            // yang di-escape hanyalah nilai yang dimasukkan ke dalamnya
            $pesan = isset($template[$notif->type])
                ? replacer($template[$notif->type], ['invoice' => esc((string) $notif->seri), 'nama' => esc((string) $notif->name)])
                : '';

            $daftar[] = [
                'id'        => (int) $notif->id_notifikasi,
                'pesan'     => $pesan,
                'juragan'   => $notif->juragan,
                'seri'      => $notif->seri,
                'dibaca'    => $notif->read_at !== null,
                'prioritas' => (int) ($notif->prioritas ?? 0) === 1,
                'waktu'     => Time::createFromTimestamp($notif->created_at)
                    ->setTimezone($zona)
                    ->toLocalizedString('EEE, d MMM yyyy HH:mm'),
                'tautan'    => tautan_invoice_notif($notif->seri, $notif->juragan),
            ];
        }

        return view('notifikasi', [
            'title'         => 'Notifikasi',
            'daftar'        => $daftar,
            'tab'           => $tab,
            'jumlah_belum'  => $notifikasi->ambil($user_id, false)->countAllResults(),
            'jumlah_semua'  => $notifikasi->ambil($user_id, null)->countAllResults(),
            'halaman'       => $page,
            'halaman_akhir' => $akhir,
            'boleh_sapu'    => in_array(session()->get('level'), ['admin', 'superadmin'], true),
        ]);
    }

    // --
}
