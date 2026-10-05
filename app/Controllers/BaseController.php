<?php

namespace App\Controllers;

use App\Controllers\Juragan as Jrgn;
use App\Models\InvoiceModel;
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

        // akun lama ada yang belum punya baris order_relasi, jadi peta juragannya bisa
        // tidak ada sama sekali; tanpa pagar ini PHP 8 melempar "foreach on null"
        $punya = $juragan[$user_id]['juragan'] ?? [];

        $ids = [];

        foreach ($punya as $r) {
            $ids = array_merge($ids, [$r['id']]);
        }

        $return = $juraganModel->terakhir_update($ids);

        if ($return === null) {
            //  $juragan[$user_id]['juragan'];
            $arr = [];

            foreach ($punya as $r => $v) {
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

    /**
     * Daftar toko yang boleh dibuka akun ini. superadmin memegang semuanya, jadi
     * daftar lengkap dikembalikan apa adanya; admin/CS/viewer hanya toko yang
     * tertaut di order_relasi (table=1).
     *
     * @return list<int>
     */
    public function tokoBoleh(): array
    {
        if (session()->get('level') === 'superadmin') {
            $semua = (new JuraganModel())->select('id_juragan')->findAll();

            return array_map('intval', array_column($semua, 'id_juragan'));
        }

        return $this->juraganIds();
    }

    public function bolehToko($juragan_id): bool
    {
        return in_array((int) $juragan_id, $this->tokoBoleh(), true);
    }

    /**
     * Toko satu-satunya milik akun ini, atau null kalau pegang nol / lebih dari satu.
     * Dipakai untuk membuang pilihan "Semua Juragan": kalau yang dipegang cuma satu
     * toko, orang tidak perlu memilih dan tidak bisa nyasar ke toko lain.
     *
     * @return array{id:int,slug:string,nama:string}|null
     */
    public function tokoTunggal(): ?array
    {
        $ids = $this->juraganIds();

        if (count($ids) !== 1 || session()->get('level') === 'superadmin') {
            return null;
        }

        $toko = (new JuraganModel())->where('id_juragan', $ids[0])->first();

        if ($toko === null) {
            return null;
        }

        return ['id' => (int) $toko->id_juragan, 'slug' => $toko->juragan, 'nama' => $toko->nama_juragan];
    }

    /**
     * Satu nota, diambil hanya kalau tokonya memang dipegang akun ini.
     * null menjawab dua hal sekaligus: notanya tidak ada, atau itu nota toko lain.
     * Halaman admin dan halaman CS memakai pagar yang sama.
     */
    public function notaMilikToko(int $invoice_id): ?object
    {
        $nota = (new InvoiceModel())->find($invoice_id);

        if ($nota === null || ! $this->bolehToko((int) $nota->juragan_id)) {
            return null;
        }

        return $nota;
    }

    /**
     * Jawaban baku untuk permintaan ke nota toko lain: tidak ada yang diubah.
     */
    public function tolakToko()
    {
        $this->response->setStatusCode(403);

        return $this->response->setJSON(['status' => 'Orderan ini bukan milik tokomu.']);
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

            // slug boleh berbentuk benar tapi juragannya sudah tidak ada, dan akun lama
            // bisa tidak punya peta juragan sama sekali; keduanya harus cukup dijawab
            // "tidak diijinkan" supaya halaman memberi 404, bukan galat PHP
            if ($get !== null) {
                $punya = $juragans[$user_id]['juragan'] ?? [];

                $result = array_search($get->id_juragan, array_column($punya, 'id'), false);  // false if not found, key off array if exist

                $return = false;
                if ($result !== false) {
                    $return = true;
                }
            }
        }

        return $return;
    }
}
