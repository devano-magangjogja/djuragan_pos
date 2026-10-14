<?php

namespace App\Models;

use CodeIgniter\Model;

class NotifikasiModel extends Model
{
    protected $table      = 'notifikasi';
    protected $primaryKey = 'id_notifikasi';
    protected $returnType = 'object';
    // protected $useSoftDeletes = true;

    protected $allowedFields = ['juragan_id', 'from', 'for', 'invoice_id', 'type', 'read_at', 'created_at'];

    public function ambil($user_id, $dibaca = false, $limit = null, $offset = null)
    {
        $notif = $this->db->table($this->table . ' n');
        $notif->select('i.seri, n.*, p.name, j.juragan');

        $notif->join('invoice i', 'n.invoice_id = i.id_invoice', 'LEFT');
        $notif->join('user p', 'n.from = p.id', 'LEFT');
        $notif->join('juragan j', 'j.id_juragan = n.juragan_id', 'LEFT');
        $notif->where('n.for', $user_id);

        // $dibaca === null berarti "semua": halaman daftar lengkap memang ingin
        // melihat yang sudah terbaca sekaligus yang belum
        if ($dibaca === null) {
            // tanpa filter read_at
        } elseif ($dibaca) {
            $notif->where('n.read_at !=', null);
        } else {
            $notif->where('n.read_at', null);
        }

        if ($limit !== null && $offset !== null) {
            $notif->limit($limit, $offset);
        }

        $notif->orderBy('n.created_at', 'DESC');

        return $notif;
    }
}
