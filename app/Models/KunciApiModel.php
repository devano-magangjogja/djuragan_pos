<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Kunci API yang bisa diberikan ke pihak luar (mis. website partner).
 *
 * Yang dipakai untuk mencocokkan permintaan tetap hash SHA-256-nya. Sebagai
 * kenyaamanan bagi pemilik kunci yang suka kehilangan catatan, salinan mentahnya
 * ikut disimpan ter-enkripsi di kolom `token_enkrip` (kuncinya di .env, bukan di
 * basis data) dan hanya ditampilkan di layar kelola sebagai teks kabur yang
 * harus diklik dulu. Jadi salinan basis data telanjang tetap tidak langsung
 * memegang kunci yang masih jalan.
 *
 * `hak` dan `juragan` disimpan sebagai CSV karena keduanya selalu dibaca utuh
 * bersama barisnya (satu baris per permintaan) dan tidak pernah jadi syarat
 * query dari sisi lain. Angkanya dibersihkan di sini, bukan dipercayakan pada
 * peramban.
 */
class KunciApiModel extends Model
{
    /** Hak yang bisa dicentang, sekaligus kunci untuk rute /api/publik. */
    public const HAK = [
        'katalog' => 'Daftar barang + stok tersedia',
        'stok'    => 'Stok satu varian',
        'laporan' => 'Ringkasan penjualan',
        'lacak'   => 'Status orderan (butuh kode lacak)',
        // poin gaji itu angka internal (dasar hitung gaji pegawai), jadi dibuka
        // sendiri dan bukan bawaan hak katalog
        'poin'    => 'Poin gaji per barang + poin yang sudah masuk hitungan',
    ];

    /** Batas permintaan per hari bila pengguna tidak mengisi sendiri. */
    public const BATAS_BAKU = 500;

    protected $table          = 'kunci_api';
    protected $primaryKey     = 'id_kunci';
    protected $returnType     = 'object';
    protected $useSoftDeletes = true;
    protected $allowedFields  = ['nama', 'kunci', 'token_enkrip', 'hak', 'juragan', 'batas_harian', 'hidup', 'terakhir_pakai'];
    protected $useTimestamps  = true;
    protected $createdField   = 'created_at';
    protected $updatedField   = 'updated_at';
    protected $deletedField   = 'deleted_at';
    protected $dateFormat     = 'int';

    /**
     * Kunci baru: awalan jap_ supaya orang langsung tahu itu apa, 40 karakter
     * hex acak dari random_bytes (bukan microtime, yang bisa ditebak).
     *
     * @return array{kunci:string, hash:string, enkrip:string}
     */
    public static function baru(): array
    {
        $mentah = 'jap_' . bin2hex(random_bytes(20));

        return ['kunci' => $mentah, 'hash' => self::hash($mentah), 'enkrip' => self::enkrip($mentah)];
    }

    public static function hash(string $mentah): string
    {
        return hash('sha256', $mentah);
    }

    /** Salinan token mentah untuk disimpan, ter-enkripsi dengan kunci dari .env. */
    public static function enkrip(string $mentah): string
    {
        return bin2hex(\Config\Services::encrypter()->encrypt($mentah));
    }

    /**
     * Token mentah dari salinan ter-enkripnya, untuk layar "lihat kunci" di
     * halaman kelola. null = kunci lama yang dibuat sebelum salinan ini ada:
     * nilainya memang tidak pernah ditulis, jadi tidak bisa ditarik lagi.
     */
    public static function tokenAsli(?object $kunci): ?string
    {
        $isi = trim((string) ($kunci->token_enkrip ?? ''));

        if ($isi === '') {
            return null;
        }

        try {
            $mentah = \Config\Services::encrypter()->decrypt(hex2bin($isi));

            return $mentah === false || $mentah === '' ? null : $mentah;
        } catch (\Throwable) {
            // kunci .env berganti atau salinanya rusak: anggap tidak ada,
            // pemanggil akan menampilkan pesan kunci lama
            return null;
        }
    }

    /** Baris kunci yang masih boleh dipakai, dicari lewat hash token terkirim. */
    public function cari(string $mentah): ?object
    {
        if ($mentah === '') {
            return null;
        }

        $baris = $this->where('kunci', self::hash($mentah))->where('hidup', 1)->first();

        return $baris ?: null;
    }

    /** Daftar untuk layar kelola; deleted_at otomatis dibuang oleh soft delete. */
    public function daftar(): array
    {
        return $this->orderBy('id_kunci', 'DESC')->findAll();
    }

    /** CSV hak -> daftar nama hak yang dikenal saja. */
    public static function hak($kunci): array
    {
        $isi = array_intersect(
            array_map('trim', explode(',', (string) (is_object($kunci) ? $kunci->hak : $kunci))),
            array_keys(self::HAK)
        );

        return array_values($isi);
    }

    public static function boleh($kunci, string $hak): bool
    {
        return $hak === '' || in_array($hak, self::hak($kunci), true);
    }

    /** CSV toko -> id numerik unik, urutan dipertahankan. */
    public static function toko($kunci): array
    {
        $isi = array_values(array_unique(array_filter(array_map(
            'intval',
            explode(',', (string) (is_object($kunci) ? $kunci->juragan : $kunci))
        ))));

        return $isi;
    }

    public static function punyaToko($kunci, int $juragan_id): bool
    {
        return $juragan_id > 0 && in_array($juragan_id, self::toko($kunci), true);
    }

    /** Rapihkan kiriman form: hak dikenal + toko angka + batas masuk akal. */
    public static function siapan(array $kirim): array
    {
        $hak = array_values(array_intersect(
            array_map('strval', (array) ($kirim['hak'] ?? [])),
            array_keys(self::HAK)
        ));

        $toko = array_values(array_unique(array_filter(array_map('intval', (array) ($kirim['juragan'] ?? [])))));

        $batas = (int) ($kirim['batas_harian'] ?? self::BATAS_BAKU);
        $batas = $batas < 0 ? 0 : min($batas, 100000);

        return ['hak' => implode(',', $hak), 'juragan' => implode(',', $toko), 'batas_harian' => $batas];
    }

    /**
     * Kode lacak untuk orderan baru, format 4KXJ-9M2A supaya enak dibaca lewat
     * pesan; dipilih dari random_bytes agar tidak ikut mudah ditebak seperti
     * nomor nota.
     */
    public static function kodeBaru(): string
    {
        $huruf  = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $acak   = random_bytes(8);
        $kiri   = '';
        $kanan  = '';

        for ($i = 0; $i < 4; $i++) {
            $kiri  .= $huruf[ord($acak[$i]) % strlen($huruf)];
            $kanan .= $huruf[ord($acak[$i + 4]) % strlen($huruf)];
        }

        return $kiri . '-' . $kanan;
    }

    /**
     * Satu pemakaian dihitung untuk kunci ini hari ini; false artinya kuota habis.
     * Batas 0 berarti tanpa batas. Hitungannya disimpan di cache (bukan tabel log)
     * supaya tidak ada pekerjaan pembersihan dan tidak ada satu baris tulis ke
     * basis data untuk setiap permintaan.
     */
    public static function hemat(object $kunci): bool
    {
        $batas = (int) $kunci->batas_harian;

        if ($batas <= 0) {
            return true;
        }

        $cache = \Config\Services::cache();
        $nama  = 'pakai_api_' . (int) $kunci->id_kunci . '_' . date('Ymd');
        $sudah = (int) $cache->get($nama);

        if ($sudah >= $batas) {
            return false;
        }

        // sisa hari ini saja, supaya besok mulai dari nol lagi
        $sisa = 86400 - (time() - (int) floor(time() / 86400) * 86400);
        $cache->save($nama, $sudah + 1, max(60, $sisa));

        return true;
    }

    /** Ditulis maksimal sekali per menit per kunci, bukan tiap permintaan. */
    public function tandaiPakai(object $kunci): void
    {
        if ((int) $kunci->terakhir_pakai > time() - 60) {
            return;
        }

        $this->skipValidation()->protect(false)
            ->update((int) $kunci->id_kunci, ['terakhir_pakai' => time()]);
    }

    /** Cabut: barisnya tetap ada supaya jejaknya tidak hilang, kuncinya mati. */
    public function cabut(int $id_kunci): bool
    {
        return (bool) $this->protect(false)->update($id_kunci, ['hidup' => 0]);
    }

    /**
     * Kunci yang belum sekali pun dipakai sejak dibuat. Dipakai layar kelola untuk
     * menampung tombol bersih-bersih: kunci percobaan yang dibuat lalu dilupakan
     * jangan dibiarkan menumpuk di daftar.
     *
     * @return list<object> cukup id_kunci + nama
     */
    public function belumDipakai(): array
    {
        return $this->select(['id_kunci', 'nama'])->where('terakhir_pakai', null)->orderBy('id_kunci', 'DESC')->findAll();
    }

    /**
     * Hapus lunak. Bedanya dengan cabut(): barisnya hilang dari daftar dan dari
     * pencarian, jadi pemanggil yang masih membawa kunci itu ikut kena 401 seperti
     * kunci dicabut. Row-nya sendiri tidak dibuang — hash, cakupan toko, dan jejak
     * pemakaian terakhir tetap tertinggal di tabel sebagai arsip kalau suatu saat
     * perlu ditelusuri.
     *
     * @return int jumlah kunci yang terarsipkan
     */
    public function arsipkan(int ...$id_kunci): int
    {
        $id = array_values(array_filter($id_kunci));

        if ($id === []) {
            return 0;
        }

        // dihitung dari baris yang benar-benar berubah, bukan dari id yang dikirim:
        // kunci yang sudah terarsipkan sebelumnya tidak boleh terhitung dua kali
        return $this->delete($id) === false ? 0 : (int) $this->db->affectedRows();
    }
}
