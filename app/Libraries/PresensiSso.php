<?php

namespace App\Libraries;

/**
 * Klien Single Sign-On ke server Presensi eksternal.
 *
 * Kelas ini sengaja tipis: hanya membungkus satu panggilan REST
 * (POST /api/auth/verify) lalu mengelompokkan hasilnya jadi tiga keadaan yang
 * bisa dipahami Auth::index():
 *   - valid       : Presensi mengonfirmasi kredensial benar (status 200 + data).
 *   - invalid     : Presensi menjangkau server tapi menolak kredensial.
 *   - unavailable : server mati/timeout/JSON rusak -> pemanggil jatuh ke DB lokal.
 *
 * Konfigurasi dibaca dari JuraganConfig::$sso dan boleh dioverride lewat .env
 * (sso.endpoint, sso.api_key, sso.timeout) supaya kunci tidak hardcode saat
 * produksi.
 */
class PresensiSso
{
    /** @var array<string, mixed> konfigurasi ter-gabung (default + env) */
    private array $cfg;

    public function __construct()
    {
        $base = (array) (config('JuraganConfig')->sso ?? []);

        $this->cfg = [
            'aktif'     => $this->boolEnv('sso.aktif', (bool) ($base['aktif'] ?? true)),
            'endpoint'  => (string) (env('sso.endpoint') ?: ($base['endpoint'] ?? '')),
            'apiKey'    => (string) (env('sso.api_key') ?: ($base['apiKey'] ?? '')),
            'timeout'   => (int) (env('sso.timeout') ?: ($base['timeout'] ?? 10)),
            'levelBaru' => (string) (env('sso.level_baru') ?: ($base['levelBaru'] ?? 'cs')),
            // verifikasi sertifikat TLS: mati lewat sso.verify_ssl=false bila server
            // (mis. dev Windows tanpa CA bundle) tidak bisa memverifikasi ngrok
            'verifikasi' => $this->boolEnv('sso.verify_ssl', (bool) ($base['verifikasi'] ?? true)),
            'caBundle'  => (string) (env('sso.ca_bundle') ?: ($base['caBundle'] ?? '')),
        ];
    }

    /** Apakah SSO dinyalakan. Bila false, pemanggil langsung ke verifikasi lokal. */
    public function aktif(): bool
    {
        return $this->cfg['aktif'] === true && $this->cfg['endpoint'] !== '';
    }

    /** Level lokal untuk user SSO baru. */
    public function levelBaru(): string
    {
        return $this->cfg['levelBaru'];
    }

    /**
     * Verifikasi kredensial ke Presensi.
     *
     * @return array{state:string,data?:array<string,mixed>,message?:string}
     */
    public function verifikasi(string $username, string $password): array
    {
        if (! $this->aktif()) {
            return ['state' => 'unavailable', 'message' => 'SSO tidak aktif.'];
        }

        try {
            $opsi = [
                'headers' => [
                    'X-API-KEY'    => $this->cfg['apiKey'],
                    'Content-Type' => 'application/json',
                    'Accept'       => 'application/json',
                ],
                'json'      => ['username' => $username, 'password' => $password],
                'timeout'   => $this->cfg['timeout'],
                'verify'    => $this->cfg['verifikasi'],
                // 401/403 tidak boleh melempar; kita baca status & body-nya sendiri
                'http_errors' => false,
            ];

            // tunjuk CA bundle sendiri bila disediakan (produksi tanpa curl.cainfo)
            if ($this->cfg['verifikasi'] && $this->cfg['caBundle'] !== '') {
                $opsi['curl'] = [CURLOPT_CAINFO => $this->cfg['caBundle']];
            }

            $response = \Config\Services::curlrequest()->post($this->cfg['endpoint'], $opsi);
        } catch (\Throwable $e) {
            // koneksi gagal / DNS / timeout -> anggap server tak terjangkau
            log_message('error', 'SSO Presensi tidak terjangkau: ' . $e->getMessage());

            return ['state' => 'unavailable', 'message' => 'Server Presensi tidak terjangkau.'];
        }

        $status = (int) $response->getStatusCode();
        $body   = $response->getBody();
        $isi    = json_decode((string) $body, true);

        if (! is_array($isi)) {
            log_message('error', 'SSO Presensi mengirim respons non-JSON (HTTP ' . $status . ').');

            return ['state' => 'unavailable', 'message' => 'Respons Presensi tidak dapat dibaca.'];
        }

        $code = (int) ($isi['status'] ?? 0);
        $data = (array) ($isi['data'] ?? []);

        // sukses hanya bila HTTP 200 DAN body menandai status 200 dengan data terisi
        if ($status === 200 && $code === 200 && $data !== []) {
            return ['state' => 'valid', 'data' => $data, 'message' => (string) ($isi['message'] ?? '')];
        }

        return ['state' => 'invalid', 'message' => (string) ($isi['message'] ?? 'Kredensial ditolak Presensi.')];
    }

    /** Baca boolean dari env tanpa bergantung pada normalisasi string CI4. */
    private function boolEnv(string $key, bool $bawaan): bool
    {
        $nilai = env($key);

        if ($nilai === null) {
            return $bawaan;
        }

        if (is_bool($nilai)) {
            return $nilai;
        }

        return ! in_array(strtolower((string) $nilai), ['', '0', 'false', 'off', 'no'], true);
    }
}
