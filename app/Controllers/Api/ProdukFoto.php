<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Models\ProdukFotoModel;
use App\Models\StokModel;
use CodeIgniter\HTTP\Files\UploadedFile;

/**
 * Foto produk katalog: unggah, tampilkan, hapus.
 *
 * Perilakunya sengaja disamakan dengan foto orderan (Api\Foto): berkas tidak
 * pernah diletakkan di public/, satu-satunya jalan membacanya lewat saji(), dan
 * kepemilikan toko dicek per barang lewat pagarnya BaseController. Jadi id varian
 * atau id foto milik toko lain dijawab "bukan milikmu", bukan data.
 *
 * Membaca foto boleh juga oleh CS karena form tulis orderan menampilkan contoh
 * bentuk barang; yang menulis (unggah/hapus) hanya Admin dan superadmin, sama
 * seperti halaman Produk.
 *
 * Panel kelola fotonya dikirim sebagai fragmen HTML dari foto_panel_form(),
 * supaya halaman Produk tidak perlu menyalin markup panel yang sudah ada di
 * form orderan.
 */
class ProdukFoto extends BaseController
{
    /**
     * GET api/produk-foto/saji/<id_foto> — satu-satunya jalan membaca berkas foto.
     * ?s= minta ukuran thumbnail yang dikenal (240 / 480).
     */
    public function saji($id_foto)
    {
        $foto = $this->foto((int) $id_foto);

        if ($foto === null) {
            return $this->galat(404, 'Foto tidak ditemukan.');
        }

        $jalan = $this->kirim($foto);

        if ($jalan === null) {
            return $this->galat(404, 'Foto tidak ditemukan.');
        }

        return $this->response
            // berkas biner: charset tidak ikut ditempel supaya header benar apa adanya
            ->setContentType(ProdukFotoModel::mime($foto['tipe']), '')
            ->setHeader('Content-Length', (string) filesize($jalan))
            // disimpan sebentar di peramban, tapi tidak boleh ikut ter-cache CDN
            ->setHeader('Cache-Control', 'private, max-age=3600')
            ->setBody((string) file_get_contents($jalan));
    }

    /**
     * GET api/produk-foto/kelola/<id_stok> — panel foto satu kartu produk.
     *
     * Kartu katalog cuma menyimpan satu tombol, isinya baru diambil waktu
     * tombolnya ditekan: halaman Produk bisa memuat 20 kartu dan tidak semuanya
     * butuh daftar foto.
     */
    public function kelola($id_stok)
    {
        $varian = $this->varian((int) $id_stok);

        if ($varian === null) {
            return $this->galat(404, 'Barang tidak ditemukan.');
        }

        $model = new ProdukFotoModel();
        $foto  = $model->untukKartu($this->sekartu($varian));

        return $this->response->setBody(foto_panel_form($foto, (int) $varian->id_stok, 'produk'));
    }

    /**
     * GET api/produk-foto/baca/<id_stok> — daftar foto sekartu untuk form orderan.
     *
     * Panel kelola() khusus Admin karena di sana fotonya bisa ditulis; form
     * tulis orderan hanya perlu melihatnya supaya CS tahu bentuk barangnya, jadi
     * jawabannya JSON kecil tanpa tombol hapus.
     */
    public function baca($id_stok)
    {
        $varian = $this->varian((int) $id_stok);

        if ($varian === null) {
            return $this->galat(404, 'Barang tidak ditemukan.');
        }

        $foto = array_map(
            static fn (array $f): array => [
                'id_foto'      => (int) $f['id_foto'],
                'nama'         => (string) ($f['nama_asli'] ?? ''),
                'alamat'       => ProdukFotoModel::alamat((int) $f['id_foto']),
                'alamat_kecil' => ProdukFotoModel::alamat((int) $f['id_foto'], 240),
            ],
            (new ProdukFotoModel())->untukKartu($this->sekartu($varian))
        );

        return $this->response->setJSON([
            'stok_id' => (int) $varian->id_stok,
            'kode'    => (string) $varian->kode,
            'ukuran'  => (string) ($varian->ukuran ?? ''),
            'foto'    => $foto,
        ]);
    }

    /**
     * POST api/produk-foto/unggah/<id_stok>
     * Fotonya nempel ke varian yang dibuka panelnya, tapi kartunya menampilkan
     * gabungan sekartu, jadi ukuran lain ikut keisi tanpa unggah ulang.
     */
    public function unggah($id_stok)
    {
        $varian = $this->varian((int) $id_stok);

        if ($varian === null) {
            return $this->tolakToko();
        }

        $berkas = $this->berkas();

        if ($berkas === []) {
            return $this->galat(406, 'Tidak ada foto yang ikut terkirim.');
        }

        $foto    = new ProdukFotoModel();
        $user_id = (int) session()->get('id');

        $tersimpan = [];
        $ditolak   = [];

        foreach ($berkas as $satu) {
            [$baris, $galat] = $foto->terima($satu, (int) $varian->id_stok, $user_id);

            if ($galat !== null) {
                $ditolak[] = $galat;

                continue;
            }

            $tersimpan[] = self::siapTampil($baris);
        }

        if ($tersimpan === []) {
            return $this->galat(406, $ditolak[0] ?? 'Foto gagal diunggah, coba lagi ya.');
        }

        return $this->response->setJSON([
            'status'    => 'foto tersimpan',
            'foto'      => $tersimpan,
            'galat'     => $ditolak,
            'jumlah'    => $foto->jumlah((int) $varian->id_stok),
            'maks'      => ProdukFotoModel::MAKS_FOTO,
        ]);
    }

    /**
     * POST api/produk-foto/hapus/<id_foto> — baris dan berkasnya pergi berdua.
     */
    public function hapus($id_foto)
    {
        $foto = $this->foto((int) $id_foto);

        if ($foto === null) {
            return $this->galat(404, 'Foto tidak ditemukan.');
        }

        $model  = new ProdukFotoModel();
        $pemilik = (int) $foto['stok_id'];
        $model->buang((int) $foto['id_foto']);

        return $this->response->setJSON([
            'status' => 'foto terhapus',
            'jumlah' => $model->jumlah($pemilik),
        ]);
    }

    /**
     * Satu varian, hanya kalau tokonya memang dipegang akun ini.
     * null menjawab dua hal sekaligus: barisnya tidak ada (atau sudah dihapus),
     * atau itu barang toko lain.
     */
    private function varian(int $id_stok): ?object
    {
        $baris = (new StokModel())->find($id_stok);

        if ($baris === null || ! $this->bolehToko((int) $baris->juragan_id)) {
            return null;
        }

        return $baris;
    }

    /** Foto + tokonya; foto milik toko lain atau varian yang sudah hilang: null. */
    private function foto(int $id_foto): ?array
    {
        $foto = (new ProdukFotoModel())->find($id_foto);

        if ($foto === null || $this->varian((int) ($foto['stok_id'] ?? 0)) === null) {
            return null;
        }

        return $foto;
    }

    /** Varian sekartu dengan varian yang sedang dibuka panelnya. @return int[] */
    private function sekartu(object $varian): array
    {
        return (new StokModel())->sekartu((int) $varian->juragan_id, (string) $varian->kode);
    }

    /**
     * Berkas siap tampil: thumbnail kalau ?s= minta ukuran yang dikenal, kalau
     * tidak berkas aslinya. Null berarti barisnya ada tapi berkasnya tidak.
     */
    private function kirim(array $foto): ?string
    {
        $sisi = (int) $this->request->getVar('s');

        if ($sisi > 0) {
            $kecil = ProdukFotoModel::thumb($foto, $sisi);

            if ($kecil !== null) {
                return $kecil;
            }
        }

        return ProdukFotoModel::jalur($foto['berkas']);
    }

    /** Foto siap tampil untuk sisi browser: data baris + alamat gambarnya. */
    private static function siapTampil(?array $baris): array
    {
        $baris ??= [];

        return array_merge($baris, [
            'alamat'       => ProdukFotoModel::alamat((int) ($baris['id_foto'] ?? 0)),
            'alamat_kecil' => ProdukFotoModel::alamat((int) ($baris['id_foto'] ?? 0), 480),
        ]);
    }

    /** Jawaban galat baku untuk jalur foto. */
    private function galat(int $kode, string $pesan)
    {
        $this->response->setStatusCode($kode);

        return $this->response->setJSON(['status' => $pesan]);
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
