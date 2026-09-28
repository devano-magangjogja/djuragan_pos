<?php

namespace App\Models;

use CodeIgniter\Model;

class Subdistricts extends Model
{
    protected $table            = 'subdistricts';
    protected $primaryKey       = 'subdistrict_id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'object';
    protected $protectFields    = true;
    protected $allowedFields    = [
        'subdistrict_id',
        'city_id',
        'subdistrict_name',
    ];
}
