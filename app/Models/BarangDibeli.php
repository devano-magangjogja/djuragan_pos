<?php

namespace App\Models;

use CodeIgniter\Model;

class BarangDibeli extends Model
{
    protected $table            = 'dibeli';
    protected $primaryKey       = 'id_beli';
    protected $useAutoIncrement = true;
    protected $returnType       = 'object';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = ['invoice_id', 'stok_id', 'kode', 'ukuran', 'qty', 'harga', 'rincian'];

    /**
     * Ambil data dari tabel dibeli berdasarkan invoice_id
     *
     * @param int $invoice_id Invoice ID
     */
    public function getSimple(int $invoice_id): array
    {
        return $this->select('id_beli as id,kode, ukuran, qty, harga, rincian')
            ->where('invoice_id', $invoice_id)
            ->findAll();
    }
}
