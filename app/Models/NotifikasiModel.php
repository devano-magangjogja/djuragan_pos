<?php

namespace App\Models;

use CodeIgniter\Model;

class NotifikasiModel extends Model
{
    /**
     * Kunci simpanan pengaturan kebersihan di crm_setting (lihat PengaturanModel).
     * `terakhir` menyimpan waktu percobaan sapuan terakhir — bukan hanya sapuan
     * yang menemukan sesuatu — supaya polling lonceng tidak mengulang kerja yang
     * sama tiap 10 detik.
     */
    public const S_AKTIF    = 'notif_auto_aktif';
    public const S_UMUR     = 'notif_auto_umur_hari';
    public const S_TERAKHIR = 'notif_auto_terakhir';
    public const S_JUMLAH   = 'notif_auto_jumlah';

    /** Batas umur notifikasi terbaca yang akan disapu, dalam hari. */
    public const UMUR_BAKU = 30;
    public const UMUR_MIN  = 1;
    public const UMUR_MAKS = 365;

    /**
     * Jeda antar sapuan otomatis, dalam jam. Endpoint lonceng dipanggil polling,
     * jadi satu percobaan per beberapa jam cukup untuk menjaga tabelnya kecil
     * tanpa menulis berulang kali pada permintaan yang sama.
     */
    public const JEDA_SAPU = 6;

    protected $table      = 'notifikasi';
    protected $primaryKey = 'id_notifikasi';
    protected $returnType = 'object';
    // protected $useSoftDeletes = true;

    protected $allowedFields = ['juragan_id', 'from', 'for', 'invoice_id', 'type', 'read_at', 'created_at'];

    public function ambil($user_id, $dibaca = false, $limit = null, $offset = null)
    {
        $notif = $this->db->table($this->table . ' n');
        $notif->select('i.seri, n.*, p.name, j.juragan');

        $notif->join('invoice i', 'n.invoice_id = i.id_invoice', 'LEFT');
        $notif->join('user p', 'n.from = p.id', 'LEFT');
        $notif->join('juragan j', 'j.id_juragan = n.juragan_id', 'LEFT');
        $notif->where('n.for', $user_id);

        // $dibaca === null berarti "semua": halaman daftar lengkap memang ingin
        // melihat yang sudah terbaca sekaligus yang belum
        if ($dibaca === null) {
            // tanpa filter read_at
        } elseif ($dibaca) {
            $notif->where('n.read_at !=', null);
        } else {
            $notif->where('n.read_at', null);
        }

        if ($limit !== null && $offset !== null) {
            $notif->limit($limit, $offset);
        }

        $notif->orderBy('n.created_at', 'DESC');

        return $notif;
    }

    // ------------------------------------------------------------------
    // kebersihan kotak notifikasi (pengaturan: Admin & Superadmin)
    // ------------------------------------------------------------------

    /**
     * Isi pengaturan saat ini, sudah dibersihkan.
     *
     * Belum pernah disimpan apa pun = otomatis mati + batas umur bawaan. Itu
     * sengaja: instalasi baru tidak boleh menghapus riwayat sebelum ada manusia
     * yang memutuskan batasnya.
     *
     * @return array{aktif:bool, umur_hari:int, terakhir:?int, jumlah:int}
     */
    public function setelan(): array
    {
        $isi      = (new PengaturanModel())->banyak([self::S_AKTIF, self::S_UMUR, self::S_TERAKHIR, self::S_JUMLAH]);
        $terakhir = $this->angkaDari($isi[self::S_TERAKHIR] ?? null, 0);

        return [
            'aktif'     => ($isi[self::S_AKTIF] ?? '0') === '1',
            'umur_hari' => $this->umurSah($this->angkaDari($isi[self::S_UMUR] ?? null, self::UMUR_BAKU)),
            // 0 bukan waktu, artinya belum pernah menyapu sama sekali
            'terakhir'  => $terakhir === 0 ? null : $terakhir,
            'jumlah'    => $this->angkaDari($isi[self::S_JUMLAH] ?? null, 0),
        ];
    }

    /** Angka dari kiriman crm_setting; kosong atau bukan angka = bawaan. */
    private function angkaDari(?string $nilai, int $baku): int
    {
        return $nilai === null || ! is_numeric($nilai) ? $baku : (int) $nilai;
    }

    /** Simpan pengaturan; pengembalian = umur yang benar-benar dipakai. */
    public function simpanSetelan(bool $aktif, int $umur_hari): int
    {
        $umur    = $this->umurSah($umur_hari);
        $setelan = new PengaturanModel();

        $setelan->tulis(self::S_AKTIF, $aktif ? '1' : '0');
        $setelan->tulis(self::S_UMUR, (string) $umur);

        return $umur;
    }

    /** Batas umur dipaksa masuk akal; kiriman peramban tidak pernah dipercaya. */
    public function umurSah(int $umur): int
    {
        return max(self::UMUR_MIN, min($umur, self::UMUR_MAKS));
    }

    public function hitungTerbaca(?int $untuk = null): int
    {
        return $this->terbaca($untuk)->countAllResults();
    }

    public function hitungBelum(?int $untuk = null): int
    {
        return $this->belum($untuk)->countAllResults();
    }

    /**
     * Berapa banyak yang akan hilang pada sapuan otomatis berikutnya. Dipakai layar
     * pengaturan sebagai perkiraan sebelum orang menekan tombol.
     */
    public function usang(int $umur_hari): int
    {
        $potong = time() - $this->umurSah($umur_hari) * DAY;

        return $this->terbaca()->where('created_at <', $potong)->countAllResults();
    }

    /**
     * Hapus manual semua yang sudah terbaca. Tanpa `$untuk` seluruh kotak milik
     * semua akun disapu (itu memang isi layar pengaturan); dengan `$untuk` hanya
     * kotak satu akun.
     *
     * @return int jumlah baris yang benar-benar terhapus
     */
    public function hapusTerbaca(?int $untuk = null): int
    {
        $this->terbaca($untuk)->delete();

        return (int) $this->db->affectedRows();
    }

    /**
     * Hapus satu notifikasi. Pemiliknya dibaca dari kolom `for`, bukan dari id yang
     * dikirim peramban, dan yang belum dibaca tidak boleh hilang sebelum sempat
     * dibuka — notifikasi itu masih punya tugas.
     */
    public function hapusSatu(int $id_notifikasi, int $untuk): int
    {
        $this->where('id_notifikasi', $id_notifikasi)
            ->where('for', $untuk)
            ->where('read_at !=', null)
            ->delete();

        return (int) $this->db->affectedRows();
    }

    /**
     * Jalan otomatis: dipanggil dari halaman daftar dan endpoint lonceng, bukan
     * dari cron. Pengaturannya mati atau percobaan terakhir masih hangat? Langsung
     * balik tanpa menyentuh tabel notifikasi.
     *
     * @return int jumlah baris terhapus (0 kalau belum waktunya)
     */
    public function sapuJadwal(): int
    {
        $setelan = $this->setelan();

        if (! $setelan['aktif']) {
            return 0;
        }

        if ($setelan['terakhir'] !== null && $setelan['terakhir'] > time() - self::JEDA_SAPU * HOUR) {
            return 0;
        }

        return $this->sapuUsang($setelan['umur_hari']);
    }

    /**
     * Buang notifikasi yang sudah terbaca DAN berumur lebih tua dari batasnya.
     * Umur dihitung sejak notifikasinya dibuat, bukan sejak dibaca: yang baru
     * dibaca kemarin tapi catatannya sudah seumur batas memang sudah selesai
     * tugasnya. Yang belum dibaca tidak pernah ikut tersapu.
     *
     * Umur tersimpan di pengaturan dipakai kalau tidak diisi sendiri, supaya
     * perintah CLI dan layar pengaturan tidak punya dua versi aturan.
     */
    public function sapuUsang(?int $umur_hari = null): int
    {
        $umur   = $this->umurSah($umur_hari ?? $this->setelan()['umur_hari']);
        $potong = time() - $umur * DAY;

        $this->terbaca()->where('created_at <', $potong)->delete();
        $jumlah = (int) $this->db->affectedRows();

        $this->catatSapu($jumlah);

        return $jumlah;
    }

    /**
     * Dicatat walau hasilnya nol: layar pengaturan boleh jujur menyebut kapan
     * terakhir bersih-bersih, dan jeda berikutnya dihitung dari percobaan itu.
     */
    private function catatSapu(int $jumlah): void
    {
        $setelan = new PengaturanModel();

        $setelan->tulis(self::S_TERAKHIR, (string) time());
        $setelan->tulis(self::S_JUMLAH, (string) $jumlah);
    }

    /**
     * Baris terbaca, boleh dipersempit ke satu pemilik kotak. Dibangun lewat method
     * ini setiap kali dipakai karena countAllResults() me-reset query builder, jadi
     * syarat hitungan dan penghapusannya tidak bisa dititip satu kali.
     */
    private function terbaca(?int $untuk = null)
    {
        $pencarian = $this->where('read_at !=', null);

        return $untuk === null ? $pencarian : $pencarian->where('for', $untuk);
    }

    /** Baris yang belum dibaca; tidak pernah jadi sasaran penghapusan. */
    private function belum(?int $untuk = null)
    {
        $pencarian = $this->where('read_at', null);

        return $untuk === null ? $pencarian : $pencarian->where('for', $untuk);
    }
}
