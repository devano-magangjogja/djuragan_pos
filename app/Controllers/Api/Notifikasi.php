<?php

namespace App\Controllers\Api;

use CodeIgniter\API\ResponseTrait;
use CodeIgniter\I18n\Time;

class Notifikasi extends \CodeIgniter\Controller
{
    use ResponseTrait;

    public function get()
    {
        $notifikasi = new \App\Models\NotifikasiModel();
        helper('fungsi');

        $user_id = $this->request->getGet('id');
        $page    = $this->request->getGet('page');
        $baca    = $this->request->getGet('dibaca');

        $limit = config('Pager')->perPage;
        $page  = (int) $this->request->getGet('page');

        if (! isset($page) || $page === 0 || $page === 1) {
            $page   = 1;
            $offset = 0;
        } else {
            $offset = ($page - 1) * $limit;
            $page   = $page;
        }

        $dibaca = false;
        if (isset($baca) && $baca === '1') {
            $dibaca = true;
        }

        $counter = $notifikasi->ambil($user_id, $dibaca)->countAllResults();

        $res = [
            'page'       => (int) $page,
            'next'       => (ceil($counter / $limit) > $page ? true : false),
            'count'      => $counter,
            'count_text' => formatRibuan($counter),
        ];

        $x = $notifikasi->ambil($user_id, $dibaca, $limit, $offset)->get()->getResult();

        foreach ($x as $notif) {
            $notifikasi = replacer(config('JuraganConfig')->notifikasi[$notif->type], ['invoice' => $notif->seri, 'nama' => $notif->name]);
            $tanggal    = Time::createFromTimestamp($notif->created_at)->setTimezone(config('App')->appTimezone);

            $res['results'][] = [
                'id'         => (int) $notif->id_notifikasi,
                'notif'      => $notifikasi,
                'created_at' => $tanggal->toLocalizedString('EEE, d MMM yyyy (HH:mm:ss)'),
                'invoice'    => $notif->seri,
                'juragan'    => $notif->juragan,
            ];
        }

        return $this->respond($res);
    }

    public function mark_all()
    {
        $notifikasi = new \App\Models\NotifikasiModel();

        $user_id = $this->request->getPost('id');
        $notifikasi->whereIn('for', [$user_id])
            ->set(['read_at' => time()])
            ->update();

        return $this->respond(['status' => 'OK', 'message' => 'Semua notifikasi sudah ditandai terbaca']);
    }

    public function mark()
    {
        $notifikasi = new \App\Models\NotifikasiModel();

        $id     = $this->request->getPost('id');
        $action = $this->request->getPost('action');

        $dibaca = time();
        if ($action === 'belumBaca') {
            $dibaca = null;
        }

        $data = [
            'id_notifikasi' => $id,
            'read_at'       => $dibaca,
        ];
        $notifikasi->save($data);

        return $this->respond($data);
    }
}
