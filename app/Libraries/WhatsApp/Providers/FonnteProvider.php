<?php

namespace App\Libraries\WhatsApp\Providers;

use App\Libraries\WhatsApp\Contracts\WhatsAppProviderInterface;

class FonnteProvider implements WhatsAppProviderInterface
{
    protected string $token;
    protected string $baseUrl;

    public function __construct(array $config = [])
    {
        $this->token   = trim($config['token'] ?? '');
        $this->baseUrl = rtrim($config['base_url'] ?? 'https://api.fonnte.com', '/');
    }

    public function getName(): string
    {
        return 'fonnte';
    }

    public function isConfigured(): bool
    {
        return !empty($this->token);
    }

    public function sendMessage(string $to, string $message, array $options = []): array
    {
        if (!$this->isConfigured()) {
            return [
                'success'    => false,
                'message_id' => null,
                'error'      => 'Token Fonnte belum dikonfigurasi.',
                'raw'        => null,
            ];
        }

        $cleanPhone = $this->normalizePhone($to);
        if (empty($cleanPhone)) {
            return [
                'success'    => false,
                'message_id' => null,
                'error'      => 'Nomor telepon tujuan tidak valid.',
                'raw'        => null,
            ];
        }

        $endpoint = "{$this->baseUrl}/send";

        $postData = [
            'target'      => $cleanPhone,
            'message'     => $message,
            'countryCode' => '62',
        ];

        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: ' . $this->token,
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);

        if ($response === false || !empty($curlError)) {
            return [
                'success'    => false,
                'message_id' => null,
                'error'      => 'cURL Error: ' . $curlError,
                'raw'        => null,
            ];
        }

        $data = json_decode($response, true);

        if ($httpCode >= 200 && $httpCode < 300 && ($data['status'] ?? false)) {
            $messageId = $data['id'][0] ?? ($data['process'] ?? null);
            return [
                'success'    => true,
                'message_id' => is_string($messageId) ? $messageId : json_encode($messageId),
                'error'      => null,
                'raw'        => $data,
            ];
        }

        $errorMessage = $data['reason'] ?? ($data['message'] ?? 'Gagal mengirim pesan via Fonnte (HTTP ' . $httpCode . ')');

        return [
            'success'    => false,
            'message_id' => null,
            'error'      => $errorMessage,
            'raw'        => $data ?? $response,
        ];
    }

    protected function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/[^\d]/', '', $phone);
        if (str_starts_with($phone, '0')) {
            $phone = '62' . substr($phone, 1);
        } elseif (str_starts_with($phone, '8')) {
            $phone = '62' . $phone;
        }
        return $phone;
    }

    /**
     * Cek status profil & koneksi perangkat ke Fonnte
     * Endpoint: POST https://api.fonnte.com/device
     */
    public function getDeviceStatus(): array
    {
        if (!$this->isConfigured()) {
            return [
                'status'  => false,
                'message' => 'Token Fonnte belum dikonfigurasi.',
            ];
        }

        $endpoint = "{$this->baseUrl}/device";

        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: ' . $this->token,
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false || !empty($curlError)) {
            return [
                'status'  => false,
                'message' => 'Gagal menghubungi server Fonnte: ' . $curlError,
            ];
        }

        $data = json_decode($response, true);
        if (!is_array($data)) {
            return [
                'status'  => false,
                'message' => 'Respon Fonnte tidak valid (HTTP ' . $httpCode . ')',
                'raw'     => $response,
            ];
        }

        return $data;
    }

    /**
     * Mengambil QR Code untuk koneksi WhatsApp via Fonnte
     * Endpoint: POST https://api.fonnte.com/qr
     */
    public function getQrCode(): array
    {
        if (!$this->isConfigured()) {
            return [
                'status'  => false,
                'message' => 'Token Fonnte belum dikonfigurasi.',
            ];
        }

        $endpoint = "{$this->baseUrl}/qr";

        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: ' . $this->token,
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, ['type' => 'qr']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false || !empty($curlError)) {
            return [
                'status'  => false,
                'message' => 'Gagal mengambil QR Fonnte: ' . $curlError,
            ];
        }

        $data = json_decode($response, true);
        if (!is_array($data)) {
            return [
                'status'  => false,
                'message' => 'Respon QR Fonnte tidak valid (HTTP ' . $httpCode . ')',
                'raw'     => $response,
            ];
        }

        return $data;
    }
}
