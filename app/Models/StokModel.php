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
                'total_stok'  => 0,
                'terjual'     => 0,
                'varian'      => [],
            ];

            $t['harga_mulai'] = min($t['harga_mulai'], (int) $b['harga']);
            $t['harga_akhir'] = max($t['harga_akhir'], (int) $b['harga']);
            $t['total_stok'] += (int) $b['stok'];
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
     * Angka untuk kartu ringkasan: varian, total unit, nilai stok, menipis, habis.
     *
     * @return array{varian:int,unit:int,nilai:int,menipis:int,habis:int}
     */
    public function ringkas(array $juragan_ids): array
    {
        $tabel  = $this->db->prefixTable('stok');
        $klausul = $this->klausulJuragan($juragan_ids);

        $row = $this->db->query('SELECT COUNT(*) AS varian, COALESCE(SUM(stok), 0) AS unit,'
            . ' COALESCE(SUM(stok * harga), 0) AS nilai,'
            . ' COALESCE(SUM(stok > 0 AND stok <= ' . self::AMBANG_MENIPIS . '), 0) AS menipis,'
            . ' COALESCE(SUM(stok <= 0), 0) AS habis'
            . ' FROM `' . $tabel . '` WHERE deleted_at IS NULL AND ' . $klausul)->getFirstRow();

        return array_map('intval', (array) $row);
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
     * Varian sekartu produk: juragan dan nama barang yang sama. Foto produk
     * memang menempel pada satu varian, tapi kartu katalog dan form orderan
     * menampilkan gabungan foto sekartu supaya satu foto cukup diunggah sekali.
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
        $builder = $this->select('stok.*, juragan.nama_juragan, COALESCE(u.urutan, 255) AS urutan')
            ->join('juragan', 'juragan.id_juragan = stok.juragan_id')
            ->join('ukuran u', 'u.kode = stok.ukuran', 'left');

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

        switch ($keadaan) {
            case 'menipis':
                $builder->where('stok.stok >', 0)->where('stok.stok <=', self::AMBANG_MENIPIS);
                break;

            case 'habis':
                $builder->where('stok.stok <=', 0);
                break;

            case 'ada':
                $builder->where('stok.stok >', self::AMBANG_MENIPIS);
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
