<?php

namespace App\Controllers\Api;

use App\Controllers\Juragan as Jrgn;
use CodeIgniter\API\ResponseTrait;

class Juragan extends \CodeIgniter\Controller
{
    use ResponseTrait;

    public function all()
    {
        $juragan = new \App\Models\JuraganModel();
        $cache   = \Config\Services::cache();

        // $response = service('response');

        $nama_cache = 'all_juragan';
        if (! $json = $cache->get($nama_cache)) {
            $x = $juragan->findAll();

            $json = [];

            foreach ($x as $a) {
                $list_bank = [];
                $banks     = $juragan->ambil_bank($a->id_juragan)->getResult();

                foreach ($banks as $b) {
                    $list_bank[$b->id_bank] = [
                        'id'        => (int) $b->id_bank,
                        'nama'      => $b->nama_bank,
                        'tipe'      => $b->tipe_bank,
                        'rekening'  => $b->rekening,
                        'atas_nama' => $b->atas_nama,
                    ];
                }

                $json[$a->id_juragan] = [
                    'id'   => (int) $a->id_juragan,
                    'nama' => $a->nama_juragan,
                    'slug' => $a->juragan,
                    'bank' => $list_bank,
                ];
            }

            // simpan cache selama 30 menit
            $cache->save($nama_cache, $json, 60 * 1);
        }

        return $this->respond($json);
    }

    public function by_user()
    {
        $user_id = $this->request->getGet('id'); // numeric
        $bank    = $this->request->getGet('bank'); // yes = true

        $json = Jrgn::by_user($user_id, $bank);

        return $this->response->setJSON($json);
    }

    public function get_users()
    {
        $user_id = $this->request->getGet('id'); // numeric

        $json = Jrgn::get_users($user_id);

        return $this->response->setJSON($json);
    }
}
