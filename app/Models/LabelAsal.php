<?php

namespace App\Models;

use CodeIgniter\Model;

class LabelAsal extends Model
{
    protected $table            = 'asal_order';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $insertID         = 0;
    protected $returnType       = 'object';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = ['label', 'status'];
}
