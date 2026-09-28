<?php

namespace App\Models;

use CodeIgniter\Model;

class Province extends Model
{
    protected $table            = 'provinces';
    protected $primaryKey       = 'province_id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'object';
    protected $protectFields    = true;
    protected $allowedFields    = ['province_id', 'province_name'];
}
