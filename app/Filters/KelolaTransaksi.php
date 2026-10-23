<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Pintu masuk layar transaksi admin: Tracking Transaksi, sunting nota, panel
 * proses (tahap produksi), dan koreksi pembayaran.
 *
 * Admin dan superadmin selalu masuk. CS masuk hanya kalau Superadmin menyalakan
 * wewenang `sunting_transaksi` di akunnya; tanpa itu dia dikembalikan ke daftar
 * transaksinya sendiri, bukan ke halaman login, supaya tidak tampak seperti
 * akunnya rusak.
 *
 * Filter ini selalu dipakai setelah 'auth' pada grup rutenya, jadi tamu sudah
 * terblokir sebelum sampai di sini.
 */
class KelolaTransaksi implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        helper('fungsi');

        if (wewenang_sunting_transaksi()) {
            return;
        }

        return redirect()->to(site_url('user/invoices'));
    }

    /**
     * Tidak ada yang perlu diubah setelah respons dibuat.
     */
    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }
}
