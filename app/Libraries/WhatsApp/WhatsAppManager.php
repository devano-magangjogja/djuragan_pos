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

        } catch (\Throwable $e) {
            log_message('error', 'Gagal mencatat log CRM pesan: ' . $e->getMessage());
        }

        if ($warningNotice) {
            $result['notice'] = $warningNotice;
        }

        return $result;
    }

}
