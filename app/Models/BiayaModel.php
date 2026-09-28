<?php

namespace App\Models;

use CodeIgniter\Model;

class BiayaModel extends Model
{
    protected $table            = 'biaya';
    protected $primaryKey       = 'id_biaya';
    protected $useAutoIncrement = true;
    protected $returnType       = 'object';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = ['invoice_id', 'biaya_id', 'nominal', 'label'];

    /**
     * Ambil data dari tabel biaya berdasarkan invoice_id
     *
     * @param int $invoice_id Invoice ID
     */
    public function getSimple(int $invoice_id): array
    {
        return $this->select('id_biaya as id, biaya_id, nominal, label')
            ->where('invoice_id', $invoice_id)
            ->findAll();
    }
}
