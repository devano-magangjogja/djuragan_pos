<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Kotak masukan: kendala dan permintaan fitur yang dikirim CS dan Admin lewat
 * tombol melayang "Kirim Masukan" (lihat App\Controllers\Masukan).
 *
 * Halamannya hanya dibaca Superadmin, jadi di sini cuma ada pengambilan daftar
 * dan penghitungnya; tidak ada perubahan status karena tabelnya memang tidak
 * menyimpan apa pun selain pengirim, isi, dan waktu. Penyaringnya cukup kata
 * kunci dan rentang tanggal — nama pengirim sudah tertangkap oleh pencarian.
 */
class FeedbackModel extends Model
{
    protected $table            = 'feedback';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = false;
    protected $returnType       = 'object';
    protected $useSoftDeletes   = false;
    protected $protectFields    = false;
    protected $allowedFields    = ['id', 'nama', 'isi'];

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    /**
     * Daftar masukan, yang terbaru di atas.
     *
     * `$cari` menembak nama pengirim atau isi pesannya; `$dari` dan `$sampai`
     * membatasi rentang tanggal (format Y-m-d, salah satunya boleh kosong).
     *
     * @return list<object>
     */
    public function daftar(string $cari = '', string $dari = '', string $sampai = '', int $limit = 0, int $offset = 0): array
    {
        return $this->pedoman($cari, $dari, $sampai)
            ->orderBy('created_at', 'DESC')
            ->orderBy('id', 'DESC')
            ->findAll($limit, $offset);
    }

    /** Jumlah masukan yang cocok dengan saringan yang sama. */
    public function jumlah(string $cari = '', string $dari = '', string $sampai = ''): int
    {
        return $this->pedoman($cari, $dari, $sampai)->countAllResults();
    }

    /**
     * Pembatas yang sama untuk daftar dan jumlahnya. Instance baru dibuat tiap
     * panggilan karena where yang menempel pada model ikut terbawa ke panggilan
     * berikutnya, dan sekali dipakai untuk menghitung jumlah ia sudah tercemar.
     */
    private function pedoman(string $cari, string $dari, string $sampai): self
    {
        $model = new self();

        // batas bawah mulai tengah malam dan batas atas sampai detik terakhir,
        // supaya masukan pada hari "sampai" tetap ikut terbawa
        if ($dari !== '') {
            $model = $model->where('created_at >=', $dari . ' 00:00:00');
        }

        if ($sampai !== '') {
            $model = $model->where('created_at <=', $sampai . ' 23:59:59');
        }

        if ($cari !== '') {
            $model = $model->groupStart()
                ->like('nama', $cari)
                ->orLike('isi', $cari)
                ->groupEnd();
        }

        return $model;
    }
}
