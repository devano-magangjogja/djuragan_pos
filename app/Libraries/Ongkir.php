<?php

namespace App\Libraries;

use App\Models\Cities;
use App\Models\Province;
use App\Models\Subdistricts;
use Totoprayogo1916\CodeIgniter\Libraries\Rajaongkir;

class Ongkir
{
    protected $rajaongkir;
    protected $cache;
    protected $provinsi;
    protected $jangka;

    public function __construct()
    {
        $this->rajaongkir = new Rajaongkir(config('JuraganConfig')->rajaongkir, Rajaongkir::ACCOUNT_PRO);
        $this->cache      = \Config\Services::cache();
        $this->provinsi   = new Province();
        $this->jangka     = 60 * 60 * 24 * 365;
    }

    /**
     * Ambil semua provinsi
     */
    private function allProvinsi(): array
    {
        $nama_cache = 'prov_';

        if (! $provinsi = $this->cache->get($nama_cache)) {
            $provinsi = $this->provinsi->findAll();

            $this->cache->save($nama_cache, $provinsi, $this->jangka);
        }

        return $provinsi;
    }

    /**
     * Ambil data provinsi berdasarkan id
     */
    public function provinsi(?int $id_provinsi = null): array
    {
        if ($id_provinsi === null) {
            return $this->allProvinsi();
        }

        $key = array_search($id_provinsi, array_column($this->allProvinsi(), 'province_id'), false);

        if ($key !== false) {
            return (array) $this->allProvinsi()[$key];
        }

        return [];
    }

    /**
     * Ambil data kota/kabupaten berdasarkan id_provinsi
     */
    private function allKota(int $id_provinsi): array
    {
        $nama_cache = 'city_' . $id_provinsi;
        if (! $kota = $this->cache->get($nama_cache)) {
            $city = new Cities();

            $kota = $city->where(['province_id' => $id_provinsi])->findAll();

            // simpan cache
            $this->cache->save($nama_cache, $kota, $this->jangka);
        }

        return $kota;
    }

    /**
     * Ambil data kota/kabupaten berdasarkan id_provinsi & id_kota
     */
    public function kota(int $id_provinsi, ?int $id_kota): array
    {
        if ($id_kota === null) {
            return $this->allKota($id_provinsi);
        }

        $key = array_search($id_kota, array_column($this->allKota($id_provinsi), 'city_id'), false);

        if ($key !== false) {
            return (array) $this->allKota($id_provinsi)[$key];
        }

        return [];
    }

    /**
     * Ambil semua kecamatan berdasarkan $id_kota
     */
    private function allKecamatan(int $id_kota): array
    {
        $nama_cache = 'subdc_' . $id_kota;
        if (! $kecamatan = $this->cache->get($nama_cache)) {
            $subdc = new Subdistricts();

            $kecamatan = $subdc->where(['city_id' => $id_kota])->findAll();

            // simpan cache
            $this->cache->save($nama_cache, $kecamatan, $this->jangka);
        }

        return $kecamatan;
    }

    /**
     * Ambil kecamatan berdasarkan id_kota & id_kecamatan
     */
    public function kecamatan(int $id_kota, ?int $id_kecamatan): array
    {
        if ($id_kecamatan === null) {
            return $this->allKecamatan($id_kota);
        }

        $key = array_search($id_kecamatan, array_column($this->allKecamatan($id_kota), 'subdistrict_id'), false);

        if ($key !== false) {
            return (array) $this->allKecamatan($id_kota)[$key];
        }

        return [];
    }
}
