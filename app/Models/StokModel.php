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
     * Baris stok yang terlihat pengguna, siap tampil di tabel. Tiap baris ikut
     * terjual (qty dari transaksi lama) supaya angkanya tidak cuma tebakan.
     *
     * @param array  $juragan_ids ID juragan yang boleh dilihat pengguna
     * @param int    $juragan_id  0 = semua dari $juragan_ids
     * @param string $cari        Dicocokkan dengan kode atau keterangan
     * @param string $keadaan     '', 'ada', 'menipis', 'habis'
     */
    public function daftar(array $juragan_ids, int $juragan_id = 0, string $cari = '', string $keadaan = '', int $limit = 20, int $offset = 0): array
    {
        $baris = $this->saring($juragan_ids, $juragan_id, $cari, $keadaan)
            // varian terbaru di atas supaya barang yang barusan ditambah langsung kelihatan
            ->orderBy('stok.id_stok', 'DESC')
            ->limit($limit, $offset)
            ->get()
            ->getResultArray();

        return $this->pasangTerjual($baris);
    }

    public function jumlah(array $juragan_ids, int $juragan_id = 0, string $cari = '', string $keadaan = ''): int
    {
        return $this->saring($juragan_ids, $juragan_id, $cari, $keadaan)->countAllResults();
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
            ->join('ukuran u', 'u.kode = stok.ukuran', 'left')
            // hasilnya diambil lewat get(), yang menembus query builder tanpa
            // scope soft delete yang biasa dipakai find()/findAll()
            ->where('stok.deleted_at', null);

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
