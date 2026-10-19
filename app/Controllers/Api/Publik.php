<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Filters\Apitoken;
use App\Models\BarangDibeli;
use App\Models\BiayaModel;
use App\Models\Cities;
use App\Models\InvoiceModel;
use App\Models\JuraganModel;
use App\Models\KunciApiModel;
use App\Models\LaporanModel;
use App\Models\PelangganModel;
use App\Models\PengirimanModel;
use App\Models\Province;
use App\Models\StatusModel;
use App\Models\StokModel;

/**
 * Bacaan untuk pihak luar (website partner), dipagari kunci API, bukan sesi login.
 *
 * Semua angka dihitung dari model yang sama dengan halaman dalam (StokModel,
 * LaporanModel, InvoiceModel) supaya "stok tersedia" dan rumus uang tidak punya
 * dua versi. Yang beda hanya bentuknya: kunci JSON disebut satu per satu, jadi
 * kolom baru di tabel tidak otomatis ikut keluar.
 *
 * Data pelanggan sengaja disensor. Yang dikirim hanya nama penerima dan wilayah
 * (kabupaten/provinsi) untuk membuktikan orderan itu benar miliknya. Alamat,
 * kodepos, kecamatan, nomor HP, keterangan bebas, riwayat pembayaran per
 * transferan, ukuran badan, dan nama anggota rombongan TIDAK dikirim — bagian
 * ini justru yang tidak boleh muncul di situs orang lain.
 */
class Publik extends BaseController
{
    /** Laporan yang boleh dibuka: yang agregat saja, bukan daftar per nota. */
    private const LAPORAN = ['pesanan', 'pendapatan', 'produk'];

    /** Kartu per halaman dibatasi supaya satu permintaan tidak menarik seluruh gudang. */
    private const HALAMAN_MAKS = 50;

    /**
     * Label status bayar. Helper `status_pembayaran()` tidak dipakai karena ia
     * menyusun garis waktu HTML untuk kartu transaksi, bukan kata; angkanya di
     * sini arti yang sama persis dengan tab saringan transaksi.
     */
    private const BAYAR = [
        '0' => 'Belum bayar', '1' => 'Belum bayar', '2' => 'Menunggu pembayaran',
        '3' => 'DP menunggu konfirmasi', '4' => 'Bayar dicicil', '5' => 'Lunas', '6' => 'Lunas',
    ];

    /** Label status kirim, kata-katanya diambil dari helper status_pengiriman(). */
    private const KIRIM = ['2' => 'Dikirim sebagian', '3' => 'Semua pesanan sudah dikirim'];

    /**
     * GET /api/publik/info
     *
     * Kartu pengenal kunci: hak apa yang aktif, toko mana yang terbuka, kuota
     * hariannya, dan apa yang boleh dipanggil. Sekalian ringkasan keadaan stok
     * (jual dan sewa dihitung sendiri-sendiri) dan total poin yang sudah masuk
     * hitungan, jadi partner bisa mengecek "kuncinya baca apa saja" tanpa perlu
     * dokumen tambahan.
     */
    public function info()
    {
        $kunci = $this->kunci();
        $ids   = KunciApiModel::toko($kunci);
        $stok  = new StokModel();

        $kolam = [];

        foreach (array_keys(StokModel::KOLAM) as $slug) {
            $kolam[$slug] = $this->beriTersedia($ids === [] ? [] : $stok->ringkas($ids, $slug));
        }

        $boleh = static fn (string $hak): bool => in_array($hak, KunciApiModel::hak($kunci), true);

        $isi = [
            'nama'           => (string) $kunci->nama,
            'hak'            => KunciApiModel::hak($kunci),
            'batas_harian'   => (int) $kunci->batas_harian,
            'terakhir_pakai' => $kunci->terakhir_pakai ? date('Y-m-d H:i', (int) $kunci->terakhir_pakai) : null,
            'toko'           => $this->toko($kunci),
            // unit jual dan unit sewa dua hal berbeda: dua-duanya dilaporkan
            // terpisah supaya tidak ada angka yang tercampur
            'kolam'          => $kolam,
            'endpoint'       => [
                'katalog'  => $boleh('katalog') ? '/api/publik/katalog?kolam=jual|sewa&cari=&keadaan=&toko=&halaman=&per_halaman=' : null,
                'stok'     => $boleh('stok') ? '/api/publik/stok/{id_stok}' : null,
                'laporan'  => $boleh('laporan') ? '/api/publik/laporan/{pesanan|pendapatan|produk}?awal=&akhir=' : null,
                'lacak'    => $boleh('lacak') ? '/api/publik/pesanan/{seri}?kode={kode_lacak}' : null,
                'poin'     => $boleh('poin') ? '/api/publik/poin?awal=&akhir=' : null,
            ],
        ];

        if ($boleh('poin')) {
            $ledger = $this->tabelLedger();
            $total  = \Config\Database::connect()->query('SELECT COALESCE(SUM(poin_total), 0) AS poin,'
                . ' COUNT(DISTINCT invoice_id) AS nota, COUNT(DISTINCT kode) AS barang FROM ' . $ledger
                . ' WHERE juragan_id IN (' . $this->daftarId($ids) . ')')->getRowArray();

            $isi['poin'] = [
                'terkumpul' => (int) ($total['poin'] ?? 0),
                'nota'      => (int) ($total['nota'] ?? 0),
                'barang'    => (int) ($total['barang'] ?? 0),
                'aturan'    => 'poin masuk saat nota Selesai DAN lunas; salah satu lepas, poinnya dicabut lagi',
            ];
        }

        return $this->response->setJSON($isi);
    }

    /**
     * GET /api/publik/katalog?kolam=jual&cari=&keadaan=ada&toko=3&halaman=1&per_halaman=20
     */
    public function katalog()
    {
        $kunci = $this->kunci();
        $ids   = KunciApiModel::toko($kunci);

        $kolam = StokModel::kolam((string) $this->request->getGet('kolam'));
        $cari  = mb_substr(trim((string) $this->request->getGet('cari')), 0, 60);

        $keadaan = (string) ($this->request->getGet('keadaan') ?: 'semua');
        // 'semua' adalah bawaan umpan data: halaman Produk menyembunyikan unit sewa
        // yang stoknya 0, tapi situs partner harus tetap melihat barang yang sedang
        // dipinjam orang
        if (! in_array($keadaan, ['semua', 'ada', 'menipis', 'habis'], true)) {
            $keadaan = 'semua';
        }

        // hanya toko yang benar-benar dipegang kunci ini
        $toko  = (int) $this->request->getGet('toko');
        $ids   = $toko > 0 && in_array($toko, $ids, true) ? [$toko] : $ids;

        $per    = min(self::HALAMAN_MAKS, max(1, (int) ($this->request->getGet('per_halaman') ?: 20)));
        $halaman = max(1, (int) ($this->request->getGet('halaman') ?: 1));

        $stok  = new StokModel();
        $total = $stok->kartu($ids, $toko > 0 && in_array($toko, KunciApiModel::toko($kunci), true) ? $toko : 0, $cari, $keadaan, $kolam);
        $kartu = $ids === [] ? [] : $stok->katalog($ids, $toko, $cari, $keadaan, $per, ($halaman - 1) * $per, $kolam);

        $kolam2 = [];
        $poin   = [];

        if ($kartu !== []) {
            $pasangan = array_map(static fn (array $p): array => [
                'juragan_id' => (int) $p['juragan_id'],
                'kode'       => (string) $p['kode'],
            ], $kartu);

            $kolam2 = $stok->duaKolam($pasangan, $ids);

            if ($this->boleh('poin')) {
                $poin = $this->poinBarang($ids, $pasangan);
            }
        }

        $barang = [];

        foreach ($kartu as $p) {
            $kunci2 = (int) $p['juragan_id'] . '|' . mb_strtolower((string) $p['kode']);

            $isi = [
                'kode'  => (string) $p['kode'],
                'toko'  => ['id' => (int) $p['juragan_id'], 'nama' => (string) $p['nama_juragan']],
                // unit jual dan unit sewa adalah dua tumpukan yang berbeda: tiap kolam
                // punya stok gudang, unit yang sedang keluar, dan sisanya sendiri
                'jual'  => $kolam2[$kunci2]['jual'] ?? null,
                'sewa'  => $kolam2[$kunci2]['sewa'] ?? null,
                'varian' => array_map(static fn (array $b): array => [
                    'id_stok'  => (int) $b['id_stok'],
                    'ukuran'   => (string) ($b['ukuran'] ?? ''),
                    'kolam'    => (string) $b['kolam'],
                    'harga'    => (int) $b['harga'],
                    'stok'     => (int) $b['stok'],
                    'tersedia' => (int) $b['tersedia'],
                    'keluar'   => (int) $b['stok'] - (int) $b['tersedia'],
                ], $p['varian']),
            ];

            if ($poin !== []) {
                $isi['poin'] = $poin[$kunci2] ?? ['satuan' => 0, 'terkumpul' => 0, 'nota' => 0];
            }

            $barang[] = $isi;
        }

        $ringkas = $this->beriTersedia($ids === [] ? [] : $stok->ringkas($ids, $kolam));

        return $this->response->setJSON([
            'kolam'        => $kolam,
            'halaman'      => $halaman,
            'per_halaman'  => $per,
            'total_kartu'  => $total,
            'jumlah_halaman' => $per > 0 ? (int) ceil($total / $per) : 0,
            'ringkas'      => $ringkas,
            'barang'       => $barang,
        ]);
    }

    /**
     * GET /api/publik/stok/123
     * Angka satu varian saja — dipakai widget "stok tinggal 2" di halaman produk
     * mereka tanpa menarik seluruh katalog.
     */
    public function stok($id_stok = 0)
    {
        $kunci = $this->kunci();
        $baris = (new StokModel())->find((int) $id_stok);

        if ($baris === null || ! KunciApiModel::punyaToko($kunci, (int) $baris->juragan_id)) {
            return $this->galat(404, 'Varian tidak ditemukan.');
        }

        $keluar = (new StokModel())->keluar([(int) $baris->id_stok], (string) $baris->kolam);

        return $this->response->setJSON([
            'id_stok'  => (int) $baris->id_stok,
            'kode'     => (string) $baris->kode,
            'ukuran'   => (string) ($baris->ukuran ?? ''),
            'kolam'    => (string) $baris->kolam,
            'harga'    => (int) $baris->harga,
            'stok'     => (int) $baris->stok,
            'keluar'   => $keluar[(int) $baris->id_stok] ?? 0,
            'tersedia' => (int) $baris->stok - ($keluar[(int) $baris->id_stok] ?? 0),
        ]);
    }

    /**
     * GET /api/publik/laporan/pesanan?awal=2026-10-01&akhir=2026-10-19
     *
     * Sengaja tidak mengirim nama pelanggan, nomor nota, atau isi keterangan:
     * yang dibutuhkan situs pihak lain cuma angka per periode.
     */
    public function laporan($jenis = '')
    {
        $jenis = (string) $jenis;

        if (! in_array($jenis, self::LAPORAN, true)) {
            return $this->galat(404, 'Jenis laporan tidak dikenal.');
        }

        $ids = KunciApiModel::toko($this->kunci());

        $awal  = $this->tanggal((string) $this->request->getGet('awal'), date('Y-m-01'));
        $akhir = $this->tanggal((string) $this->request->getGet('akhir'), date('Y-m-d'));

        if ($awal > $akhir) {
            [$awal, $akhir] = [$akhir, $awal];
        }

        $laporan = new LaporanModel();
        $isi     = $laporan->{$jenis}($ids, [
            'awal'    => $awal,
            'akhir'   => $akhir,
            'grup'    => LaporanModel::grup($awal, $akhir),
            'juragan' => 0,
            'kode'    => '',
            'tahap'   => -1,
            'bayar'   => '',
            'nama'    => '',
        ]);

        return $this->response->setJSON([
            'jenis'   => $jenis,
            'awal'    => $awal,
            'akhir'   => $akhir,
            'periode' => $isi['periode'] ?? '',
            'ringkas' => $isi['ringkas'] ?? [],
            'baris'   => $isi['baris'] ?? [],
        ]);
    }

    /**
     * GET /api/publik/pesanan/JF1790049540?kode=4KXJ-9M2A
     *
     * Nomor nota saja tidak cukup: tanpa kode lacak, siapa pun yang punya kunci
     * (atau menebak nomor) bisa membaca status orderan orang lain. Ketiganya
     * diperiksa sekaligus dan jawabannya sengaja sama-sama "tidak ditemukan",
     * supaya tidak ada bocoran nota mana yang sebenarnya ada.
     */
    public function pesanan($seri = '')
    {
        $kunci = $this->kunci();
        $seri  = mb_substr(trim((string) $seri), 0, 30);
        $kode  = mb_strtoupper(mb_substr(trim((string) $this->request->getGet('kode')), 0, 12));

        if ($seri === '' || $kode === '') {
            return $this->galat(404, 'Nota tidak ditemukan atau kode lacaknya salah.');
        }

        $nota = (new InvoiceModel())->where('seri', $seri)->first();

        if ($nota === null
            || ! KunciApiModel::punyaToko($kunci, (int) $nota->juragan_id)
            || ! hash_equals(mb_strtoupper((string) $nota->kode_lacak), $kode)) {
            return $this->galat(404, 'Nota tidak ditemukan atau kode lacaknya salah.');
        }

        return $this->response->setJSON($this->rangkumanNota($nota));
    }

    /**
     * Isi satu nota, dalam bentuk yang aman ditunjukkan ke situs orang lain.
     *
     * Daftarnya tulis eksplisit (bukan `$nota->toArray()`) supaya kolom baru di
     * tabel tidak otomatis ikut terkirim.
     *
     * @return array<string, mixed>
     */
    private function rangkumanNota(object $nota): array
    {
        $id     = (int) $nota->id_invoice;
        $rincian = json_decode((string) $nota->rincian, true);
        $rincian = is_array($rincian) ? $rincian : [];

        $kirim = (new PengirimanModel())->where('invoice_id', $id)->orderBy('id_pengiriman', 'DESC')->first();
        $pelanggan = (new PelangganModel())->find((int) $nota->pemesan_id);

        // angka agregat lewat query prepared langsung: builder CI di proyek ini
        // tidak punya selectRaw(), dan angka 'masuk' harus sama dengan yang
        // dipakai laporan (hanya pembayaran status 3 yang sudah dicek)
        $db        = \Config\Database::connect();
        $uangBayar = $db->query('SELECT COALESCE(SUM(total_pembayaran), 0) AS semua,'
            . " COALESCE(SUM(status = '3'), 0) AS dicek FROM `" . $db->prefixTable('pembayaran') . '`'
            . ' WHERE invoice_id = ?', [$id])->getRow();

        $tagihan = 0;

        foreach ((new BarangDibeli())->where('invoice_id', $id)->get()->getResultObject() as $b) {
            $tagihan += (int) $b->qty * (int) $b->harga;
        }

        foreach ((new BiayaModel())->where('invoice_id', $id)->get()->getResultObject() as $c) {
            $tagihan += (int) $c->nominal;
        }

        $rinciPoin = null;

        if ($this->boleh('poin')) {
            $catatan = $db->query('SELECT COALESCE(SUM(poin_total), 0) AS poin, COUNT(*) AS baris'
                . ' FROM ' . $this->tabelLedger() . ' WHERE invoice_id = ?', [$id])->getRowArray();

            // baris ledger hanya ada kalau nota ini sudah Selesai DAN lunas, jadi
            // 'sudah_dihitung: false' berarti tracking-nya memang belum sampai situ
            $rinciPoin = [
                'sudah_dihitung' => (int) ($catatan['baris'] ?? 0) > 0,
                'total'          => (int) ($catatan['poin'] ?? 0),
            ];
        }

        $isi = [
            'seri'         => (string) $nota->seri,
            'toko'         => $this->satuToko((int) $nota->juragan_id),
            // tanggal_pesan kolom DATETIME (bukan epoch), jadi potong teksnya saja;
            // memakai (int) di sini dulu menghasilkan 1970-01-01 untuk semua nota
            'tanggal_pesan' => substr((string) $nota->tanggal_pesan, 0, 10),
            'deadline'     => (string) ($nota->deadline ?? ''),
            // nama penerima + nama wilayah (bukan id mentah dari tabel): cukup untuk
            // memastikan orderan itu miliknya, tanpa alamat dan nomor kontak
            'penerima'     => $pelanggan === null ? null : [
                'nama'     => (string) $pelanggan->nama_pelanggan,
                'kabupaten' => (string) ((new Cities())->find((int) $pelanggan->kabupaten)->city_name ?? ''),
                'provinsi'  => (string) ((new Province())->find((int) $pelanggan->provinsi)->province_name ?? ''),
            ],
            'status'       => [
                'pesanan'         => (string) $nota->status_pesanan,
                'pesanan_label'   => label_status_orderan($nota->status_pesanan),
                'pembayaran'      => (string) $nota->status_pembayaran,
                'pembayaran_label' => self::BAYAR[(string) $nota->status_pembayaran] ?? '-',
                'pengiriman'      => (string) $nota->status_pengiriman,
                'pengiriman_label' => self::KIRIM[(string) $nota->status_pengiriman] ?? 'Belum dikirim',
            ],
            'uang'         => [
                'tagihan'    => $tagihan,
                'masuk'      => (int) ($uangBayar->dicek ?? 0) > 0 ? (int) $uangBayar->semua : 0,
                'tercatat'   => (int) ($uangBayar->semua ?? 0),
                'sisa'       => max(0, $tagihan - ((int) ($uangBayar->dicek ?? 0) > 0 ? (int) $uangBayar->semua : 0)),
            ],
            'layanan'      => [
                'tipe'     => (string) ($rincian['tipe'] ?? 'pembuatan'),
                'orderan'  => (string) ($rincian['orderan'] ?? ''),
                'ambil'    => (string) ($rincian['ambil'] ?? ''),
                'kembali'  => (string) ($rincian['kembali'] ?? ''),
                'jaminan'  => (string) ($rincian['jaminan'] ?? ''),
            ],
            'barang'       => array_map(static fn (object $b): array => [
                'kode'     => (string) $b->kode,
                'ukuran'   => (string) ($b->ukuran ?? ''),
                'qty'      => (int) $b->qty,
                'harga'    => (int) $b->harga,
                'subtotal' => (int) $b->qty * (int) $b->harga,
            ], (new BarangDibeli())->where('invoice_id', $id)->get()->getResultObject()),
            'pengiriman'   => $kirim === null ? null : [
                'kurir'         => (string) $kirim->kurir,
                'resi'          => (string) $kirim->resi,
                'ongkir'        => (int) $kirim->ongkir,
                'tanggal_kirim' => $kirim->tanggal_kirim ? date('Y-m-d', (int) $kirim->tanggal_kirim) : '',
            ],
            'tahap'        => array_map(static fn (object $s): array => [
                'tahap'   => (string) ($s->status ?? ''),
                'label'   => (tahap_produksi()[(int) ($s->status ?? 0)][1] ?? 'Tahap'),
                'masuk'   => $s->tanggal_masuk ? date('Y-m-d', (int) $s->tanggal_masuk) : '',
                'selesai' => $s->tanggal_selesai ? date('Y-m-d', (int) $s->tanggal_selesai) : '',
            ], (new StatusModel())->where('invoice_id', $id)->orderBy('id_status', 'ASC')->get()->getResultObject()),
        ];

        if ($rinciPoin !== null) {
            $isi['poin'] = $rinciPoin;
        }

        return $isi;
    }

    /**
     * GET /api/publik/poin?awal=2026-10-01&akhir=2026-10-19
     *
     * Dibaca dari ledger poin gaji, bukan dihitung ulang di sini: itu tabel yang
     * sudah disinkronkan setiap kali status bayar atau status kirim sebuah nota
     * berubah (nota harus Selesai DAN lunas, salah satu lepas maka poinnya dicabut).
     * Jadi angka endpoint ini selalu sama dengan yang dilihat Superadmin di menu
     * Tracking Transaksi dan laporan poin.
     *
     * Nama pegawai yang menerima poin sengaja tidak dikirim — yang penting untuk
     * pihak luar cuma berapa poin yang sudah masuk hitungan dan dari nota mana.
     */
    public function poin()
    {
        $kunci = $this->kunci();
        $ids   = KunciApiModel::toko($kunci);

        $awal  = $this->tanggal((string) $this->request->getGet('awal'), date('Y-m-01'));
        $akhir = $this->tanggal((string) $this->request->getGet('akhir'), date('Y-m-d'));

        if ($awal > $akhir) {
            [$awal, $akhir] = [$akhir, $awal];
        }

        $db    = \Config\Database::connect();
        $ledger = $this->tabelLedger();
        $nota  = $this->tabel('invoice');

        $rentang = 'WHERE p.juragan_id IN (' . $this->daftarId($ids) . ')'
            . ' AND p.created_at BETWEEN ? AND ?';

        $perBarang = $db->query('SELECT p.juragan_id, p.kode, SUM(p.poin_total) AS poin, SUM(p.qty) AS qty,'
            . ' COUNT(DISTINCT p.invoice_id) AS nota FROM ' . $ledger . ' p ' . $rentang
            . ' GROUP BY p.juragan_id, p.kode ORDER BY poin DESC LIMIT ' . LaporanModel::KAPASITAS,
            $this->batas($awal, $akhir))->getResultArray();

        $perNota = $db->query('SELECT p.invoice_id, MAX(o.seri) AS seri, MAX(o.tanggal_pesan) AS tanggal,'
            . ' SUM(p.poin_total) AS poin FROM ' . $ledger . ' p'
            . ' JOIN ' . $nota . ' o ON o.id_invoice = p.invoice_id ' . $rentang
            . ' GROUP BY p.invoice_id ORDER BY MAX(p.created_at) DESC LIMIT ' . LaporanModel::KAPASITAS,
            $this->batas($awal, $akhir))->getResultArray();

        $total = $db->query('SELECT COALESCE(SUM(poin_total), 0) AS poin, COUNT(DISTINCT invoice_id) AS nota'
            . ' FROM ' . $ledger . ' ' . str_replace('p.', '', $rentang), $this->batas($awal, $akhir))->getRowArray();

        return $this->response->setJSON([
            'awal'  => $awal,
            'akhir' => $akhir,
            'ringkas' => [
                'poin' => (int) ($total['poin'] ?? 0),
                'nota' => (int) ($total['nota'] ?? 0),
            ],
            'per_barang' => array_map(static fn (array $b): array => [
                'kode'  => (string) $b['kode'],
                'toko'  => (int) $b['juragan_id'],
                'poin'  => (int) $b['poin'],
                'unit'  => (int) $b['qty'],
                'nota'  => (int) $b['nota'],
            ], $perBarang),
            'per_nota' => array_map(static fn (array $b): array => [
                'seri'    => (string) $b['seri'],
                'tanggal' => substr((string) $b['tanggal'], 0, 10),
                'poin'    => (int) $b['poin'],
            ], $perNota),
        ]);
    }

    /** Kunci yang sudah lolos filter; tidak pernah diambil dari input. */
    private function kunci(): object
    {
        return Apitoken::kini() ?? new \stdClass();
    }

    /** Apakah kunci ini memegang satu hak. */
    private function boleh(string $hak): bool
    {
        return KunciApiModel::boleh($this->kunci(), $hak);
    }

    /**
     * Poin per barang: angka master (yang boleh diisi hanya oleh Superadmin) dan
     * total yang sudah masuk ledger. Satu query masing-masing, bukan per kartu.
     *
     * @param list<array{juragan_id:int, kode:string}> $pasangan
     *
     * @return array<string, array{satuan:int,terkumpul:int,nota:int}>
     */
    private function poinBarang(array $ids, array $pasangan): array
    {
        if ($ids === []) {
            return [];
        }

        $db     = \Config\Database::connect();
        $kode   = array_values(array_unique(array_column($pasangan, 'kode')));
        $escape = implode(',', array_map(static fn (string $k) => $db->escape($k), $kode));

        $master = $db->query('SELECT s.juragan_id, s.kode, MAX(IFNULL(pr.poin, 0)) AS satuan'
            . ' FROM `' . $this->tabel('stok') . '` s'
            . ' LEFT JOIN `' . $this->tabel('produk') . '` pr ON pr.id_produk = s.produk_id'
            . ' WHERE s.deleted_at IS NULL AND s.juragan_id IN (' . $this->daftarId($ids) . ')'
            . ' AND s.kode IN (' . $escape . ') GROUP BY s.juragan_id, s.kode')->getResultArray();

        $ledger = $db->query('SELECT juragan_id, kode, SUM(poin_total) AS terkumpul, COUNT(DISTINCT invoice_id) AS nota'
            . ' FROM ' . $this->tabelLedger()
            . ' WHERE juragan_id IN (' . $this->daftarId($ids) . ') AND kode IN (' . $escape . ')'
            . ' GROUP BY juragan_id, kode')->getResultArray();

        $hasil = [];

        foreach ($master as $b) {
            $hasil[(int) $b['juragan_id'] . '|' . mb_strtolower((string) $b['kode'])] = [
                'satuan'    => (int) $b['satuan'],
                'terkumpul' => 0,
                'nota'      => 0,
            ];
        }

        foreach ($ledger as $b) {
            $kunci2 = (int) $b['juragan_id'] . '|' . mb_strtolower((string) $b['kode']);

            $hasil[$kunci2] = [
                'satuan'    => (int) ($hasil[$kunci2]['satuan'] ?? 0),
                'terkumpul' => (int) $b['terkumpul'],
                'nota'      => (int) $b['nota'],
            ];
        }

        return $hasil;
    }

    /**
     * Ringkasan stok dengan nama field yang tidak bisa salah baca orang luar:
     * 'unit' di model adalah jumlah unit yang MASIH ADA (bukan angka gudang),
     * jadi di sini ditempel salinan bernama 'tersedia' juga.
     *
     * @param array<string, int> $ringkas
     *
     * @return array<string, int>
     */
    private function beriTersedia(array $ringkas): array
    {
        unset($ringkas['belum_poin']);

        $ringkas['tersedia'] = (int) ($ringkas['unit'] ?? 0);

        return $ringkas;
    }

    /** Nama tabel dengan prefix aktif, dipanggil dari model agar konsisten. */
    private function tabel(string $nama): string
    {
        return \Config\Database::connect()->prefixTable($nama);
    }

    private function tabelLedger(): string
    {
        return '`' . $this->tabel('poin_penjualan') . '`';
    }

    /** @param list<int> $ids */
    private function daftarId(array $ids): string
    {
        $bersih = array_values(array_filter(array_map('intval', $ids)));

        return $bersih === [] ? '0' : implode(',', $bersih);
    }

    /** Dua batas tanggal sebagai bind (awal 00:00 s/d akhir 23:59). */
    private function batas(string $awal, string $akhir): array
    {
        return [strtotime($awal . ' 00:00:00'), strtotime($akhir . ' 23:59:59')];
    }

    /** @return list<array{id:int,nama:string}> */
    private function toko(object $kunci): array
    {
        $daftar = [];

        foreach (KunciApiModel::toko($kunci) as $id) {
            $daftar[] = $this->satuToko($id);
        }

        return $daftar;
    }

    /** @return array{id:int,nama:string} */
    private function satuToko(int $id): array
    {
        $toko = (new JuraganModel())->find($id);

        return ['id' => $id, 'nama' => (string) ($toko->nama_juragan ?? '-')];
    }

    /** Tanggal YYYY-MM-DD saja; yang lain diganti nilai aman. */
    private function tanggal(string $nilai, string $baku): string
    {
        return preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $nilai) ? $nilai : $baku;
    }

    private function galat(int $kode, string $pesan)
    {
        return $this->response->setStatusCode($kode)->setJSON(['error' => $pesan]);
    }
}
