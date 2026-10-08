<?php

namespace App\Libraries\WhatsApp;

use App\Libraries\WhatsApp\Contracts\WhatsAppProviderInterface;
use App\Libraries\WhatsApp\Providers\FonnteProvider;
use App\Libraries\WhatsApp\Providers\KapsoProvider;
use Config\Database;

class WhatsAppManager
{
    protected static ?self $instance = null;
    protected array $settings = [];
    protected array $providers = [];

    public function __construct()
    {
        $this->loadSettings();
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function loadSettings(): array
    {
        try {
            $db = Database::connect();
            if ($db->tableExists($db->prefixTable('crm_setting'))) {
                $rows = $db->table('crm_setting')->get()->getResultArray();
                foreach ($rows as $row) {
                    $this->settings[$row['setting_key']] = $row['setting_value'];
                }
            }
        } catch (\Throwable $e) {
            log_message('error', 'Error loading CRM settings: ' . $e->getMessage());
        }

        return $this->settings;
    }

    public function getSetting(string $key, ?string $default = null): ?string
    {
        return $this->settings[$key] ?? $default;
    }

    public function setSetting(string $key, ?string $value): void
    {
        $db = Database::connect();
        $builder = $db->table('crm_setting');
        $now = time();

        $exists = $builder->where('setting_key', $key)->countAllResults();
        if ($exists > 0) {
            $builder->where('setting_key', $key)->update([
                'setting_value' => $value,
                'updated_at'    => $now,
            ]);
        } else {
            $builder->insert([
                'setting_key'   => $key,
                'setting_value' => $value,
                'created_at'    => $now,
                'updated_at'    => $now,
            ]);
        }

        $this->settings[$key] = $value;
    }

    public function getActiveProviderName(): string
    {
        return $this->getSetting('wa_provider', 'fonnte');
    }

    public function getProvider(?string $name = null): WhatsAppProviderInterface
    {
        $providerName = strtolower($name ?: $this->getActiveProviderName());

        if (isset($this->providers[$providerName])) {
            return $this->providers[$providerName];
        }

        if ($providerName === 'fonnte') {
            $this->providers[$providerName] = new FonnteProvider([
                'token'    => $this->getSetting('fonnte_token', ''),
                'base_url' => $this->getSetting('fonnte_base_url', 'https://api.fonnte.com'),
            ]);
        } else {
            // Default Kapso
            $this->providers['kapso'] = new KapsoProvider([
                'api_key'         => $this->getSetting('kapso_api_key', ''),
                'phone_number_id' => $this->getSetting('kapso_phone_number_id', ''),
                'base_url'        => $this->getSetting('kapso_base_url', 'https://api.kapso.ai'),
            ]);
            $providerName = 'kapso';
        }

        return $this->providers[$providerName];
    }

    /**
     * Cek status perangkat Fonnte
     */
    public function getFonnteDeviceStatus(): array
    {
        $provider = $this->getProvider('fonnte');
        if ($provider instanceof FonnteProvider) {
            return $provider->getDeviceStatus();
        }
        return ['status' => false, 'message' => 'Provider Fonnte tidak tersedia'];
    }

    /**
     * Ambil QR Code Fonnte untuk pairing
     */
    public function getFonnteQr(): array
    {
        $provider = $this->getProvider('fonnte');
        if ($provider instanceof FonnteProvider) {
            return $provider->getQrCode();
        }
        return ['status' => false, 'message' => 'Provider Fonnte tidak tersedia'];
    }

    /**
     * Kirim pesan WhatsApp dan otomatis catat log ke crm_pesan_log
     */
    public function send(string $to, string $message, array $meta = []): array
    {
        $provider = $this->getProvider();
        $providerName = $provider->getName();

        $pelangganId = $meta['pelanggan_id'] ?? null;
        $invoiceId   = $meta['invoice_id'] ?? null;
        $tipePesan   = $meta['tipe_pesan'] ?? 'manual';
        $userId      = $meta['user_id'] ?? (is_cli() ? null : (session()->get('id') ?: null));

        // Jika mode sandbox aktif dan sandbox_test_number diatur (khusus Kapso)
        $sandboxMode = (int) $this->getSetting('kapso_sandbox_mode', '1');
        $sandboxTestNumber = $this->getSetting('kapso_sandbox_test_number', '6285161384750');

        $targetPhone = $to;
        $warningNotice = null;

        if ($providerName === 'kapso' && $sandboxMode === 1) {
            // Jika dalam sandbox dan nomor tujuan belum terdaftar di sandbox kapso,
            // beri opsi pengiriman ke sandbox test number atau tetap coba kirim
            if (!empty($meta['force_sandbox_number']) && !empty($sandboxTestNumber)) {
                $targetPhone = $sandboxTestNumber;
                $warningNotice = "Pesan dialihkan ke nomor sandbox {$sandboxTestNumber} (Tujuan asli: {$to})";
            }
        }

        $result = $provider->sendMessage($targetPhone, $message, $meta);
        $result['provider'] = $providerName;

        // Catat log pengiriman & sinkronisasi percakapan chat
        try {
            $db = Database::connect();
            $now = time();

            // Log riwayat
            $db->table('crm_pesan_log')->insert([
                'pelanggan_id'  => $pelangganId,
                'invoice_id'    => $invoiceId,
                'provider'      => $providerName,
                'nomor_tujuan'  => $targetPhone,
                'tipe_pesan'    => $tipePesan,
                'isi_pesan'     => $message . ($warningNotice ? "\n\n[Catatan: {$warningNotice}]" : ''),
                'status'        => $result['success'] ? 'terkirim' : 'gagal',
                'response_id'   => $result['message_id'] ?? null,
                'error_message' => $result['error'] ?? null,
                'user_id'       => $userId,
                'created_at'    => $now,
            ]);

            // Sinkronkan ke modul Live Chat jika pengiriman sukses
            if ($result['success']) {
                $this->syncChatOutgoing($targetPhone, $message, $result['message_id'] ?? null, $pelangganId, $userId);
            }
        } catch (\Throwable $e) {
            log_message('error', 'Gagal mencatat log CRM pesan: ' . $e->getMessage());
        }

        if ($warningNotice) {
            $result['notice'] = $warningNotice;
        }

        return $result;
    }

    /**
     * Simpan pesan keluar ke tabel chat percakapan
     */
    public function syncChatOutgoing(string $phone, string $message, ?string $waMsgId = null, ?int $pelangganId = null, ?int $userId = null): void
    {
        $db = Database::connect();
        $now = time();
        $cleanPhone = preg_replace('/[^\d]/', '', $phone);

        // Cari atau buat percakapan
        $conv = $db->table('crm_chat_percakapan')->where('nomor_wa', $cleanPhone)->get()->getRowArray();

        $namaKontak = null;
        if (!empty($pelangganId)) {
            $pRow = $db->table('pelanggan')->select('nama_pelanggan')->where('id_pelanggan', $pelangganId)->get()->getRowArray();
            $namaKontak = $pRow['nama_pelanggan'] ?? null;
        } elseif (!empty($conv['nama_kontak'])) {
            $namaKontak = $conv['nama_kontak'];
        } else {
            // Cari dari kontak pelanggan yang ada
            $kRow = $db->table('pelanggan_kontak k')
                ->select('p.id_pelanggan, p.nama_pelanggan')
                ->join('pelanggan p', 'p.id_pelanggan = k.pelanggan_id')
                ->like('k.nomor_pure', substr($cleanPhone, -9))
                ->get()->getRowArray();
            if ($kRow) {
                $pelangganId = $kRow['id_pelanggan'];
                $namaKontak = $kRow['nama_pelanggan'];
            }
        }

        if ($conv) {
            $convId = (int) $conv['id_percakapan'];
            $updateData = [
                'pesan_terakhir' => $message,
                'waktu_terakhir' => $now,
                'arah_terakhir'  => 'keluar',
                'updated_at'     => $now,
            ];
            if ($namaKontak && empty($conv['nama_kontak'])) {
                $updateData['nama_kontak'] = $namaKontak;
            }
            if ($pelangganId && empty($conv['pelanggan_id'])) {
                $updateData['pelanggan_id'] = $pelangganId;
            }
            $db->table('crm_chat_percakapan')->where('id_percakapan', $convId)->update($updateData);
        } else {
            $db->table('crm_chat_percakapan')->insert([
                'nomor_wa'       => $cleanPhone,
                'pelanggan_id'   => $pelangganId,
                'nama_kontak'    => $namaKontak ?: 'Kontak ' . substr($cleanPhone, -4),
                'pesan_terakhir' => $message,
                'waktu_terakhir' => $now,
                'arah_terakhir'  => 'keluar',
                'unread_admin'   => 0,
                'created_at'     => $now,
                'updated_at'     => $now,
            ]);
            $convId = $db->insertID();
        }

        // Tambah pesan
        $db->table('crm_chat_pesan')->insert([
            'percakapan_id' => $convId,
            'nomor_wa'      => $cleanPhone,
            'arah'          => 'keluar',
            'tipe'          => 'text',
            'isi_pesan'     => $message,
            'status'        => 'terkirim',
            'wa_message_id' => $waMsgId,
            'user_id'       => $userId,
            'created_at'    => $now,
        ]);
    }

    /**
     * Catat pesan masuk dari webhook WhatsApp (Kapso / Fonnte)
     */
    public function recordIncomingMessage(string $from, string $message, ?string $waMsgId = null, ?array $raw = null): array
    {
        $db = Database::connect();
        $now = time();
        $cleanPhone = preg_replace('/[^\d]/', '', $from);

        if (str_starts_with($cleanPhone, '0')) {
            $cleanPhone = '62' . substr($cleanPhone, 1);
        }

        // Gateway bisa mengirim ulang event yang sama (retry webhook), jadi satu
        // wa_message_id hanya boleh tercatat sekali sebagai pesan masuk
        if ($waMsgId !== null && $waMsgId !== '') {
            $sudah = $db->table('crm_chat_pesan')
                ->select('id_pesan, percakapan_id')
                ->where('wa_message_id', $waMsgId)
                ->where('arah', 'masuk')
                ->get()->getRowArray();

            if ($sudah !== null) {
                return [
                    'percakapan_id' => (int) $sudah['percakapan_id'],
                    'pesan_id'      => (int) $sudah['id_pesan'],
                    'duplikat'      => true,
                ];
            }
        }

        // Cari pelanggan dari database
        $kRow = $db->table('pelanggan_kontak k')
            ->select('p.id_pelanggan, p.nama_pelanggan')
            ->join('pelanggan p', 'p.id_pelanggan = k.pelanggan_id')
            ->like('k.nomor_pure', substr($cleanPhone, -9))
            ->get()->getRowArray();

        $pelangganId = $kRow['id_pelanggan'] ?? null;
        $namaKontak  = $kRow['nama_pelanggan'] ?? null;

        // Cari atau buat percakapan
        $conv = $db->table('crm_chat_percakapan')->where('nomor_wa', $cleanPhone)->get()->getRowArray();

        if ($conv) {
            $convId = (int) $conv['id_percakapan'];
            $db->table('crm_chat_percakapan')->where('id_percakapan', $convId)->update([
                'pesan_terakhir' => $message,
                'waktu_terakhir' => $now,
                'arah_terakhir'  => 'masuk',
                'unread_admin'   => 1,
                'updated_at'     => $now,
            ]);
        } else {
            $db->table('crm_chat_percakapan')->insert([
                'nomor_wa'       => $cleanPhone,
                'pelanggan_id'   => $pelangganId,
                'nama_kontak'    => $namaKontak ?: 'Kontak ' . substr($cleanPhone, -4),
                'pesan_terakhir' => $message,
                'waktu_terakhir' => $now,
                'arah_terakhir'  => 'masuk',
                'unread_admin'   => 1,
                'created_at'     => $now,
                'updated_at'     => $now,
            ]);
            $convId = $db->insertID();
        }

        // Tambah pesan masuk
        $db->table('crm_chat_pesan')->insert([
            'percakapan_id' => $convId,
            'nomor_wa'      => $cleanPhone,
            'arah'          => 'masuk',
            'tipe'          => 'text',
            'isi_pesan'     => $message,
            'status'        => 'diterima',
            'wa_message_id' => $waMsgId,
            'created_at'    => $now,
        ]);

        return [
            'percakapan_id' => $convId,
            'pesan_id'      => $db->insertID(),
        ];
    }
}
