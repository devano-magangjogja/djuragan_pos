<?php

namespace App\Libraries\WhatsApp\Providers;

use App\Libraries\WhatsApp\Contracts\WhatsAppProviderInterface;

class KapsoProvider implements WhatsAppProviderInterface
{
    protected string $apiKey;
    protected string $phoneNumberId;
    protected string $baseUrl;

    public function __construct(array $config = [])
    {
        $this->apiKey        = trim($config['api_key'] ?? '');
        $this->phoneNumberId = trim($config['phone_number_id'] ?? '');
        $this->baseUrl       = rtrim($config['base_url'] ?? 'https://api.kapso.ai', '/');
    }

    public function getName(): string
    {
        return 'kapso';
    }

    public function isConfigured(): bool
    {
        return !empty($this->apiKey) && !empty($this->phoneNumberId);
    }

    public function sendMessage(string $to, string $message, array $options = []): array
    {
        if (!$this->isConfigured()) {
            return [
                'success'    => false,
                'message_id' => null,
                'error'      => 'Kredensial Kapso (API Key atau Phone Number ID) belum dikonfigurasi.',
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

        $endpoint = "{$this->baseUrl}/meta/whatsapp/v24.0/{$this->phoneNumberId}/messages";

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type'    => 'individual',
            'to'                => $cleanPhone,
            'type'              => 'text',
            'text'              => [
                'body' => $message,
            ],
        ];

        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'X-API-Key: ' . $this->apiKey,
            'Content-Type: application/json',
            'Accept: application/json',
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        // SSL certificate verify bypass for Windows dev environment
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

        if ($httpCode >= 200 && $httpCode < 300) {
            $messageId = $data['messages'][0]['id'] ?? null;
            return [
                'success'    => true,
                'message_id' => $messageId,
                'error'      => null,
                'raw'        => $data,
            ];
        }

        $errorMessage = $data['error']['message'] ?? ($data['message'] ?? 'Gagal mengirim pesan via Kapso (HTTP ' . $httpCode . ')');

        return [
            'success'    => false,
            'message_id' => null,
            'error'      => $errorMessage,
            'raw'        => $data ?? $response,
        ];
    }

    /**
     * Normalisasi nomor telepon ke format internasional (misal 0812... -> 62812...)
     */
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
}
