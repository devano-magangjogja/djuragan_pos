<?php

namespace App\Models;

use CodeIgniter\Model;

class PengirimanModel extends Model
{
    protected $table         = 'pengiriman';
    protected $primaryKey    = 'id_pengiriman';
    protected $returnType    = 'object';
    protected $allowedFields = ['invoice_id', 'kurir', 'ongkir', 'resi', 'qty_kirim', 'tanggal_kirim'];

    /**
     * Ambil data dari tabel pengiriman berdasarkan invoice_id
     *
     * @param int $invoice_id Invoice ID
     */
    public function getSimple(int $invoice_id): array
    {
        return $this->select('id_pengiriman as id, kurir, ongkir, resi, qty_kirim as qty, tanggal_kirim')
            ->where('invoice_id', $invoice_id)
            ->findAll();
    }
}
