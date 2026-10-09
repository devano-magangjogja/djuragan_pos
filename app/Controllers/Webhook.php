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

        $payload = null;
        if (!empty($rawJson)) {
            $payload = json_decode($rawJson, true);
        }

        if (!$payload) {
            $postData = $this->request->getPost();
            if (!empty($postData)) {
                $payload = $postData;
            }
        }

        if (empty($payload)) {
            return $this->response->setJSON(['status' => 'error', 'message' => 'Empty or invalid payload']);
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
        // Fonnte mengirim: pesan masuk, status update (read/delivered), pesan keluar.
        // Field 'sender' = nomor pengirim, 'message' = isi pesan, 'status' = event type.
        // 'name' = nama WhatsApp pengirim.
        if (!$processed && isset($payload['sender'])) {
            $fonnteStatus = $payload['status'] ?? null;
            $from         = $payload['sender'];
            $text         = $payload['message'] ?? null;
            $senderName   = $payload['name'] ?? null;

            // Jika ini adalah event status update pesan keluar (delivered/read)
            // Fonnte menyertakan field 'status' berisi 'read', 'receive', atau 'sent'
            // dan tidak ada isi 'message' bermakna
            if (in_array($fonnteStatus, ['read', 'receive', 'sent'], true)) {
                // Update status pesan di DB jika ada wa_message_id
                $waMsgId = $payload['id'] ?? null;
                if ($waMsgId) {
                    $statusMap = ['sent' => 'terkirim', 'receive' => 'diterima', 'read' => 'dibaca'];
                    $newStatus = $statusMap[$fonnteStatus] ?? null;
                    if ($newStatus) {
                        $waManager->updateMessageStatus((string) $waMsgId, $newStatus);
                    }
                }
                log_message('info', '[Webhook WA] Fonnte status update: ' . $fonnteStatus . ' id=' . ($waMsgId ?? '-'));
                $processed = true;

            } elseif (!empty($text)) {
                // Pesan masuk dari pelanggan
                $waId = $payload['id'] ?? null;
                $waManager->recordIncomingMessage(
                    (string) $from,
                    (string) $text,
                    $waId ? (string) $waId : null,
                    $payload,
                    $senderName ? (string) $senderName : null
                );
                $processed = true;
            } else {
                log_message('info', '[Webhook WA] Fonnte event tanpa isi pesan, status=' . ($fonnteStatus ?? '-'));
                $processed = true; // Jangan log sebagai error
            }
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

        // Jejak "kapan terakhir gateway mengetuk pintu" dipakai panel status di
        // Live Chat untuk membedakan gateway yang diam dari pesan yang nyangkut.
        // Payload kiriman tombol uji panel ditandai supaya tidak menyamar jadi
        // event gateway asli.
        $sumber = 'tak dikenal';
        if (isset($payload['selftest'])) {
            $sumber = 'uji-panel';
        } elseif (isset($payload['sender'])) {
            $sumber = 'fonnte';
        } elseif (isset($payload['entry'])) {
            $sumber = 'meta';
        } elseif (isset($payload['message']) || isset($payload['event']) || isset($payload['data']) || isset($payload['inactivity'])) {
            $sumber = 'kapso';
        }

        $nomorEvent = $payload['sender']
            ?? $payload['message']['from']
            ?? $payload['data']['message']['from']
            ?? $payload['entry'][0]['changes'][0]['value']['messages'][0]['from']
            ?? null;

        $waManager->catatEventWebhook($sumber, $nomorEvent !== null ? (string) $nomorEvent : null, $processed);

        // Response 200 OK harus selalu dikirim cepat ke webhook provider
        return $this->response->setJSON(['status' => 'ok', 'processed' => $processed]);
    }
}
