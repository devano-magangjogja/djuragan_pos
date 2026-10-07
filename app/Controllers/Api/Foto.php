<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Models\FotoModel;
use CodeIgniter\HTTP\Files\UploadedFile;

/**
 * Foto orderan: unggah, tampilkan, hapus.
 *
 * Semuanya lewat jalur API yang sudah dikunci filter auth (admin/superadmin/CS),
 * jadi foto tidak pernah bisa dibuka pelanggan maupun tamu. Kepemilikan toko
 * dicek per nota lewat pagarnya BaseController, sama seperti halaman invoice:
 * id nota atau id foto milik toko lain dijawab "bukan milikmu", bukan data.
 *
 * Berkas sengaja tidak diletakkan di public/. Satu-satunya jalan membacanya
 * lewat saji(), sehingga tidak ada celah membuka foto dengan menebak nama berkas.
 */
class Foto extends BaseController
{
    /**
     * POST api/foto/unggah/<id_invoice>
     * Berkas datang dari form yang sudah tersimpan, jadi orderan harus lebih dulu ada.
     */
    public function unggah($invoice_id)
    {
        $nota = $this->notaMilikToko((int) $invoice_id);

        if ($nota === null) {
            return $this->tolakToko();
        }

        $berkas = $this->berkas();

        if ($berkas === []) {
            $this->response->setStatusCode(406);

            return $this->response->setJSON(['status' => 'Tidak ada foto yang ikut terkirim.']);
        }

        $foto    = new FotoModel();
        $user_id = (int) session()->get('id');

        $tersimpan = [];
        $ditolak   = [];

        foreach ($berkas as $satu) {
            [$baris, $galat] = $foto->terima($satu, (int) $nota->id_invoice, $user_id);

            if ($galat !== null) {
                $ditolak[] = $galat;

                continue;
            }

            $tersimpan[] = self::siapTampil($baris);
        }

        if ($tersimpan === []) {
            $this->response->setStatusCode(406);

            return $this->response->setJSON(['status' => $ditolak[0] ?? 'Foto gagal diunggah, coba lagi ya.']);
        }

        return $this->response->setJSON([
            'status'    => 'foto tersimpan',
            'foto'      => $tersimpan,
            'galat'     => $ditolak,
            'jumlah'    => $foto->jumlah((int) $nota->id_invoice),
            'maks'      => FotoModel::MAKS_FOTO,
        ]);
    }

    /**
     * GET api/foto/saji/<id_foto> — satu-satunya jalan membaca berkas foto.
     * Tamu tidak pernah sampai sini karena route-nya ada di grup API terpagar.
     */
    public function saji($id_foto)
    {
        $foto = (new FotoModel())->find((int) $id_foto);

        if ($foto === null || $this->notaMilikToko((int) $foto['invoice_id']) === null) {
            $this->response->setStatusCode(404);

            return $this->response->setJSON(['status' => 'Foto tidak ditemukan.']);
        }

        $jalan = $this->kirim($foto);

        if ($jalan === null) {
            $this->response->setStatusCode(404);

            return $this->response->setJSON(['status' => 'Foto tidak ditemukan.']);
        }

        return $this->response
            // berkas biner: charset tidak ikut ditempel supaya header benar apa adanya
            ->setContentType(FotoModel::mime($foto['tipe']), '')
            ->setHeader('Content-Length', (string) filesize($jalan))
            // disimpan sebentar di peramban, tapi tidak boleh ikut ter-cache CDN
            ->setHeader('Cache-Control', 'private, max-age=3600')
            ->setBody((string) file_get_contents($jalan));
    }

    /**
     * POST api/foto/hapus/<id_foto> — baris dan berkasnya pergi berdua.
     */
    public function hapus($id_foto)
    {
        $foto = (new FotoModel())->find((int) $id_foto);

        if ($foto === null) {
            $this->response->setStatusCode(404);

            return $this->response->setJSON(['status' => 'Foto tidak ditemukan.']);
        }

        if ($this->notaMilikToko((int) $foto['invoice_id']) === null) {
            return $this->tolakToko();
        }

        (new FotoModel())->buang((int) $foto['id_foto']);

        return $this->response->setJSON([
            'status' => 'foto terhapus',
            'jumlah' => (new FotoModel())->jumlah((int) $foto['invoice_id']),
        ]);
    }

    /**
     * Foto siap tampil untuk sisi browser: data baris + alamat gambarnya.
     *
     * @param array<string, mixed>|null $baris
     *
     * @return array<string, mixed>
     */
    private static function siapTampil(?array $baris): array
    {
        $baris ??= [];

        return array_merge($baris, [
            'alamat'       => FotoModel::alamat((int) ($baris['id_foto'] ?? 0)),
            'alamat_kecil' => FotoModel::alamat((int) ($baris['id_foto'] ?? 0), 480),
        ]);
    }

    /**
     * Berkas yang dikirim: thumbnail kalau ?s= minta ukuran yang dikenal, kalau
     * tidak berkas aslinya. Null berarti barisnya ada tapi berkasnya tidak.
     */
    private function kirim(array $foto): ?string
    {
        $sisi = (int) $this->request->getVar('s');

        if ($sisi > 0) {
            $kecil = FotoModel::thumb($foto, $sisi);

            if ($kecil !== null) {
                return $kecil;
            }
        }

        return FotoModel::jalur($foto['berkas']);
    }

    /**
     * Berkas dari permintaan. Nama field-nya `foto[]`, dan PHP menampung field
     * berbentuk array sebagai array di dalam array, jadi datarnya begini.
     *
     * @return list<UploadedFile>
     */
    private function berkas(): array
    {
        $dapat = [];

        foreach ($this->request->getFiles() as $kumpulan) {
            foreach (is_array($kumpulan) ? $kumpulan : [$kumpulan] as $satu) {
                if ($satu instanceof UploadedFile) {
                    $dapat[] = $satu;
                }
            }
        }

        return $dapat;
    }
}
