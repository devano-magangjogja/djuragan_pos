<?php

namespace App\Models;

use CodeIgniter\Model;

class PembayaranModel extends Model
{
    protected $table         = 'pembayaran';
    protected $primaryKey    = 'id_pembayaran';
    protected $returnType    = 'object';
    protected $allowedFields = ['invoice_id', 'sumber_dana', 'total_pembayaran', 'status', 'tanggal_pembayaran', 'tanggal_cek'];

    /**
     * Ambil data dari tabel pembayaran berdasarkan invoice_id
     *
     * @param int $invoice_id Invoice ID
     */
    public function getSimple(int $invoice_id): array
    {
        return $this->select('pembayaran.id_pembayaran as id, pembayaran.sumber_dana as sumber, bank.nama_bank as bank, bank.atas_nama, pembayaran.total_pembayaran as nominal, pembayaran.status, pembayaran.tanggal_pembayaran as tanggal_bayar, pembayaran.tanggal_cek')
            ->join('bank', 'bank.id_bank=pembayaran.sumber_dana')
            ->where('invoice_id', $invoice_id)
            ->findAll();
    }

    // ambil data pembayaran
    public function ambil($invoice_id)
    {
        $bayar = $this->db->table('pembayaran p');
        $bayar->select('p.id_pembayaran as id, p.sumber_dana as sumber, bn.nama_bank as nama, bn.atas_nama, p.total_pembayaran as nominal, p.status, p.tanggal_pembayaran, p.tanggal_cek');
        $bayar->join('bank bn', 'bn.id_bank = p.sumber_dana', 'left outer');

        $bayar->where('p.invoice_id', $invoice_id);

        return $bayar;
    }
}
