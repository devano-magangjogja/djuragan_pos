<?php

namespace App\Libraries\WhatsApp;

use App\Libraries\WhatsApp\Contracts\WhatsAppProviderInterface;
use App\Libraries\WhatsApp\Providers\FonnteProvider;
use App\Libraries\WhatsApp\Providers\KapsoProvider;
use App\Models\CrmModel;
use Config\Database;

class WhatsAppManager
{
    /**
     * Nomor WA toko bawaan. Satu-satunya tempat nomor ini ditulis di source
     * code; semuanya membacanya lewat nomorToko() supaya server lokal dan
     * hosting tidak berakhir memakai nomor yang berbeda.
     */
    public const NOMOR_TOKO = '6285161384750';

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

    /**
     * Rekam event webhook terakhir yang sampai ke server. Panel status di Live
     * Chat membacanya untuk membedakan "gateway tidak pernah mengirim" dari
     * "pesan masuk tapi tidak tampil". Disimpan di crm_setting yang sudah
     * key-value, jadi tidak butuh tabel atau kolom baru.
     */
    public function catatEventWebhook(string $sumber, ?string $nomor, bool $diproses): void
    {
        try {
            $this->setSetting('wa_webhook_event_terakhir', json_encode([
                'waktu'    => time(),
                'sumber'   => $sumber,
                'nomor'    => $nomor,
                'diproses' => $diproses,
            ], JSON_UNESCAPED_SLASHES));
        } catch (\Throwable $e) {
            log_message('error', 'Gagal mencatat event webhook: ' . $e->getMessage());
        }
    }

    public function eventWebhookTerakhir(): ?array
    {
        $mentah = $this->getSetting('wa_webhook_event_terakhir');

        if (! is_string($mentah) || $mentah === '') {
            return null;
        }

        $event = json_decode($mentah, true);

        return is_array($event) && isset($event['waktu']) ? $event : null;
    }

    /**
     * Nomor WhatsApp toko sendiri — satu sumber kebenaran untuk seluruh modul
     * WhatsApp. Urut prioritas: setelan CRM (CRm > Pengaturan), .env
     * (wa.nomorToko), lalu bawaan di kelas ini. Sebelumnya nomor ini ditulis di
     * lima tempat dengan tiga nilai berbeda, sehingga habis pull/push aplikasi
     * bisa berakhir memakai nomor lain.
     */
    public function nomorToko(): string
    {
        foreach ([
            trim((string) $this->getSetting('wa_nomor_toko', '')),
            trim((string) env('wa.nomorToko', '')),
            self::NOMOR_TOKO,
        ] as $calon) {
            $bersih = preg_replace('/[^\d]/', '', $calon);
            if (strlen($bersih) >= 9) {
                return $bersih;
            }
        }

        return self::NOMOR_TOKO;
    }

    /**
     * Nomor perangkat (nomor toko) yang ikut terkirim dalam payload gateway.
     * Fonnte mengirimnya di field device/username; Kapso dan Meta tidak
     * menyertakan nomor bisnis, jadi null.
     */
    public static function nomorPerangkatDari(?array $payload): ?string
    {
        if ($payload === null) {
            return null;
        }

        foreach (['device', 'username'] as $kunci) {
            $nilai = $payload[$kunci] ?? null;
            if (! is_string($nilai)) {
                continue;
            }
            $bersih = preg_replace('/[^\d]/', '', $nilai);
            if (strlen($bersih) >= 9) {
                return $bersih;
            }
        }

        return null;
    }

    /**
     * Rekam nomor toko dari payload gateway kalau setelan masih kosong, supaya
     * webhook langsung punya pembanding untuk mengenali gema pesan keluar.
     * Setelan yang sudah diisi manual tidak pernah ditimpa.
     */
    public function pelajariNomorToko(?array $payload): void
    {
        if (trim((string) $this->getSetting('wa_nomor_toko', '')) !== '') {
            return;
        }

        $nomor = self::nomorPerangkatDari($payload);

        if ($nomor !== null && $nomor !== self::NOMOR_TOKO) {
            $this->setSetting('wa_nomor_toko', $nomor);
        }
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
        $sandboxTestNumber = $this->getSetting('kapso_sandbox_test_number', $this->nomorToko());

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
     * Cocokkan nomor WhatsApp dengan pelanggan memakai aturan yang sama dengan
     * modul CRM (CrmModel::cariCustomerByWa: persis, kedua ragam 62... dan 08...).
     * Dulu pustaka ini mencocokan 9 digit terakhir sendiri, sehingga chat bisa
     * tersambat ke pelanggan lain yang nomornya mirip — termasuk nomor toko
     * sendiri, yang lalu muncul sebagai "pelanggan" di daftar Live Chat.
     */
    private function cariPelangganDariNomor(string $nomor): ?array
    {
        $pelanggan = model(CrmModel::class)->cariCustomerByWa($nomor);

        if ($pelanggan === null) {
            return null;
        }

        return [
            'id_pelanggan'   => $pelanggan['id_pelanggan'],
            'nama_pelanggan' => $pelanggan['nama_pelanggan'],
        ];
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
            $kRow = $this->cariPelangganDariNomor($cleanPhone);
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
     *
     * @param string      $from        Nomor pengirim
     * @param string      $message     Isi pesan
     * @param string|null $waMsgId     ID pesan dari gateway (untuk dedup)
     * @param array|null  $raw         Payload mentah
     * @param string|null $senderName  Nama kontak dari WhatsApp (dari Fonnte 'name' field)
     */
    public function recordIncomingMessage(string $from, string $message, ?string $waMsgId = null, ?array $raw = null, ?string $senderName = null): array
    {
        $db = Database::connect();
        $now = time();
        $cleanPhone = preg_replace('/[^\d]/', '', $from);

        if (str_starts_with($cleanPhone, '0')) {
            $cleanPhone = '62' . substr($cleanPhone, 1);
        }

        $this->pelajariNomorToko($raw);

        // Fonnte mengirim ulang pesan yang toko kirim lewat API sebagai event
        // dengan sender = nomor toko sendiri. Kalau ikut dicatat, riwayat toko
        // muncul sebagai percakapan pelanggan baru di Live Chat.
        if ($cleanPhone !== '' && $cleanPhone === $this->nomorToko()) {
            log_message('info', '[Webhook WA] Gema pesan nomor toko sendiri diabaikan: ' . $cleanPhone);

            return [
                'percakapan_id' => 0,
                'pesan_id'      => 0,
                'diabaikan'     => 'nomor toko sendiri',
            ];
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
        $kRow = $this->cariPelangganDariNomor($cleanPhone);

        $pelangganId = $kRow['id_pelanggan'] ?? null;
        $namaKontak  = $kRow['nama_pelanggan'] ?? null;

        // Jika tidak ada di database pelanggan, gunakan nama dari WhatsApp (Fonnte)
        if (empty($namaKontak) && !empty($senderName)) {
            $namaKontak = $senderName;
        }

        // Cari atau buat percakapan
        $conv = $db->table('crm_chat_percakapan')->where('nomor_wa', $cleanPhone)->get()->getRowArray();

        if ($conv) {
            $convId = (int) $conv['id_percakapan'];
            $updateData = [
                'pesan_terakhir' => $message,
                'waktu_terakhir' => $now,
                'arah_terakhir'  => 'masuk',
                'unread_admin'   => 1,
                'updated_at'     => $now,
            ];
            // Perbarui nama_kontak jika belum ada nama asli (masih nomor/kosong) dan sekarang ada nama
            $namaLama = $conv['nama_kontak'] ?? '';
            if (!empty($namaKontak) && (empty($namaLama) || preg_match('/^\d+$/', $namaLama))) {
                $updateData['nama_kontak'] = $namaKontak;
            }
            // Link pelanggan jika belum ter-link
            if (!empty($pelangganId) && empty($conv['pelanggan_id'])) {
                $updateData['pelanggan_id'] = $pelangganId;
            }
            $db->table('crm_chat_percakapan')->where('id_percakapan', $convId)->update($updateData);
        } else {
            $db->table('crm_chat_percakapan')->insert([
                'nomor_wa'       => $cleanPhone,
                'pelanggan_id'   => $pelangganId,
                'nama_kontak'    => $namaKontak ?: $cleanPhone,
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

    /**
     * Update status pengiriman pesan (terkirim/diterima/dibaca) dari callback Fonnte.
     * Fonnte mengirim wa_message_id = id pesan yang statusnya berubah.
     */
    public function updateMessageStatus(string $waMsgId, string $status): bool
    {
        $db = Database::connect();

        // Validasi status yang diizinkan
        $validStatus = ['terkirim', 'diterima', 'dibaca', 'gagal'];
        if (!in_array($status, $validStatus, true)) {
            return false;
        }

        // Update baris pertama yang cocok (arah keluar saja)
        $rows = $db->table('crm_chat_pesan')
            ->where('wa_message_id', $waMsgId)
            ->where('arah', 'keluar')
            ->get()
            ->getResultArray();

        if (empty($rows)) {
            log_message('info', '[WhatsApp] updateMessageStatus: wa_message_id tidak ditemukan: ' . $waMsgId);
            return false;
        }

        $updated = $db->table('crm_chat_pesan')
            ->where('wa_message_id', $waMsgId)
            ->where('arah', 'keluar')
            ->update(['status' => $status]);

        log_message('info', '[WhatsApp] updateMessageStatus: ' . $waMsgId . ' -> ' . $status);
        return (bool) $updated;
    }
}
