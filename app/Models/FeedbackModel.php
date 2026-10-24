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
    protected $allowedFields    = ['id', 'nama', 'isi', 'prioritas', 'selesai'];

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
     * `$hanyaPrioritas` mempersempit ke baris bertanda prioritas.
     *
     * @return list<object>
     */
    public function daftar(string $cari = '', string $dari = '', string $sampai = '', int $limit = 0, int $offset = 0, bool $hanyaPrioritas = false): array
    {
        return $this->pedoman($cari, $dari, $sampai, $hanyaPrioritas)
            ->orderBy('created_at', 'DESC')
            ->orderBy('id', 'DESC')
            ->findAll($limit, $offset);
    }

    /** Jumlah masukan yang cocok dengan saringan yang sama. */
    public function jumlah(string $cari = '', string $dari = '', string $sampai = '', bool $hanyaPrioritas = false): int
    {
        return $this->pedoman($cari, $dari, $sampai, $hanyaPrioritas)->countAllResults();
    }

    /**
     * Pembatas yang sama untuk daftar dan jumlahnya. Instance baru dibuat tiap
     * panggilan karena where yang menempel pada model ikut terbawa ke panggilan
     * berikutnya, dan sekali dipakai untuk menghitung jumlah ia sudah tercemar.
     */
    private function pedoman(string $cari, string $dari, string $sampai, bool $hanyaPrioritas = false): self
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

        if ($hanyaPrioritas) {
            $model = $model->where('prioritas', 1);
        }

        if ($cari !== '') {
            $model = $model->groupStart()
                ->like('nama', $cari)
                ->orLike('isi', $cari)
                ->groupEnd();
        }

        return $model;
    }

    /**
     * Catat satu masukan prioritas (mis. permintaan taut juragan dari halaman 404).
     * Id 6 karakter dibuat dari waktu + seed supaya tidak bentrok, mengikuti pola
     * Masukan::kirim(); kolom id bukan auto-increment.
     */
    public function catatPrioritas(string $nama, string $isi): string
    {
        do {
            $id = substr(md5(microtime(true) . $nama . random_int(0, PHP_INT_MAX)), 0, 6);
        } while ($this->find($id) !== null);

        $this->save([
            'id'        => $id,
            'nama'      => $nama,
            'isi'       => $isi,
            'prioritas' => 1,
            'selesai'   => 0,
        ]);

        return $id;
    }

    /**
     * Berapa banyak prioritas yang masih menunggu (dipakai badge menu sidebar).
     * Yang sudah ditandai selesai tidak dihitung lagi.
     */
    public function hitungPrioritas(): int
    {
        return (new self())->where('prioritas', 1)->where('selesai', 0)->countAllResults();
    }

    /** Tandai satu masukan selesai/buka-lagi; hanya baris prioritas yang berubah. */
    public function tandaiSelesai(string $id, bool $selesai): int
    {
        return (int) (new self())
            ->set(['selesai' => $selesai ? 1 : 0])
            ->where('id', $id)
            ->where('prioritas', 1)
            ->update();
    }
}
