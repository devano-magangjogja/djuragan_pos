<?php

namespace App\Libraries\WhatsApp\Contracts;

interface WhatsAppProviderInterface
{
    /**
     * Mendapatkan nama unik provider (contoh: kapso, fonnte)
     */
    public function getName(): string;

    /**
     * Cek apakah konfigurasi provider sudah lengkap
     */
    public function isConfigured(): bool;

    /**
     * Kirim pesan teks WhatsApp ke nomor tujuan
     *
     * @param string $to Nomor tujuan (format: 628xxx atau 08xxx)
     * @param string $message Isi pesan teks
     * @param array $options Opsi tambahan
     * @return array [
     *   'success'    => bool,
     *   'message_id' => string|null,
     *   'error'      => string|null,
     *   'raw'        => mixed
     * ]
     */
    public function sendMessage(string $to, string $message, array $options = []): array;
}
