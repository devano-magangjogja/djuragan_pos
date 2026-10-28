<?php

namespace App\Controllers;

use App\Models\InvoiceModel;
use App\Models\JuraganModel;
use App\Models\PengaturanModel;

/**
 * Reminder Penjahit — daftar orderan pembuatan/jual yang deadline-nya sudah dekat,
 * supaya tim sempat menghubungi penjahit sebelum tenggat lewat. Sengaja berdiri
 * sendiri (bukan bagian lonceng notifikasi) dan jadi menu prioritas di sidebar.
 *
 * Ambang "berapa hari sebelum deadline" hanya boleh diubah Superadmin; admin & CS
 * cukup melihat daftarnya.
 */
class Reminder extends BaseController
{
    public function index()
    {
        helper('fungsi');

        $hari  = pengingat_penjahit_hari();
        $level = (string) session()->get('level');

        $reminder = (new InvoiceModel())->pengingatPenjahit($this->tokoBoleh(), $hari);

        // tiap kartu mengarah ke layar transaksi yang sudah tersaring ke satu
        // nomor nota (cari[kolom]=faktur) lewat helper yang sama dengan lonceng
        // notifikasi, jadi mengklik reminder benar-benar berfokus ke invoice itu
        // saja — bukan daftar transaksi yang belum tersaring
        $slug_toko = [];

        foreach ((new JuraganModel())->select('id_juragan, juragan')->findAll() as $toko) {
            $slug_toko[(int) $toko->id_juragan] = (string) $toko->juragan;
        }

        foreach (array_keys($reminder) as $i) {
            $reminder[$i]['tautan'] = tautan_invoice_notif(
                (string) $reminder[$i]['seri'],
                $slug_toko[(int) $reminder[$i]['juragan_id']] ?? ''
            );
        }

        $data = [
            'title'      => 'Reminder Pesanan',
            'hari'       => $hari,
            'reminder'   => $reminder,
            'superadmin' => $level === 'superadmin',
        ];

        return view('reminder/index', $data);
    }

    public function jumlah()
    {
        helper('fungsi');

        return $this->response->setJSON([
            'status' => 'success',
            'count'  => pengingat_penjahit_jumlah(),
        ]);
    }

    /**
     * Simpan ambang hari reminder. Rutenya dipagari auth:superadmin, jadi hanya
     * Superadmin yang bisa sampai ke sini.
     */
    public function simpan()
    {
        helper('fungsi');

        $hari = max(0, min(90, (int) $this->request->getPost('hari')));

        (new PengaturanModel())->tulis('reminder_penjahit_hari', (string) $hari);

        return redirect()->to(site_url('admin/reminder'))->with('sukses', 'Pengaturan reminder tersimpan.');
    }
}
