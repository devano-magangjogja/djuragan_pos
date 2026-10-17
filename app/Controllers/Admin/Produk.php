<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\ProdukFotoModel;
use App\Models\StokModel;
use CodeIgniter\HTTP\Files\UploadedFile;

/**
 * Pantau stok dan tambah barang per juragan.
 */
class Produk extends BaseController
{
    private const KEADAAN = ['', 'ada', 'menipis', 'habis'];

    /**
     * Daftar stok. Query string: juragan, cari, keadaan, page.
     *
     * @return string
     */
    public function index()
    {
        $ids       = $this->juraganIds();
        $stokModel = new StokModel();

        $juragan = (int) $this->request->getGet('juragan');
        // juragan yang tidak dimiliki pengguna dianggap tidak ada
        if ($juragan > 0 && ! in_array($juragan, $ids, true)) {
            $juragan = 0;
        }

        $cari = trim((string) $this->request->getGet('cari'));

        $keadaan = (string) $this->request->getGet('keadaan');
        if (! in_array($keadaan, self::KEADAAN, true)) {
            $keadaan = '';
        }

        // tab yang dibuka: unit untuk dijual atau unit untuk disewa
        $kolam = StokModel::kolam((string) $this->request->getGet('kolam'));

        $limit = config('Pager')->perPage;
        $page  = max(1, (int) $this->request->getGet('page'));
        $offset = ($page - 1) * $limit;

        $total = $stokModel->kartu($ids, $juragan, $cari, $keadaan, $kolam);

        $katalog = $stokModel->katalog($ids, $juragan, $cari, $keadaan, $limit, $offset, $kolam);

        // foto diambil sekali query untuk seluruh kartu di halaman ini, per
        // daftar varian (bukan per kartu) karena foto menempel di varian
        $varian = array_map(
            static fn (array $p): array => array_map(
                static fn (array $b): int => (int) $b['id_stok'],
                $p['varian']
            ),
            $katalog
        );

        $data = [
            'title'     => 'Produk & Stok',
            'katalog'   => $katalog,
            'fotonya'   => (new ProdukFotoModel())->perKartu($varian),
            'ringkas'   => $stokModel->ringkas($ids, $kolam),
            'juragan'   => $juragan,
            'juragans'  => $this->juraganPilihan(),
            'cari'      => $cari,
            'keadaan'   => $keadaan,
            // tab kolam aktif + labelnya, dipakai view untuk judul kolom dan tautan tab
            'kolam'     => $kolam,
            'kolams'    => StokModel::KOLAM,
            'kolamStok' => StokModel::kolomKolam($kolam)[0],
            'kolamHarga' => StokModel::kolomKolam($kolam)[1],
            'ukuran'    => $stokModel->daftarUkuran(),
            'kode'      => $stokModel->daftarKode(),
            'total'     => $total,
            'limit'     => $limit,
            'page'      => $page,
            'sukses'    => session('sukses'),
            'gagal'     => session('gagal'),
            'errors'    => session('errors') ?: [],
            'ambangnya' => StokModel::AMBANG_MENIPIS,
            // CS hanya membaca daftar; Superadmin yang menata poin varian
            'bacaSaja'  => (string) session()->get('level') === 'cs',
            'bisaPoin'  => (string) session()->get('level') === 'superadmin',
        ];

        return view('admin/produk', $data);
    }

    /**
     * Simpan varian stok baru.
     *
     * @return \CodeIgniter\HTTP\RedirectResponse
     */
    public function simpan()
    {
        $validation = \Config\Services::validation();
        $validation->setRuleGroup('addStok');

        if (! $validation->withRequest($this->request)->run()) {
            return redirect()->to($this->alamatKembali())
                ->withInput()->with('errors', $validation->getErrors());
        }

        $juragan_id = (int) $this->request->getPost('juragan_id');
        $kode       = $this->kodePost();
        $ukuran     = $this->ukuranPost();
        $kolam      = StokModel::kolam((string) $this->request->getPost('kolam'));
        $stokPost   = (int) $this->request->getPost('stok');

        if (! $this->bolehJuragan($juragan_id)) {
            return redirect()->to($this->alamatKembali())->with('gagal', 'Juragan ini tidak bisa diakses.');
        }

        $stokModel = new StokModel();

        if ($stokModel->kembar(0, $juragan_id, $kode, $ukuran)) {
            return redirect()->to($this->alamatKembali())
                ->withInput()->with('gagal', 'Varian ' . $kode . ' ' . $ukuran . ' sudah terdaftar.');
        }

        // "+ Varian" pada kartu tidak menanyakan harga: satu kartu dianggap punya
        // harga yang sama, jadi nilainya diwarisi dari varian tertua sekartu
        $harga   = $this->hargaPost();
        $saudara = $stokModel->sekartu($juragan_id, $kode);

        if ($harga === null && $saudara === []) {
            return redirect()->to($this->alamatKembali())->withInput()
                ->with('gagal', 'Belum ada varian ' . $kode . ' di toko ini, jadi harganya tidak bisa diwarisi. '
                    . 'Tambahkan lewat tombol "Tambah Barang" di atas.');
        }

        $lain = $saudara === [] ? null : $stokModel->find($saudara[0]);

        $produk_id = jamin_produk($kode);

        $stokModel->insert([
            'juragan_id' => $juragan_id,
            'produk_id'  => $produk_id,
            'kode'       => $kode,
            'ukuran'     => $ukuran,
            // hanya kolam tab yang dibuka yang diisi; harga kolam satunya diwarisi
            // dari saudara sekartu supaya satu barang tidak berubah harga hanya
            // karena ukuran baru ditambah lewat tab lain
            'harga'      => $kolam === 'jual' ? ($harga ?? (int) $lain->harga) : (int) ($lain->harga ?? 0),
            'harga_sewa' => $kolam === 'sewa' ? ($harga ?? (int) $lain->harga_sewa) : (int) ($lain->harga_sewa ?? 0),
            'stok'       => $kolam === 'jual' ? $stokPost : 0,
            'stok_sewa'  => $kolam === 'sewa' ? $stokPost : 0,
            'keterangan' => $this->keteranganPost(),
        ]);

        // poin menempel di master produk (satu nilai untuk semua ukuran), khusus Superadmin
        if ($produk_id !== null) {
            $this->simpanPoinProduk((int) $produk_id);
        }

        // foto ikut form tambah, jadi varian yang baru ada sudah langsung punya
        // contoh bentuk; pipa penyimpanannya sama dengan panel kelola foto
        $pesan = 'Barang berhasil ditambahkan.';
        $berkas = $this->berkasFoto();

        if ($berkas !== []) {
            $id_stok   = (int) $stokModel->getInsertID();
            $fotoModel = new ProdukFotoModel();
            $user_id   = (int) session()->get('id');
            $masuk     = 0;
            $ditolak   = [];

            foreach ($berkas as $satu) {
                [, $galat] = $fotoModel->terima($satu, $id_stok, $user_id);

                if ($galat !== null) {
                    $ditolak[] = $galat;

                    continue;
                }

                $masuk++;
            }

            if ($ditolak !== []) {
                $pesan = 'Barang berhasil ditambahkan, tetapi ' . count($ditolak) . ' foto ditolak: ' . $ditolak[0];
            } elseif ($masuk > 0) {
                $pesan = 'Barang berhasil ditambahkan bersama ' . $masuk . ' foto.';
            }
        }

        return redirect()->to($this->alamatKembali())->with('sukses', $pesan);
    }

    /**
     * Perbarui harga, stok, keterangan, atau juragan satu varian.
     *
     * @return \CodeIgniter\HTTP\RedirectResponse
     */
    public function perbarui()
    {
        $validation = \Config\Services::validation();
        $validation->setRuleGroup('updateStok');

        if (! $validation->withRequest($this->request)->run()) {
            return redirect()->to($this->alamatKembali())
                ->withInput()->with('errors', $validation->getErrors());
        }

        $stokModel = new StokModel();
        $id        = (int) $this->request->getPost('id_stok');
        $baris     = $stokModel->find($id);

        if ($baris === null || ! $this->bolehJuragan((int) $baris->juragan_id)) {
            return redirect()->to($this->alamatKembali())->with('gagal', 'Barang tidak ditemukan.');
        }

        // varian boleh dipindah antar toko, tapi kedua ujung harus milik user
        $juragan_baru = (int) $this->request->getPost('juragan_id');

        if (! $this->bolehJuragan($juragan_baru)) {
            return redirect()->to($this->alamatKembali())->with('gagal', 'Juragan ini tidak bisa diakses.');
        }

        $kode   = $this->kodePost();
        $ukuran = $this->ukuranPost();

        if ($stokModel->kembar($id, $juragan_baru, $kode, $ukuran)) {
            return redirect()->to($this->alamatKembali())
                ->with('gagal', 'Varian ' . $kode . ' ' . $ukuran . ' sudah terdaftar di toko itu.');
        }

        $produk_id = jamin_produk($kode);

        // sunting hanya menyentuh kolam tab yang dibuka; kolam satunya dibiarkan
        // apa adanya supaya pindah tab tidak pernah mengubah angka yang lain
        [$kolomStok, $kolomHarga] = StokModel::kolomKolam(StokModel::kolam((string) $this->request->getPost('kolam')));

        $data = [
            'juragan_id' => $juragan_baru,
            'produk_id'  => $produk_id,
            'kode'       => $kode,
            'ukuran'     => $ukuran,
            'keterangan' => $this->keteranganPost(),
            $kolomHarga  => (int) $this->request->getPost('harga'),
            $kolomStok   => (int) $this->request->getPost('stok'),
        ];

        $stokModel->update($id, $data);

        // poin per produk ditulis ke master (superadmin saja); Admin tidak menyentuhnya
        if ($produk_id !== null) {
            $this->simpanPoinProduk((int) $produk_id);
        }

        return redirect()->to($this->alamatKembali())->with('sukses', 'Barang berhasil diperbarui.');
    }

    /**
     * Tulis poin ke master produk. Hanya Superadmin yang boleh menentukan angka;
     * level lain tidak mengubah apa pun sehingga poin yang sudah ada tetap utuh.
     */
    private function simpanPoinProduk(int $produk_id): void
    {
        if ((string) session()->get('level') !== 'superadmin') {
            return;
        }

        $poin = trim((string) $this->request->getPost('poin'));
        $poin = $poin === '' ? 0 : max(0, (int) $poin);

        \Config\Database::connect()
            ->table('produk')
            ->where('id_produk', $produk_id)
            ->update(['poin' => $poin, 'updated_at' => time()]);
    }

    /**
     * Hapus lunak satu varian. Transaksi lama tetap utuh.
     *
     * @return \CodeIgniter\HTTP\RedirectResponse
     */
    public function hapus()
    {
        $stokModel = new StokModel();
        $id        = (int) $this->request->getPost('id_stok');
        $baris     = $stokModel->find($id);

        if ($baris === null || ! $this->bolehJuragan((int) $baris->juragan_id)) {
            return redirect()->to($this->alamatKembali())->with('gagal', 'Barang tidak ditemukan.');
        }

        // foto varian yang pergi pindah ke saudara sekartunya supaya kartu yang
        // masih hidup tidak kehilangan contoh bentuknya
        $saudara = array_values(array_diff(
            $stokModel->sekartu((int) $baris->juragan_id, (string) $baris->kode),
            [$id]
        ));

        $this->bereskanFoto($id, $saudara);
        $stokModel->delete($id);

        return redirect()->to($this->alamatKembali())->with('sukses', 'Barang berhasil dihapus.');
    }

    /**
     * Hapus lunak satu kartu produk: semua variannya pergi bersama.
     * Transaksi lama tetap utuh karena baris stok hanya ditandai terhapus.
     *
     * @return \CodeIgniter\HTTP\RedirectResponse
     */
    public function hapus_kartu()
    {
        $juragan_id = (int) $this->request->getPost('juragan_id');
        $kode       = $this->kodePost();

        if ($kode === '' || ! $this->bolehJuragan($juragan_id)) {
            return redirect()->to($this->alamatKembali())->with('gagal', 'Barang tidak ditemukan.');
        }

        $stokModel = new StokModel();
        $ids       = $stokModel->sekartu($juragan_id, $kode);

        if ($ids === []) {
            return redirect()->to($this->alamatKembali())->with('gagal', 'Barang tidak ditemukan.');
        }

        // kartu ikut habis, jadi tidak ada saudara tempat foto pindah
        foreach ($ids as $id) {
            $this->bereskanFoto($id, []);
        }

        $stokModel->delete($ids);

        return redirect()->to($this->alamatKembali())
            ->with('sukses', 'Barang ' . $kode . ' (' . count($ids) . ' varian) berhasil dihapus.');
    }

    /**
     * Foto varian yang dihapus tidak boleh jadi yatim: pindah ke varian saudara
     * kalau kartunya masih hidup, ikut terhapus bersama berkasnya kalau tidak.
     *
     * @param int[] $saudara
     */
    private function bereskanFoto(int $id_stok, array $saudara): void
    {
        $foto = new ProdukFotoModel();

        if ($saudara === []) {
            foreach ($foto->banyak([$id_stok])[$id_stok] ?? [] as $b) {
                $foto->buang((int) $b['id_foto']);
            }

            return;
        }

        // builder, bukan Model::update(): argumen pertama update() model adalah
        // id, jadi data saja di situ dianggap "tidak ada yang diperbarui"
        $foto->builder()->where('stok_id', $id_stok)->update(['stok_id' => $saudara[0]]);
    }

    /** Berkas foto dari form tambah; nama field-nya `foto[]`. @return list<UploadedFile> */
    private function berkasFoto(): array
    {
        $dapat = [];

        foreach ($this->request->getFiles() as $kumpulan) {
            foreach (is_array($kumpulan) ? $kumpulan : [$kumpulan] as $satu) {
                // kolom file yang tidak disentuh pengguna ikut terkirim sebagai
                // isian kosong; itu bukan foto yang gagal, jadi jangan dihitung
                if ($satu instanceof UploadedFile && $satu->getError() !== UPLOAD_ERR_NO_FILE) {
                    $dapat[] = $satu;
                }
            }
        }

        return $dapat;
    }

    /** Nama varian tidak boleh punya spasi ganda; huruf besar dibiarkan seperti ditulis. */
    private function kodePost(): string
    {
        return mb_substr(trim((string) preg_replace('/\s+/', ' ', (string) $this->request->getPost('kode'))), 0, 20);
    }

    private function ukuranPost(): string
    {
        return mb_substr(trim((string) $this->request->getPost('ukuran')), 0, 6);
    }

    /** Harga kosong berarti varian baru warisan dari saudaranya; lihat simpan(). */
    private function hargaPost(): ?int
    {
        $harga = trim((string) $this->request->getPost('harga'));

        return $harga === '' ? null : (int) $harga;
    }

    private function keteranganPost(): ?string
    {
        $keterangan = trim((string) $this->request->getPost('keterangan'));

        return $keterangan === '' ? null : mb_substr($keterangan, 0, 500);
    }

    private function bolehJuragan(int $juragan_id): bool
    {
        return $juragan_id > 0 && in_array($juragan_id, $this->juraganIds(), true);
    }

    /**
     * Kembali ke daftar dengan filter yang sedang dipakai, supaya pesan sukses
     * atau kesalahan tidak muncul di halaman kosong.
     */
    private function alamatKembali(): string
    {
        $kueri = array_filter([
            'juragan' => (int) $this->request->getPost('kembali_juragan'),
            'keadaan' => (string) $this->request->getPost('kembali_keadaan'),
            'cari'    => (string) $this->request->getPost('kembali_cari'),
            // kembali ke tab kolam yang sedang dibuka
            'kolam'   => StokModel::kolam((string) $this->request->getPost('kolam')),
        ], static fn ($v) => $v !== '' && $v !== 0);

        return site_url('admin/produk' . ($kueri === [] ? '' : '?' . http_build_query($kueri)));
    }
}
