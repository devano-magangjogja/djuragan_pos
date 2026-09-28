<?php

namespace App\Models;

use CodeIgniter\Model;

class UserModel extends Model
{
    protected $table = 'user';
    protected $primaryKey = 'id';
    protected $returnType = 'object';
    protected $useSoftDeletes = true;
    protected $allowedFields = ['username', 'password', 'name', 'email', 'level', 'status', 'login_terakhir'];
    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
    protected $deletedField = 'deleted_at';
    protected $dateFormat = 'int';
    protected $validationRules = [];
    protected $validationMessages = [];
    protected $skipValidation = false;

    /**
     * Ambil data dari tabel user berdasarkan user_id
     *
     * @param int $user_id User ID
     */
    public function getSimple(int $user_id): object
    {
        return $this->select('id, username, name as nama')
            ->where('id', $user_id)
            ->first();
    }

    public function ambil()
    {
        $builder = $this->db->table('user u');
        $builder->select('u.*');

        $builder->select('CONCAT("[" ,GROUP_CONCAT(DISTINCT CONCAT("{","&quot;id&quot;:",j.id_juragan,",","&quot;nama&quot;:&quot;",j.nama_juragan,"&quot;,","&quot;link&quot;:&quot;",j.juragan,"&quot;}")),"]") as juragan');

        $builder->join('relasi r', 'r.val_id = u.id', 'left');
        $builder->join('juragan j', 'j.id_juragan = r.juragan_id', 'left');
        $builder->where('r.table', '1');

        $builder->groupBy('u.id');

        return $builder;
    }
}
