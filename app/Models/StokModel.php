<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Master stok per juragan: satu baris untuk tiap kombinasi juragan + kode + ukuran.
 */
class StokModel extends Model
{
    /** Stok sebanyak ini atau kurang dianggap menipis (masih ada, tapi hampir habis). */
    public const AMBANG_MENIPIS = 1;

    protected $table          = 'stok';
    protected $primaryKey     = 'id_stok';
    protected $returnType     = 'object';
    protected $useSoftDeletes = true;
    protected $allowedFields  = ['juragan_id', 'produk_id', 'kode', 'ukuran', 'harga', 'stok', 'keterangan'];
    protected $useTimestamps  = true;
    protected $createdField   = 'created_at';
    protected $updatedField   = 'updated_at';
    protected $deletedField   = 'deleted_at';
    protected $dateFormat     = 'int';

    /**
     * Katalog untuk kartu produk: satu kartu merangkum semua varian yang nama
     * barangnya sama di satu juragan. Halaman dihitung per kartu, bukan per
     * varian, karena yang dicari pengguna adalah "barang apa", bukan "ukuran apa".
     *
     * @param array  $juragan_ids ID juragan yang boleh dilihat pengguna
     * @param int    $juragan_id  0 = semua dari $juragan_ids
     * @param string $cari        Dicocokkan dengan kode atau keterangan
     * @param string $keadaan     '', 'ada', 'menipis', 'habis'
     *
     * @return list<array<string, mixed>>
     */
    public function katalog(array $juragan_ids, int $juragan_id = 0, string $cari = '', string $keadaan = '', int $limit = 20, int $offset = 0): array
    {
        $kunci = $this->kunci($juragan_ids, $juragan_id, $cari, $keadaan, $limit, $offset);

        if ($kunci === []) {
            return [];
        }

        $kartu = [];

        foreach ($this->varian($kunci, $juragan_ids, $juragan_id, $cari, $keadaan) as $b) {
            $t = &$kartu[$this->tanda($b['juragan_id'], $b['produk_id'], $b['kode'])];

            $t ??= [
                'juragan_id'  => (int) $b['juragan_id'],
                'id_juragan'  => (int) $b['juragan_id'],
                'produk_id'   => (int) $b['produk_id'],
                'kode'        => (string) $b['kode'],
                'nama_juragan' => (string) $b['nama_juragan'],
                'harga_mulai' => (int) $b['harga'],
                'harga_akhir' => (int) $b['harga'],
                'poin'        => (int) ($b['poin_produk'] ?? 0),
                'total_stok'  => 0,
                'terjual'     => 0,
                'varian'      => [],
            ];

            $t['harga_mulai'] = min($t['harga_mulai'], (int) $b['harga']);
            $t['harga_akhir'] = max($t['harga_akhir'], (int) $b['harga']);
            // poin satu produk: sama untuk semua ukuran, ambil apa adanya
            $t['poin']        = (int) ($b['poin_produk'] ?? 0);
            $t['total_stok'] += (int) $b['tersedia'];
            $t['terjual']    += (int) $b['terjual'];
            $t['varian'][]    = $b;

            unset($t);
        }

        // urutan kartu mengikuti $kunci (varian terbaru di atas)
        $urut = [];

        foreach ($kunci as $i => $k) {
            $urut[$this->tanda($k['juragan_id'], $k['produk_id'], $k['kode'])] = $i;
        }

        uksort($kartu, static fn (string $a, string $b) => $urut[$a] <=> $urut[$b]);

        return array_values($kartu);
    }

    /** Banyak kartu katalog dengan penyaringan yang sama. */
    public function kartu(array $juragan_ids, int $juragan_id = 0, string $cari = '', string $keadaan = ''): int
    {
        return count($this->kunci($juragan_ids, $juragan_id, $cari, $keadaan));
    }

    /**
     * Kondisi "sewa yang sudah harus kembali": tipenya sewa, tanggal kembalinya
     * diisi, dan tanggalnya sudah lewat. Baris orderan seperti ini tidak lagi
     * mengurangi stok, jadi barang sewaan pulih di rak sendiri tanpa ada yang
     * perlu mengingatnya. Sewa tanpa tanggal kembali dianggap masih dipinjam.
     */
    private static function sudahKembali(): string
    {
        return "JSON_UNQUOTE(JSON_EXTRACT(o.rincian, '$.tipe')) = 'sewa'"
            . " AND COALESCE(JSON_UNQUOTE(JSON_EXTRACT(o.rincian, '$.kembali')), '') <> ''"
            . ' AND DATE(JSON_UNQUOTE(JSON_EXTRACT(o.rincian, \'$.kembali\'))) < CURDATE()';
    }

    /**
     * Ungkapan SQL: stok gudang dikurangi unit yang sedang keluar, yaitu qty dari
     * nota yang sudah masuk tab Selesai (status_pengiriman 3), tidak dibatalkan,
     * dan tidak dihapus. Dipakai katalog, saringan keadaan, saran form order, dan
     * kartu ringkasan supaya angka stok di mana-mana berarti "masih bisa diambil".
     *
     * Ekspresi ini mentah (tidak di-escape builder), jadi tabel stok harus
     * ditulis dengan prefix aslinya seperti pada klausa FROM-nya.
     */
    private function klausaTersedia(): string
    {
        $stok    = '`' . $this->db->prefixTable('stok') . '`';
        $dibeli  = $this->db->prefixTable('dibeli');
        $invoice = $this->db->prefixTable('invoice');

        return '(CAST(' . $stok . '.stok AS SIGNED) - (SELECT IFNULL(SUM(d.qty), 0)'
            . ' FROM `' . $dibeli . '` d'
            . ' JOIN `' . $invoice . '` o ON o.id_invoice = d.invoice_id'
            . ' WHERE d.stok_id = ' . $stok . '.id_stok'
            . ' AND o.deleted_at IS NULL'
            . " AND o.status_pengiriman = '3'"
            . " AND o.status_pesanan <> '3'"
            . ' AND NOT (' . self::sudahKembali() . ')))';
    }

    /**
     * Unit yang sedang keluar per varian. Sama aturannya dengan `klausaTersedia()`,
     * hanya saja bentuknya peta PHP supaya jalur tulis bisa memeriksa dulu.
     *
     * @return array<int, int> id_stok => qty yang keluar
     */
    public function keluar(array $stok_ids, int $kecuali_invoice = 0): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $stok_ids))));

        if ($ids === []) {
            return [];
        }

        $dibeli  = $this->db->prefixTable('dibeli');
        $invoice = $this->db->prefixTable('invoice');

        $sql = 'SELECT d.stok_id, IFNULL(SUM(d.qty), 0) AS keluar'
            . ' FROM `' . $dibeli . '` d'
            . ' JOIN `' . $invoice . '` o ON o.id_invoice = d.invoice_id'
            . ' WHERE d.stok_id IN (' . implode(',', $ids) . ')'
            . ' AND o.deleted_at IS NULL'
            . " AND o.status_pengiriman = '3'"
            . " AND o.status_pesanan <> '3'"
            . ' AND NOT (' . self::sudahKembali() . ')';

        if ($kecuali_invoice > 0) {
            $sql .= ' AND o.id_invoice <> ' . $kecuali_invoice;
        }

        $sql .= ' GROUP BY d.stok_id';

        $hasil = [];

        foreach ($this->db->query($sql)->getResultArray() as $b) {
            $hasil[(int) $b['stok_id']] = (int) $b['keluar'];
        }

        return $hasil;
    }

    /**
     * Baris orderan yang mengambil lebih banyak unit daripada stok yang tersedia.
     * Pemakaian nota yang sedang disimpan tidak ikut dihitung, jadi menyunting
     * nota yang sudah keluar tetap boleh selama qty-nya tidak bertambah melewati
     * sisa rak.
     *
     * @param array<string|int, array> $baris hasil `tautkan_stok()`
     *
     * @return array<string, string> kunci form => pesan galat
     */
    public function lebih(array $baris, int $kecuali_invoice = 0): array
    {
        $perlu = [];

        foreach ($baris as $k => $v) {
            $id = (int) ($v['stok_id'] ?? 0);

            if ($id > 0) {
                $perlu[$k] = [
                    'id'     => $id,
                    'qty'    => max(0, (int) ($v['qty'] ?? 0)),
                    'label'  => trim((string) ($v['kode'] ?? '') . ' ' . (string) ($v['ukuran'] ?? '')),
                ];
            }
        }

        if ($perlu === []) {
            return [];
        }

        $punya = [];

        foreach ($this->whereIn('id_stok', array_column($perlu, 'id'))->get()->getResultArray() as $b) {
            $punya[(int) $b['id_stok']] = (int) $b['stok'];
        }

        $keluar = $this->keluar(array_keys($punya), $kecuali_invoice);
        $dipaki = [];
        $galat  = [];

        foreach ($perlu as $k => $v) {
            $sisa     = ($punya[$v['id']] ?? 0) - ($keluar[$v['id']] ?? 0);
            $dipaki[$v['id']] = ($dipaki[$v['id']] ?? 0) + $v['qty'];

            if ($dipaki[$v['id']] > $sisa) {
                $galat['produk[' . $k . '][stok]'] = 'Stok ' . ($v['label'] === '' ? 'barang ini' : $v['label'])
                    . ' tinggal ' . $sisa . ', tidak bisa mengambil ' . $dipaki[$v['id']] . ' unit.';
            }
        }

        return $galat;
    }

    /**
     * Angka untuk kartu ringkasan: varian, total unit, nilai stok, menipis, habis.
     *
     * @return array{varian:int,unit:int,nilai:int,menipis:int,habis:int}
     */
    public function ringkas(array $juragan_ids): array
    {
        $tabel    = $this->db->prefixTable('stok');
        $tersedia = $this->klausaTersedia();

        $baris = $this->db->query('SELECT ' . $tersedia . ' AS tersedia, harga'
            . ' FROM `' . $tabel . '` WHERE deleted_at IS NULL AND ' . $this->klausulJuragan($juragan_ids))
            ->getResultArray();

        $hasil = ['varian' => 0, 'unit' => 0, 'nilai' => 0, 'menipis' => 0, 'habis' => 0];

        foreach ($baris as $b) {
            $unit = (int) $b['tersedia'];

            $hasil['varian']++;
            $hasil['unit']   += $unit;
            $hasil['nilai']  += $unit * (int) $b['harga'];

            if ($unit <= 0) {
                $hasil['habis']++;
            } elseif ($unit <= self::AMBANG_MENIPIS) {
                $hasil['menipis']++;
            }
        }

        return $hasil;
    }

    /**
     * Sudah ada baris stok dengan juragan + kode + ukuran yang sama? Tanpa unique
     * index, karena baris yang dihapus lunak tidak boleh memblokir nama yang sama
     * dibuat ulang.
     */
    public function kembar(int $id, int $juragan_id, string $kode, ?string $ukuran): bool
    {
        $builder = $this->where('juragan_id', $juragan_id)->where('kode', $kode);

        if ($ukuran === null || $ukuran === '') {
            $builder->groupStart()->where('ukuran', null)->orWhere('ukuran', '')->groupEnd();
        } else {
            $builder->where('ukuran', $ukuran);
        }

        if ($id > 0) {
            $builder->where('id_stok !=', $id);
        }

        return $builder->countAllResults() > 0;
    }

    /**
     * Varian sekartu produk yang masih hidup: juragan dan nama barang yang sama.
     * Foto produk memang menempel pada satu varian, tapi kartu katalog dan form
     * orderan menampilkan gabungan foto sekartu supaya satu foto cukup diunggah
     * sekali. Varian yang dihapus lunak dibuang di sini karena `get()` tidak
     * menyaringnya, sementara `find()` iya — campuran keduanya bikin harga
     * warisan menunjuk baris yang sudah mati.
     *
     * @return int[]
     */
    public function sekartu(int $juragan_id, string $kode): array
    {
        if ($juragan_id <= 0 || $kode === '') {
            return [];
        }

        $baris = $this->select('id_stok')
            ->where('juragan_id', $juragan_id)
            ->where('kode', $kode)
            ->where('deleted_at', null)
            ->orderBy('id_stok', 'ASC')
            ->get()
            ->getResultArray();

        return array_map(static fn (array $b): int => (int) $b['id_stok'], $baris);
    }

    /**
     * Saran varian untuk form orderan: varian yang nama barangnya cocok dengan
     * kata yang diketik di satu toko. Saringannya sengaja memakai `saring()`
     * yang dipakai katalog juga, jadi kode, ukuran, harga, dan stok yang dilihat
     * CS di form sama persis dengan kartu katalog.
     *
     * @return list<array<string, mixed>>
     */
    public function saran(array $juragan_ids, int $juragan_id, string $cari, int $limit = 12): array
    {
        if ($cari === '' || $juragan_id <= 0) {
            return [];
        }

        return $this->saring($juragan_ids, $juragan_id, $cari, '')
            ->orderBy('stok.kode')
            ->orderBy('urutan')
            ->orderBy('stok.id_stok')
            ->limit($limit)
            ->get()
            ->getResultArray();
    }

    /** Nama produk dari master, untuk saran di kolom isian. */
    public function daftarKode(): array
    {
        $produk = $this->db->prefixTable('produk');

        return $this->db->query('SELECT kode FROM `' . $produk . '` ORDER BY kode')->getResultArray();
    }

    /** Ukuran dari master, dikelompokkan supaya dropdown tidak acak-acakan. */
    public function daftarUkuran(): array
    {
        $ukuran = $this->db->prefixTable('ukuran');

        return $this->db->query('SELECT kode, label, kelompok FROM `' . $ukuran . '` ORDER BY kelompok, urutan')->getResultArray();
    }

    private function saring(array $juragan_ids, int $juragan_id, string $cari, string $keadaan)
    {
        // `ukuran` diberi alias: ekspresi di dalam COALESCE tidak ikut diberi
        // prefix tabel oleh query builder, jadi nama aslinya (order_ukuran) tidak dikenal.
        $builder = $this->select('stok.*, juragan.nama_juragan, COALESCE(u.urutan, 255) AS urutan, IFNULL(pr.poin, 0) AS poin_produk')
            ->select($this->klausaTersedia() . ' AS tersedia', false)
            ->join('juragan', 'juragan.id_juragan = stok.juragan_id')
            ->join('ukuran u', 'u.kode = stok.ukuran', 'left')
            ->join('produk pr', 'pr.id_produk = stok.produk_id', 'left');

        return $this->pagar($builder, $juragan_ids, $juragan_id, $cari, $keadaan);
    }

    /**
     * Saringan yang berlaku atas baris stok: pagar toko, pencarian, dan keadaan
     * stok. Dipakai bersama oleh query katalog dan query variannya supaya jumlah
     * kartu di pager tidak pernah berbeda dari kartu yang tampil.
     */
    private function pagar($builder, array $juragan_ids, int $juragan_id, string $cari, string $keadaan)
    {
        // hasilnya diambil lewat get(), yang menembus query builder tanpa
        // scope soft delete yang biasa dipakai find()/findAll()
        $builder->where('stok.deleted_at', null);

        if ($juragan_id > 0) {
            $builder->where('stok.juragan_id', $juragan_id);
        } else {
            // IN () kosong adalah SQL invalid, gunakan [0] saat tidak ada id yang cocok
            $builder->whereIn('stok.juragan_id', $juragan_ids ?: [0]);
        }

        if ($cari !== '') {
            $builder->groupStart()
                ->like('stok.kode', $cari)
                ->orLike('stok.keterangan', $cari)
                ->groupEnd();
        }

        // keadaan dibaca dari stok yang masih tersedia, bukan angka gudang:
        // barang yang sedang keluar di nota Selesai tidak bisa dijual lagi
        $tersedia = $this->klausaTersedia();

        switch ($keadaan) {
            case 'menipis':
                $builder->where($tersedia . ' > 0', null, false)
                    ->where($tersedia . ' <= ' . self::AMBANG_MENIPIS, null, false);
                break;

            case 'habis':
                $builder->where($tersedia . ' <= 0', null, false);
                break;

            case 'ada':
                $builder->where($tersedia . ' > ' . self::AMBANG_MENIPIS, null, false);
                break;
        }

        return $builder;
    }

    /**
     * Kunci tiap kartu katalog: juragan + produk + nama barang. Produk NULL
     * ikut dikelompokkan (baris lama sebelum kode dipetakan ke master), dan
     * `MAX(id_stok)` dipakai sebagai penanda kemudaan karena kartu boleh berisi
     * beberapa varian.
     */
    private function kunci(array $juragan_ids, int $juragan_id, string $cari, string $keadaan, ?int $limit = null, int $offset = 0): array
    {
        // agregat ditulis mentah, jadi nama tabelnya harus berprefix seperti
        // hasil kerja builder (FROM `order_stok` tanpa alias)
        $tabel = $this->db->prefixTable('stok');

        $builder = $this->pagar(
            $this->builder()
                // nama kolom biasa dibiarkan di-escape builder supaya ikut terprefix
                ->select('stok.juragan_id, stok.produk_id, stok.kode')
                ->select('MAX(`' . $tabel . '`.id_stok) AS terbaru', false)
                ->groupBy(['stok.juragan_id', 'stok.produk_id', 'stok.kode']),
            $juragan_ids,
            $juragan_id,
            $cari,
            $keadaan
        )->orderBy('terbaru', 'DESC');

        if ($limit !== null) {
            $builder->limit($limit, $offset);
        }

        return $builder->get()->getResultArray();
    }

    /** Semua varian dari kunci kartu yang sedang diminta, lengkap dengan terjualnya. */
    private function varian(array $kunci, array $juragan_ids, int $juragan_id, string $cari, string $keadaan): array
    {
        $builder = $this->saring($juragan_ids, $juragan_id, $cari, $keadaan)->groupStart();

        foreach (array_values($kunci) as $i => $k) {
            $i === 0 ? $builder->groupStart() : $builder->orGroupStart();

            $builder->where('stok.juragan_id', (int) $k['juragan_id'])
                ->where('stok.kode', $k['kode']);

            // NULL dan '' harus cocok dengan kelompoknya sendiri, bukan dengan varian ber-produk
            if ($k['produk_id'] === null || $k['produk_id'] === '') {
                $builder->groupStart()->where('stok.produk_id', null)->orWhere('stok.produk_id', '')->groupEnd();
            } else {
                $builder->where('stok.produk_id', (int) $k['produk_id']);
            }

            $builder->groupEnd();
        }

        $baris = $builder->groupEnd()
            ->orderBy('stok.juragan_id')
            ->orderBy('stok.kode')
            ->orderBy('urutan')
            ->orderBy('stok.id_stok')
            ->get()
            ->getResultArray();

        return $this->pasangTerjual($baris);
    }

    /** Penanda kartu di PHP; harus sama cara membulatnya dengan kunci() dan varian(). */
    private function tanda(int|string $juragan_id, int|string|null $produk_id, string $kode): string
    {
        $produk = ($produk_id === null || $produk_id === '') ? '-' : (string) (int) $produk_id;

        // kode dibandingkan tanpa peduli huruf besar/kecil, sama seperti collation
        // MySQL yang dipakai GROUP BY di kunci()
        return $juragan_id . '|' . $produk . '|' . mb_strtolower($kode);
    }

    private function klausulJuragan(array $juragan_ids): string
    {
        $ids = array_values(array_filter(array_map('intval', $juragan_ids)));

        // IN () kosong adalah SQL invalid, gunakan (0) saat tidak ada id yang cocok
        return 'juragan_id IN (' . ($ids === [] ? '0' : implode(',', $ids)) . ')';
    }

    /**
     * Barang terjual per varian, dihitung dari transaksi yang belum dihapus.
     * Hanya untuk baris di halaman ini supaya agregasinya tetap murah.
     */
    private function pasangTerjual(array $baris): array
    {
        if ($baris === []) {
            return [];
        }

        $produkIds  = array_values(array_unique(array_filter(array_column($baris, 'produk_id'))));
        $juraganIds = array_values(array_unique(array_map('intval', array_column($baris, 'juragan_id'))));

        $petakan = [];

        if ($produkIds !== []) {
            $dibeli  = $this->db->prefixTable('dibeli');
            $invoice = $this->db->prefixTable('invoice');

            $sql = 'SELECT o.juragan_id, d.produk_id, d.ukuran, SUM(d.qty) AS jumlah'
                . ' FROM `' . $dibeli . '` d'
                . ' JOIN `' . $invoice . '` o ON o.id_invoice = d.invoice_id AND o.deleted_at IS NULL'
                . ' WHERE o.juragan_id IN (' . implode(',', $juraganIds) . ')'
                . ' AND d.produk_id IN (' . implode(',', $produkIds) . ')'
                . ' GROUP BY o.juragan_id, d.produk_id, d.ukuran';

            foreach ($this->db->query($sql)->getResultArray() as $h) {
                $petakan[$h['juragan_id'] . '|' . $h['produk_id'] . '|' . strtolower((string) $h['ukuran'])] = (int) $h['jumlah'];
            }
        }

        foreach ($baris as $i => $b) {
            $kunci = $b['juragan_id'] . '|' . $b['produk_id'] . '|' . strtolower((string) $b['ukuran']);

            $baris[$i]['terjual'] = ! empty($b['produk_id']) ? ($petakan[$kunci] ?? 0) : 0;
        }

        return $baris;
    }
}
