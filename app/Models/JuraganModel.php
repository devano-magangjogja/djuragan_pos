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

    /**
     * Jumlah nota aktif per juragan. Dipakai sebagai peringatan sebelum
     * menghapus: nota lama tidak bisa dibuka lagi lewat slug tokonya.
     *
     * @return array<int, int> id_juragan => jumlah nota
     */
    public function jumlahOrderan(): array
    {
        $petakan = [];

        $hasil = $this->db->table('invoice')
            ->select('juragan_id, COUNT(*) AS jumlah')
            ->where('deleted_at', null)
            ->groupBy('juragan_id')
            ->get()
            ->getResultArray();

        foreach ($hasil as $h) {
            $petakan[(int) $h['juragan_id']] = (int) $h['jumlah'];
        }

        return $petakan;
    }

    public function byUserId($user_id)
    {
        $builder = $this->db->table($this->table . ' j');
        $builder->select('j.*, u.id as user_id, u.name as nama_user, u.username, u.email, r.table');

        $builder->join('relasi r', 'r.juragan_id = j.id_juragan');
        $builder->join('user u', 'u.id = r.val_id');

        // hasil diambil lewat get() yang menembus query builder, jadi scope soft
        // delete harus dipasang sendiri supaya toko yang dihapus tidak ikut muncul
        $builder->where('j.deleted_at', null);

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

    /**
     * Admin dan CS yang tertaut pada tiap toko, untuk layar penanggung jawab.
     * Hanya id dan nama yang dibawa keluar; kolom akun lain (termasuk sandi)
     * tidak boleh sampai ke view.
     *
     * @param array<int> $juragan_ids kosong = semua toko
     *
     * @return array<int, array{admin: array<int, array{id:int, nama:string}>, cs: array<int, array{id:int, nama:string}>}>
     */
    public function pengelola(array $juragan_ids = []): array
    {
        $b = $this->db->table('relasi r');
        $b->select('r.juragan_id, u.id as user_id, u.name as nama, u.level');
        $b->join('user u', 'u.id = r.val_id');
        $b->where('r.table', 1); // juragan-user
        $b->whereIn('u.level', ['admin', 'cs']);

        if ($juragan_ids !== []) {
            $b->whereIn('r.juragan_id', array_map('intval', $juragan_ids));
        }

        $b->orderBy('u.name', 'ASC');

        $return = [];

        foreach ($b->get()->getResult() as $r) {
            $level = $r->level === 'cs' ? 'cs' : 'admin';

            $return[(int) $r->juragan_id][$level][] = [
                'id'   => (int) $r->user_id,
                'nama' => (string) $r->nama,
            ];
        }

        return $return;
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
        if (! is_array($ids_juragan)) {
            return ['error' => '$ids_juragan harus array'];
        }

        if ($ids_juragan === []) {
            return null;
        }

        // relasi + user tidak ikut di-join lagi: keduanya LEFT JOIN tanpa penyaring, jadi
        // tiap orderan terkali jumlah relasi juragan pemiliknya (akun tertaut 21 juragan
        // menyeret 444.347 baris ke memori). Pemanggil hanya membaca kolom juragan + id_juragan.
        $juragan = $this->db->table($this->table . ' j');
        $juragan->select('i.juragan_id as id_juragan, j.*');
        $juragan->join('invoice i', 'i.juragan_id = j.id_juragan', 'left');

        $juragan->whereIn('i.juragan_id', $ids_juragan);
        // dulu ASC + getLastRow(); sama-sama jatuh di update_at terbesar, tapi DESC + limit 1
        // hanya memindahkan satu baris dari database, bukan seluruh hasilnya
        $juragan->orderBy('i.update_at', 'DESC');

        return $juragan->limit(1)->get()->getRow();
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
