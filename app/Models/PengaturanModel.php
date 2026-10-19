<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Setelan key-value aplikasi, menumpang tabel crm_setting yang sudah ada sejak
 * modul CRM dipasang (setting_key unik + setting_value TEXT).
 *
 * Notifikasi ikut menaruh pengaturannya di sini alih-alih membuat tabel baru:
 * isinya cuma beberapa kunci yang selalu dibaca utuh per layar, tidak pernah jadi
 * syarat query dari sisi lain — persis alasan tabel key-value itu dipilih untuk
 * pengaturan gateway WhatsApp.
 *
 * Tabelnya boleh tidak ada (instalasi lama yang belum lewat migrasi CRM), jadi
 * setiap baca jatuh ke nilai bawaan dan setiap tulis hanya dicatat ke log.
 */
class PengaturanModel extends Model
{
    protected $table          = 'crm_setting';
    protected $primaryKey     = 'id_setting';
    protected $returnType     = 'object';
    protected $allowedFields  = ['setting_key', 'setting_value', 'created_at', 'updated_at'];
    protected $useTimestamps  = true;
    protected $createdField   = 'created_at';
    protected $updatedField   = 'updated_at';
    protected $dateFormat     = 'int';

    /** Keberadaan tabel cukup diperiksa sekali per request. */
    private static ?bool $ada_tabel = null;

    public function tabelAda(): bool
    {
        return self::$ada_tabel ??= $this->db->tableExists($this->table);
    }

    /**
     * Beberapa kunci sekaligus, satu query. Dipakai layar yang membaca sekelompok
     * setelan berdekatan supaya pengaturannya tidak mahal dibaca.
     *
     * @param list<string> $keys
     *
     * @return array<string, ?string> nama kunci => nilai; kunci yang belum pernah
     *                                ditulis tidak muncul sama sekali
     */
    public function banyak(array $keys): array
    {
        if ($keys === [] || ! $this->tabelAda()) {
            return [];
        }

        $isi = [];

        foreach ($this->select(['setting_key', 'setting_value'])->whereIn('setting_key', $keys)->findAll() as $baris) {
            $isi[(string) $baris->setting_key] = $baris->setting_value === null ? null : (string) $baris->setting_value;
        }

        return $isi;
    }

    /** Simpan satu kunci: diselip kalau belum ada, ditimpa kalau sudah ada. */
    public function tulis(string $key, ?string $nilai): void
    {
        if (! $this->tabelAda()) {
            log_message('error', 'Tabel crm_setting tidak ada; setelan ' . $key . ' tidak tersimpan.');

            return;
        }

        $baris = $this->select(['id_setting'])->where('setting_key', $key)->first();

        if ($baris === null) {
            $this->insert(['setting_key' => $key, 'setting_value' => $nilai]);

            return;
        }

        $this->update((int) $baris->id_setting, ['setting_value' => $nilai]);
    }
}
