<?php

namespace App\Controllers;

use App\Libraries\WhatsApp\WhatsAppManager;

class Webhook extends BaseController
{
    /**
     * Webhook Endpoint WhatsApp (Kapso / Fonnte)
     * URL: https://.../webhook/whatsapp atau https://.../webhook/kapso
     */
    public function index()
    {
        // 1. Tangani Webhook Verification (Meta / Kapso Challenge)
        if ($this->request->getMethod() === 'get' || $this->request->is('get')) {
            $hubChallenge = $this->request->getGet('hub_challenge') ?? $this->request->getGet('hub.challenge');
            if ($hubChallenge) {
                return $this->response->setBody($hubChallenge);
            }
            return $this->response->setJSON(['status' => 'ok', 'message' => 'WhatsApp Webhook is active']);
        }

        // 2. Tangani Inbound Webhook Event (POST)
        $rawJson = $this->request->getBody();

        // Log semua payload masuk untuk debugging
        log_message('info', '[Webhook WA] Payload masuk: ' . substr($rawJson ?? '', 0, 1000));

        if (empty($rawJson)) {
            return $this->response->setJSON(['status' => 'error', 'message' => 'Empty payload']);
        }

        $payload = json_decode($rawJson, true);
        if (!$payload) {
            log_message('error', '[Webhook WA] JSON tidak valid: ' . $rawJson);
            return $this->response->setJSON(['status' => 'error', 'message' => 'Invalid JSON']);
        }

        $waManager = WhatsAppManager::getInstance();
        $processed  = false;

        // ── Format A: Meta Cloud API (entry > changes > value > messages) ──────
        if (!$processed && isset($payload['entry'][0]['changes'][0]['value']['messages'][0])) {
            $entry   = $payload['entry'][0];
            $changes = $entry['changes'] ?? [];
            foreach ($changes as $change) {
                $messages = $change['value']['messages'] ?? [];
                foreach ($messages as $msg) {
                    $from = $msg['from'] ?? null;
                    $text = $msg['text']['body'] ?? ($msg['body'] ?? null);
                    $waId = $msg['id'] ?? null;
                    if ($from && $text) {
                        $waManager->recordIncomingMessage($from, $text, $waId, $payload);
                        $processed = true;
                    }
                }
            }
        }

        // ── Format B: Kapso event envelope (event.type = whatsapp.message.received) ─
        if (!$processed && isset($payload['event']['type']) && str_contains($payload['event']['type'], 'message')) {
            $data = $payload['data'] ?? [];
            $msg  = $data['message'] ?? $data;
            $from = $msg['from'] ?? ($data['conversation']['phone_number'] ?? null);
            $text = $msg['text']['body'] ?? ($msg['body'] ?? null);
            $waId = $msg['id'] ?? null;
            if ($from && $text) {
                $waManager->recordIncomingMessage($from, $text, $waId, $payload);
                $processed = true;
            }
        }

        // ── Format C: Kapso data.message (format lama / alternatif) ─────────────
        if (!$processed && isset($payload['data']['message'])) {
            $msg  = $payload['data']['message'];
            $from = $msg['from'] ?? ($payload['data']['conversation']['phone_number'] ?? null);
            $text = $msg['text']['body'] ?? ($msg['body'] ?? null);
            $waId = $msg['id'] ?? null;
            if ($from && $text) {
                $waManager->recordIncomingMessage($from, $text, $waId, $payload);
                $processed = true;
            }
        }

        // ── Format D: Kapso batch (data adalah array pesan) ─────────────────────
        if (!$processed && isset($payload['batch']) && is_array($payload['data'] ?? null)) {
            foreach ($payload['data'] as $item) {
                $msg  = $item['message'] ?? $item;
                $from = $msg['from'] ?? null;
                $text = $msg['text']['body'] ?? ($msg['body'] ?? null);
                $waId = $msg['id'] ?? null;
                if ($from && $text) {
                    $waManager->recordIncomingMessage($from, $text, $waId, $item);
                    $processed = true;
                }
            }
        }

        // ── Format E: Fonnte Webhook ─────────────────────────────────────────────
        if (!$processed && isset($payload['sender'], $payload['message'])) {
            $from = $payload['sender'];
            $text = $payload['message'];
            $waManager->recordIncomingMessage($from, $text, null, $payload);
            $processed = true;
        }

        // ── Format F: Kapso Cloud API (pesan di akar payload, arah di message.kapso.direction) ──
        if (!$processed && isset($payload['message']) && is_array($payload['message'])) {
            $msg   = $payload['message'];
            $kapso = is_array($msg['kapso'] ?? null) ? $msg['kapso'] : [];
            $arah  = $kapso['direction'] ?? null;
            $from  = $msg['from'] ?? null;

            // Kapso mengirim empat jenis event ke URL yang sama: pesan masuk
            // (direction=inbound, ada from), pesan keluar (direction=outbound),
            // lalu pembaruan status delivered/read (hanya ada to). Hanya pesan
            // masuk yang boleh tercatat, kalau tidak chat sendiri muncul sebagai
            // balasan pelanggan.
            $pesanMasuk = $arah === 'inbound' || ($arah === null && $from !== null && ! isset($msg['to']));

            if ($pesanMasuk && $from !== null) {
                $text = $msg['text']['body'] ?? ($msg['body'] ?? null);

                if ($text === null || $text === '') {
                    // gambar/dokumen/audio tetap tercatat sebagai satu giliran bicara
                    $text = '[pesan ' . ($msg['type'] ?? 'media') . ']';
                }

                $waManager->recordIncomingMessage((string) $from, (string) $text, isset($msg['id']) ? (string) $msg['id'] : null, $payload);
                $processed = true;
            } else {
                log_message('info', '[Webhook WA] event Kapso dilewati (direction=' . var_export($arah, true)
                    . ' status=' . ($kapso['status'] ?? '-') . ')');
            }
        }

        if (!$processed) {
            log_message('warning', '[Webhook WA] Format payload tidak dikenali: ' . json_encode(array_keys($payload)));
        }

        // Response 200 OK harus selalu dikirim cepat ke webhook provider
        return $this->response->setJSON(['status' => 'ok', 'processed' => $processed]);
    }
}
