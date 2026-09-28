<?php

namespace App\Models;

use CodeIgniter\Model;

class JuraganModel extends Model
{
    protected $table              = 'juragan';
    protected $primaryKey         = 'id_juragan';
    protected $returnType         = 'object';
    protected $useSoftDeletes     = true;
    protected $allowedFields      = ['juragan', 'nama_juragan'];
    protected $useTimestamps      = true;
    protected $createdField       = 'created_at';
    protected $updatedField       = 'updated_at';
    protected $deletedField       = 'deleted_at';
    protected $dateFormat         = 'int';
    protected $validationRules    = [];
    protected $validationMessages = [];
    protected $skipValidation     = false;

    /**
     * Ambil data dari tabel juragan berdasarkan juragan_id
     *
     * @param int $juragan_id Juragan ID
     */
    public function getSimple(int $juragan_id): object
    {
        return $this->select('juragan as slug, nama_juragan as nama')
            ->where('id_juragan', $juragan_id)
            ->first();
    }

    public function byUserId($user_id)
    {
        $builder = $this->db->table($this->table . ' j');
        $builder->select('j.*, u.id as user_id, u.name as nama_user, u.username, u.email, r.table');

        $builder->join('relasi r', 'r.juragan_id = j.id_juragan');
        $builder->join('user u', 'u.id = r.val_id');

        $builder->where('r.table', 1); // juragan-user
        $builder->having('u.id', $user_id);
        $builder->groupBy('u.id');

        $builder->groupBy('j.id_juragan');

        return $builder->get();
    }

    public function byInvoiceId($invoice_id)
    {
        $builder = $this->db->table($this->table . ' j');
        $builder->select('j.*,i.id_invoice');

        $builder->join('invoice i', 'i.juragan_id = j.id_juragan');
        $builder->where('i.id_invoice', $invoice_id);

        return $builder->get();
    }

    public function getUsers($ids_juragan)
    {
        if (is_array($ids_juragan)) {
            $builder = $this->db->table($this->table . ' j');
            $builder->select('j.*, u.*, r.table');

            $builder->join('relasi r', 'r.juragan_id = j.id_juragan', 'left');

            $builder->join('user u', 'u.id = r.val_id', 'both');
            $builder->havingIn('j.id_juragan', $ids_juragan);
            $builder->where('r.table', 1); // juragan-user
            $builder->groupBy('u.id');

            return $builder->get();
        }

        return false;
    }

    public function getUsersByJuragan($id)
    {
        $builder = $this->db->table($this->table . ' j');
        $builder->select('j.*, u.*, r.table');

        $builder->join('relasi r', 'r.juragan_id = j.id_juragan', 'left');

        $builder->join('user u', 'u.id = r.val_id', 'both');
        $builder->where('j.id_juragan', $id);
        $builder->where('r.table', 1); // juragan-user
        $builder->groupBy('u.id');

        return $builder->get();
    }

    public function ambil_bank($juragan_id)
    {
        $bank = $this->db->table($this->table . ' j');

        $bank->select('j.*, b.*, r.table');

        $bank->join('relasi r', 'r.juragan_id = j.id_juragan', 'left');
        $bank->join('bank b', 'b.id_bank = r.val_id', 'left');

        $bank->where('r.table', 2); // juragan-bank
        $bank->having('j.id_juragan', $juragan_id);

        $bank->orderBy('b.id_bank', 'DESC');

        return $bank->get();
    }

    public function terakhir_update($ids_juragan)
    {
        if (is_array($ids_juragan)) {
            $juragan = $this->db->table($this->table . ' j');
            $juragan->select('i.juragan_id as id_juragan, j.*');
            $juragan->join('relasi r', 'r.juragan_id = j.id_juragan', 'left');
            $juragan->join('user u', 'u.id = r.val_id', 'left');
            $juragan->join('invoice i', 'i.juragan_id = j.id_juragan', 'left');

            $juragan->havingIn('i.juragan_id', $ids_juragan);
            $juragan->orderBy('i.update_at', 'ASC');

            return $juragan->get()->getLastRow();
        }

        return ['error' => '$ids_juragan harus array'];
    }

    /**
     * Ambil data bank berdasarkan ID Juragan
     *
     * @param int $juragan_id ID Juragan
     *
     * @return array Bank lists array
     */
    public function getBank(int $juragan_id): array
    {
        $bankModel = new BankModel();

        $bankModel->select('id_bank as id, nama_bank as nama, rekening');

        return $bankModel->join('relasi', 'relasi.val_id=bank.id_bank AND relasi.table="2"')
            ->where('relasi.juragan_id', $juragan_id)->findAll();
    }
}
