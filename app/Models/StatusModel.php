<?php

namespace App\Models;

use CodeIgniter\Model;

class StatusModel extends Model
{
    protected $table            = 'invoice_status';
    protected $primaryKey       = 'id_status';
    protected $useAutoIncrement = true;
    protected $insertID         = 0;
    protected $returnType       = 'object';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = ['invoice_id', 'status', 'tanggal_masuk', 'tanggal_selesai', 'keterangan_masuk', 'keterangan_selesai'];

    /**
     * Ambil data dari tabel invoice_status berdasarkan invoice_id
     *
     * @param int $invoice_id Invoice ID
     */
    public function getSimple(int $invoice_id): array
    {
        return $this->select('id_status as id, status, tanggal_masuk, tanggal_selesai, keterangan_masuk, keterangan_selesai')
            ->where('invoice_id', $invoice_id)
            ->findAll();
    }
}
