<?php

namespace App\Models;

use CodeIgniter\Model;

class PelangganModel extends Model
{
    protected $table              = 'pelanggan';
    protected $primaryKey         = 'id_pelanggan';
    protected $returnType         = 'object';
    protected $useSoftDeletes     = true;
    protected $allowedFields      = ['nama_pelanggan', 'hp', 'cod', 'kecamatan', 'kabupaten', 'provinsi', 'alamat', 'kodepos'];
    protected $useTimestamps      = true;
    protected $createdField       = 'created_at';
    protected $updatedField       = 'updated_at';
    protected $deletedField       = 'deleted_at';
    protected $dateFormat         = 'int';
    protected $validationRules    = [];
    protected $validationMessages = [];
    protected $skipValidation     = false;

    /**
     * Ambil data dari tabel pelanggan berdasarkan id_pelanggan
     *
     * @param int $id_pelanggan Pelanggan ID
     */
    public function getSimple(int $id_pelanggan): object
    {
        return $this->select('id_pelanggan as id, nama_pelanggan as nama, hp, cod, kecamatan, kabupaten, provinsi, alamat, kodepos')
            ->where('id_pelanggan', $id_pelanggan)
            ->first();
    }

    public function ambil($id_pelanggan)
    {
        $builder = $this->db->table('pelanggan');
        $builder->where('id_pelanggan', $id_pelanggan);

        return $builder->get();
    }

    /**
     * Kondisi "pelanggan ini pernah dilayani di toko $juragan_id" sebagai
     * subquery EXISTS. Versi lama memakai JOIN + GROUP BY + HAVING yang
     * ditolak MySQL 8 (only_full_group_by).
     *
     * @param int|string $juragan_id
     */
    private function punyaJuragan($juragan_id): string
    {
        return 'EXISTS (SELECT 1 FROM order_invoice i WHERE i.deleted_at IS NULL AND i.juragan_id = '
            . (int) $juragan_id
            . ' AND (i.pemesan_id = p.id_pelanggan OR i.kirimKepada_id = p.id_pelanggan))';
    }

    /**
     * Cari pelanggan yang pernah bertransaksi pada satu juragan.
     *
     * @param int|string $juragan_id
     * @param string     $cari      nama atau nomor HP
     */
    public function cari($juragan_id, $cari)
    {
        $builder = $this->db->table($this->table . ' p');
        $builder->select('p.*');
        $builder->where('p.deleted_at', null);

        $cari = trim((string) $cari);

        if ($cari !== '') {
            // 'hp' disimpan sebagai JSON array, jadi angkanya saja yang berguna
            $hp = preg_replace('/\D+/', '', $cari);

            $builder->groupStart();
            $builder->like('p.nama_pelanggan', $cari, 'both');

            if ($hp !== '') {
                $builder->orLike('p.hp', $hp, 'both');
            }

            $builder->groupEnd();
        }

        $builder->where($this->punyaJuragan($juragan_id), null, false);
        $builder->orderBy('p.nama_pelanggan');
        $builder->limit(10);

        return $builder->get();
    }

    /**
     * Satu pelanggan, tetap dibatasi pada toko yang sedang dipakai, supaya
     * form edit tidak bisa menarik data pelanggan toko lain.
     *
     * @param int|string $juragan_id
     */
    public function satu($juragan_id, int $id_pelanggan)
    {
        return $this->db->table($this->table . ' p')
            ->select('p.*')
            ->where('p.deleted_at', null)
            ->where('p.id_pelanggan', $id_pelanggan)
            ->where($this->punyaJuragan($juragan_id), null, false)
            ->get()
            ->getRow();
    }
}
