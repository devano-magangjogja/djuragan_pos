<?php

namespace App\Models;

use CodeIgniter\Model;

class LabelInvoice extends Model
{
    protected $table            = 'label_invoice';
    // protected $table            = 'order_label_invoice';
    protected $primaryKey       = 'id_label';
    protected $useAutoIncrement = true;
    protected $insertID         = 0;
    protected $returnType       = 'object';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = ['invoice_id', 'source_id', 'label'];

    /**
     * Ambil data dari tabel label_invoice berdasarkan invoice_id
     *
     * @param int $invoice_id Invoice ID
     */
    public function getSimple( $invoice_id): ?object
    {
        return $this->select('label, source_id as id')
            ->where('invoice_id', $invoice_id)
            ->first();
    }
    
    
}
