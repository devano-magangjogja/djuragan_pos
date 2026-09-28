<?php

namespace App\Controllers;

class Juragan extends BaseController
{
    public static function by_user($user_id, $bank = 'no')
    {
        $juragan = new \App\Models\JuraganModel();
        $cache   = \Config\Services::cache();

        $nama_cache = 'juragan_by_user_' . $user_id . ($bank === 'yes' ? '_bank' : '');
        if (! $json = $cache->get($nama_cache)) {
            $x = $juragan->byUserId($user_id)->getResult();

            $json     = [];
            $juragans = [];

            foreach ($x as $a) {
                $list_bank = [];
                if ($bank === 'yes') {
                    $banks = $juragan->ambil_bank($a->id_juragan)->getResult();

                    foreach ($banks as $b) {
                        $list_bank[$b->id_bank] = [
                            'id'        => (int) $b->id_bank,
                            'nama'      => $b->nama_bank,
                            'tipe'      => $b->tipe_bank,
                            'rekening'  => $b->rekening,
                            'atas_nama' => $b->atas_nama,
                        ];
                    }
                }

                $juragans[$a->id_juragan] = [
                    'id'   => (int) $a->id_juragan,
                    'nama' => $a->nama_juragan,
                    'slug' => $a->juragan,
                ];

                if ($bank === 'yes') {
                    $juragans[$a->id_juragan]['bank'] = $list_bank;
                }

                $json[$a->user_id] = [
                    'id'       => (int) $a->user_id,
                    'nama'     => $a->nama_user,
                    'username' => $a->username,
                    'email'    => $a->email,
                    'juragan'  => $juragans,

                ];
            }

            // simpan cache selama 30 menit
            $cache->save($nama_cache, $json, 60 * 30);
        }

        return $json;
    }

    public static function get_users($current_user_id)
    {
        $juragan = new \App\Models\JuraganModel();

        $user        = $juragan->byUserId($current_user_id)->getResult();
        $ids_juragan = [];

        foreach ($user as $u) {
            $ids_juragan[] = $u->id_juragan;
        }

        $r_users = $juragan->getUsers($ids_juragan)->getResult();

        $users = [];

        foreach ($r_users as $jrgn) {
            $users[] = [
                'id'       => (int) $jrgn->id,
                'nama'     => $jrgn->name,
                'username' => $jrgn->username,
            ];
        }

        return $users;
    }
}
