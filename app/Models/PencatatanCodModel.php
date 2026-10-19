<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Pencatatan COD: kunjungan/transaksi customer yang tidak menjadi orderan.
 *
 * Hapus bersifat lunak. Baris yang hilang dari daftar masih ada di tabel, jadi
 * kalau sebuah catatan ternyata terhapus oleh klik yang salah, isinya masih bisa
 * diperiksa dan dikembalikan.
 */
class PencatatanCodModel extends Model
{
    protected $table            = 'pencatatan_cod';
    protected $primaryKey       = 'id_cod';
    protected $returnType       = 'array';
    protected $useSoftDeletes   = true;
    protected $allowedFields    = [
        'tanggal', 'jam', 'nama_customer', 'no_hp',
        'keperluan_id', 'status_id', 'catatan', 'cs_id', 'sumber_id', 'user_id',
    ];
    protected $useTimestamps    = true;
    protected $createdField     = 'created_at';
    protected $updatedField     = 'updated_at';
    protected $deletedField     = 'deleted_at';
    protected $dateFormat       = 'int';

    /**
     * Hitung total catatan sesuai filter.
     */
    public function hitung(array $filter = []): int
    {
        $builder = $this->terang();
        $this->pasangFilter($builder, $filter);

        return $builder->countAllResults();
    }

    /**
     * Total semua catatan yang tidak dihapus (opsional filter non-status).
     */
    public function totalSemua(array $filterLain = []): int
    {
        unset($filterLain['status']);
        if ($filterLain === []) {
            return $this->where('deleted_at IS NULL')->countAllResults();
        }

        $builder = $this->builder()->where($this->table . '.deleted_at IS NULL');
        if (! empty($filterLain['cari'])) {
            $builder->join('pencatatan_pilihan p_k', 'p_k.id = ' . $this->table . '.keperluan_id', 'left')
                ->join('pencatatan_pilihan p_s', 'p_s.id = ' . $this->table . '.status_id', 'left')
                ->join('pencatatan_pilihan p_c', 'p_c.id = ' . $this->table . '.cs_id', 'left');
        }
        $this->pasangFilter($builder, $filterLain);

        return $builder->countAllResults();
    }

    /**
     * Hitung jumlah catatan untuk tiap status transaksi (hanya yang tidak dihapus).
     *
     * @return array<int, int> [status_id => jumlah]
     */
    public function hitungPerStatus(array $filterLain = []): array
    {
        unset($filterLain['status']);
        $builder = $this->builder()
            ->select($this->table . '.status_id, COUNT(*) AS jumlah')
            ->where($this->table . '.deleted_at IS NULL')
            ->where($this->table . '.status_id IS NOT NULL');

        if (! empty($filterLain['cari'])) {
            $builder->join('pencatatan_pilihan p_k', 'p_k.id = ' . $this->table . '.keperluan_id', 'left')
                ->join('pencatatan_pilihan p_s', 'p_s.id = ' . $this->table . '.status_id', 'left')
                ->join('pencatatan_pilihan p_c', 'p_c.id = ' . $this->table . '.cs_id', 'left');
        }

        $this->pasangFilter($builder, $filterLain);

        $baris = $builder->groupBy($this->table . '.status_id')->get()->getResultArray();

        $hasil = [];
        foreach ($baris as $b) {
            $hasil[(int) $b['status_id']] = (int) $b['jumlah'];
        }

        return $hasil;
    }

    /**
     * Daftar catatan untuk halaman kartu, beserta label pilihannya dan filter.
     *
     * Keempat pilihan dan nama pencatat digabung lewat LEFT JOIN, bukan dibaca
     * dari id saja: kalau labelnya ternyata sudah hilang dari tabel master,
     * kartunya tetap menampilkan catatan lengkap dengan kategori kosong, bukan
     * menghilang dari daftar.
     *
     * @return list<array<string, mixed>>
     */
    public function daftar(int $mulai, int $batas, array $filter = []): array
    {
        $builder = $this->terang();
        $this->pasangFilter($builder, $filter);

        return $builder
            ->orderBy('pencatatan_cod.tanggal', 'DESC')
            ->orderBy('pencatatan_cod.id_cod', 'DESC')
            ->limit($batas, $mulai)
            ->findAll();
    }

    /**
     * Terapkan filter pencarian teks, status, CS, keperluan, dan tanggal.
     */
    private function pasangFilter($builder, array $filter = [])
    {
        if (! empty($filter['cari'])) {
            $cari = trim((string) $filter['cari']);
            $builder->groupStart()
                ->like('pencatatan_cod.nama_customer', $cari)
                ->orLike('pencatatan_cod.no_hp', $cari)
                ->orLike('pencatatan_cod.catatan', $cari)
                ->orLike('p_c.label', $cari)
                ->orLike('p_s.label', $cari)
                ->orLike('p_k.label', $cari)
                ->groupEnd();
        }

        if (! empty($filter['status'])) {
            $builder->where('pencatatan_cod.status_id', (int) $filter['status']);
        }

        if (! empty($filter['cs'])) {
            $builder->where('pencatatan_cod.cs_id', (int) $filter['cs']);
        }

        if (! empty($filter['keperluan'])) {
            $builder->where('pencatatan_cod.keperluan_id', (int) $filter['keperluan']);
        }

        if (! empty($filter['dari'])) {
            $builder->where('pencatatan_cod.tanggal >=', $filter['dari']);
        }

        if (! empty($filter['sampai'])) {
            $builder->where('pencatatan_cod.tanggal <=', $filter['sampai']);
        }

        return $builder;
    }

    /** Satu catatan beserta label pilihannya, untuk mengisi modal sunting. */
    public function satu(int $idCod): ?array
    {
        $baris = $this->terang()->where('pencatatan_cod.id_cod', $idCod)->findAll();

        return $baris[0] ?? null;
    }

    /**
     * Berapa catatan yang menunjuk satu pilihan master, dipisah menurut
     * kondisinya: [tampil => n, hapus => n].
     *
     * Bedanya penting. Semua kolom pilihan ber-foreign key RESTRICT, jadi baris
     * yang sudah dihapus lunak pun masih menahan baris master-nya di mata MySQL —
     * tapi secara manusiawi catatannya sudah dibuang, jadi pilihannya boleh
     * dilepas lebih dulu lewat lepaskan().
     */
    public function dipakai(int $idPilihan): array
    {
        $kosong = ['tampil' => 0, 'hapus' => 0];

        if ($idPilihan < 1) {
            return $kosong;
        }

        foreach ($this->pemakaianKolom() as $baris) {
            foreach ($baris as $satu) {
                if ((int) $satu['id_pilihan'] === $idPilihan) {
                    $kosong['tampil'] += (int) $satu['tampil'];
                    $kosong['hapus']  += (int) $satu['hapus'];
                }
            }
        }

        return $kosong;
    }

    /**
     * Pemakai tiap pilihan master sekaligus: [id => ['tampil' => n, 'hapus' => n]].
     *
     * Dipakai halaman kartu supaya tong sampah yang memang tidak bisa ditekan
     * terlihat mati beserta alasannya, alih-alih dibiarkan menekan tombol yang
     * pasti ditolak.
     *
     * @return array<int, array{tampil: int, hapus: int}>
     */
    public function terpakai(): array
    {
        $hitung = [];

        foreach ($this->pemakaianKolom() as $baris) {
            foreach ($baris as $satu) {
                $id   = (int) $satu['id_pilihan'];
                $awal = $hitung[$id] ?? ['tampil' => 0, 'hapus' => 0];

                $hitung[$id] = [
                    'tampil' => $awal['tampil'] + (int) $satu['tampil'],
                    'hapus'  => $awal['hapus'] + (int) $satu['hapus'],
                ];
            }
        }

        return $hitung;
    }

    /**
     * Lepas pilihan dari catatan yang sudah dihapus lunak.
     *
     * Wajib dipanggil sebelum baris master-nya dihapus: selama satu baris
     * tersembunyi masih menyimpan id-nya, RESTRICT menolak DELETE sama sekali.
     *
     * @return int berapa baris catatan yang ikut dilepas
     */
    public function lepaskan(int $idPilihan): int
    {
        if ($idPilihan < 1) {
            return 0;
        }

        $lepas = 0;

        foreach (['keperluan_id', 'status_id', 'cs_id', 'sumber_id'] as $kolom) {
            $this->db->table($this->table)
                ->where($kolom, $idPilihan)
                ->where('deleted_at IS NOT NULL', null, false)
                ->update([$kolom => null]);

            $lepas += $this->db->affectedRows();
        }

        return $lepas;
    }

    /**
     * Jumlah catatan per pilihan untuk tiap kolom pilihan, sekali baca tabel.
     *
     * withDeleted() + SUM(CASE …) dipakai supaya angka "masih tampil" dan "sudah
     * dihapus" berasal dari pembacaan yang sama, bukan dua query yang bisa
     * berbeda hasil kalau ada yang menyimpan catatan di antaranya.
     *
     * @return array<string, list<array{id_pilihan: string, tampil: string, hapus: string}>>
     */
    private function pemakaianKolom(): array
    {
        $hasil = [];

        foreach (['keperluan_id', 'status_id', 'cs_id', 'sumber_id'] as $kolom) {
            $hasil[$kolom] = $this->withDeleted()
                ->select($kolom . ' AS id_pilihan')
                ->select('SUM(CASE WHEN deleted_at IS NULL THEN 1 ELSE 0 END) AS tampil', false)
                ->select('SUM(CASE WHEN deleted_at IS NOT NULL THEN 1 ELSE 0 END) AS hapus', false)
                ->where($kolom . ' IS NOT NULL', null, false)
                ->groupBy($kolom)
                ->get()
                ->getResultArray();
        }

        return $hasil;
    }

    /** Query dasar catatan + label pilihannya + nama pencatat. */
    private function terang()
    {
        return $this->select('pencatatan_cod.*')
            ->select('p_k.label AS keperluan, p_s.label AS status_transaksi')
            ->select('p_c.label AS cs, p_i.label AS sumber, u.name AS pencatat')
            ->join('pencatatan_pilihan p_k', 'p_k.id = pencatatan_cod.keperluan_id', 'left')
            ->join('pencatatan_pilihan p_s', 'p_s.id = pencatatan_cod.status_id', 'left')
            ->join('pencatatan_pilihan p_c', 'p_c.id = pencatatan_cod.cs_id', 'left')
            ->join('pencatatan_pilihan p_i', 'p_i.id = pencatatan_cod.sumber_id', 'left')
            ->join('user u', 'u.id = pencatatan_cod.user_id', 'left');
    }
}
