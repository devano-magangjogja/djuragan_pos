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
     * Cari pelanggan yang pernah bertransaksi pada satu juragan.
     *
     * Versi lama menggabungkannya lewat JOIN + GROUP BY p.id_pelanggan +
     * HAVING i.juragan_id, dan MySQL 8 menolaknya (only_full_group_by) karena
     * SELECT i.* tidak bergantung pada kolom yang di-group. EXISTS memberi
     * hasil yang sama tanpa perlu GROUP BY.
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

        $builder->where('EXISTS (SELECT 1 FROM order_invoice i WHERE i.deleted_at IS NULL AND i.juragan_id = ' . (int) $juragan_id . ' AND (i.pemesan_id = p.id_pelanggan OR i.kirimKepada_id = p.id_pelanggan))', null, false);
        $builder->orderBy('p.nama_pelanggan');
        $builder->limit(10);

        return $builder->get();
    }
}
