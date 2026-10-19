<?php

namespace App\Filters;

use App\Models\KunciApiModel;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Pagar untuk endpoint /api/publik: kunci API, bukan sesi login.
 *
 * Orang luar tidak punya sesi, jadi filter `auth` tidak bisa dipakai di sini.
 * Yang diperiksa tiga hal dan semuanya gagal diam-diam aman: kunci dikenal dan
 * masih hidup, kunci boleh membuka rute ini (argumen filter = nama hak), dan
 * kuota hariannya belum habis.
 *
 * Token dibaca dari header X-API-Key; Authorization: Bearer ikut diterima karena
 * itu kebiasaan banyak library HTTP, bukan karena kita menyuruh pemakai menaruh
 * kunci di URL — di query string kunci akan ikut tertinggal di log server.
 */
class Apitoken implements FilterInterface
{
    /** Baris kunci yang lolos, dipakai controller tanpa mencari ulang. */
    private static ?object $kunci = null;

    public static function kini(): ?object
    {
        return self::$kunci;
    }

    /**
     * @param array|null $arguments nama hak yang wajib punya kunci, mis. 'katalog'
     */
    public function before(RequestInterface $request, $arguments = null)
    {
        $response = service('response');
        $mentah   = trim($request->getHeaderLine('X-API-Key'));

        if ($mentah === '' && preg_match('/^Bearer\s+(.+)$/i', $request->getHeaderLine('Authorization'), $m)) {
            $mentah = trim($m[1]);
        }

        $model = new KunciApiModel();
        $kunci = $model->cari($mentah);

        if ($kunci === null) {
            return $response->setStatusCode(401)->setJSON(['error' => 'Kunci API tidak dikenal atau sudah dicabut.']);
        }

        $hak = (string) ($arguments[0] ?? '');

        if ($hak !== '' && ! KunciApiModel::boleh($kunci, $hak)) {
            return $response->setStatusCode(403)->setJSON(['error' => 'Kunci ini tidak boleh membuka bagian itu.']);
        }

        if (! KunciApiModel::hemat($kunci)) {
            return $response->setStatusCode(429)->setJSON(['error' => 'Kuota harian kunci ini sudah habis.']);
        }

        self::$kunci = $kunci;
        $model->tandaiPakai($kunci);

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }
}
