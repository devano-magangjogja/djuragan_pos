<?php

namespace App\Models;

use CodeIgniter\Model;

class BankModel extends Model
{
    protected $table              = 'bank';
    protected $primaryKey         = 'id_bank';
    protected $returnType         = 'object';
    protected $useSoftDeletes     = true;
    protected $allowedFields      = ['nama_bank', 'tipe_bank', 'rekening', 'atas_nama'];
    protected $useTimestamps      = true;
    protected $createdField       = 'created_at';
    protected $updatedField       = 'updated_at';
    protected $deletedField       = 'deleted_at';
    protected $dateFormat         = 'int';
    protected $validationRules    = [];
    protected $validationMessages = [];
    protected $skipValidation     = false;
}
