<?php

namespace App\Controllers;

use App\Controllers\Juragan as Jrgn;
use App\Models\JuraganModel;
use CodeIgniter\Controller;
use CodeIgniter\HTTP\CLIRequest;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

/**
 * Class BaseController
 *
 * BaseController provides a convenient place for loading components
 * and performing functions that are needed by all your controllers.
 * Extend this class in any new controllers:
 *     class Home extends BaseController
 *
 * For security be sure to declare any new methods as protected or private.
 */
abstract class BaseController extends Controller
{
    /**
     * Instance of the main Request object.
     *
     * @var CLIRequest|IncomingRequest
     */
    protected $request;

    /**
     * An array of helpers to be loaded automatically upon
     * class instantiation. These helpers will be available
     * to all other controllers that extend BaseController.
     *
     * @var array
     */
    protected $helpers = ['date', 'form', 'fungsi', 'number', 'text', 'url'];

    /**
     * Constructor.
     */
    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        // Do Not Edit This Line
        parent::initController($request, $response, $logger);

        // Preload any models, libraries, etc, here.

        // migration here
        // $migrate = \Config\Services::migrations();
        // $migrate->latest();
    }

    public function juraganBy($user_id)
    {
        $juragan      = Jrgn::by_user($user_id);
        $juraganModel = new JuraganModel();

        $ids = [];

        foreach ($juragan[$user_id]['juragan'] as $r) {
            $ids = array_merge($ids, [$r['id']]);
        }

        $return = $juraganModel->terakhir_update($ids);

        if ($return === null) {
            //  $juragan[$user_id]['juragan'];
            $arr = [];

            foreach ($juragan[$user_id]['juragan'] as $r => $v) {
                $arr['id_juragan']   = $v['id'];
                $arr['juragan']      = $v['slug'];
                $arr['nama_juragan'] = $v['nama'];
                break;
            }

            $return = (object) $arr;
        }

        return $return;
    }

    /**
     * ID juragan milik pengguna yang sedang login. Dasar pembatas semua halaman
     * baru (Produk, Dasbor) supaya data juragan lain tidak ikut terbaca.
     *
     * @return list<int>
     */
    public function juraganIds(): array
    {
        $user_id  = (int) session()->get('id');
        $juragans = Jrgn::by_user($user_id)[$user_id]['juragan'] ?? [];

        return array_map('intval', array_column($juragans, 'id'));
    }

    /**
     * Pilihan juragan untuk dropdown filter, sudah terbatas pada milik pengguna.
     *
     * @return array<int, string> id_juragan => nama
     */
    public function juraganPilihan(): array
    {
        $user_id  = (int) session()->get('id');
        $pilihan  = [];

        foreach (Jrgn::by_user($user_id)[$user_id]['juragan'] ?? [] as $j) {
            $pilihan[(int) $j['id']] = $j['nama'];
        }

        asort($pilihan);

        return $pilihan;
    }

    public function isJuragan($juragan)
    {
        $juraganModel = new JuraganModel();
        $juraganCount = $juraganModel->where('juragan', $juragan)->countAllResults();

        $return = false;
        if ($juraganCount > 0) {
            $return = true;
        }

        return $return;
    }

    public function allowedJuragan($user_id, $juragan)
    {
        $juraganModel = new JuraganModel();
        $juragans     = Jrgn::by_user($user_id);
        $return       = false;

        if ($this->isJuragan($juragan)) {
            $get = $juraganModel->where('juragan', $juragan)->first();

            $result = array_search($get->id_juragan, array_column($juragans[$user_id]['juragan'], 'id'), false);  // false if not found, key off array if exist

            $return = false;
            if ($result !== false) {
                $return = true;
            }
        }

        return $return;
    }
}
