<?php

namespace App\Controllers;

use App\Models\InvoiceModel;
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

        $hari = pengingat_penjahit_hari();

        $data = [
            'title'      => 'Reminder Penjahit',
            'hari'       => $hari,
            'reminder'   => (new InvoiceModel())->pengingatPenjahit($this->tokoBoleh(), $hari),
            'superadmin' => (string) session()->get('level') === 'superadmin',
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
