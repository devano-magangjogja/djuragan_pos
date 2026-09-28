<?php

namespace App\Models;

use CodeIgniter\Model;

class Cities extends Model
{
    protected $table            = 'cities';
    protected $primaryKey       = 'city_id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'object';
    protected $protectFields    = true;
    protected $allowedFields    = [
        'city_id',
        'province_id',
        'city_name',
        'postal_code',
    ];
}
