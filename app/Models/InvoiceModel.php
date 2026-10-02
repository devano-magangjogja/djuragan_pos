<?php

namespace App\Models;

use CodeIgniter\Model;

class InvoiceModel extends Model
{
    protected $table              = 'invoice';
    // protected $table              = 'order_invoice';
    protected $primaryKey         = 'id_invoice';
    protected $returnType         = 'object';
    protected $useSoftDeletes     = true;
    protected $allowedFields      = ['seri', 'tanggal_pesan', 'pemesan_id', 'kirimKepada_id', 'juragan_id', 'user_id', 'status_pesanan', 'status_pembayaran', 'status_pengiriman', 'keterangan', 'rincian', 'deadline'];
    protected $useTimestamps      = true;
    protected $createdField       = 'created_at';
    protected $updatedField       = 'update_at';
    protected $deletedField       = 'deleted_at';
    protected $dateFormat         = 'int';
    protected $validationRules    = [];
    protected $validationMessages = [];
    protected $skipValidation     = false;

    /**
     * Ambil detail semua invoice
     *
     * @param string $hal        semua, selesai, belum-proses, dalam-proses, pembayaran (cek-bayar = nama lama)
     * @param int    $juragan_id ID Juragan
     * @param string $cari       Kata Pencarian
     * @param int    $limit      Batas/Limit data yang akan ditampilkan
     * @param int    $offset     Awal data yang akan ditampilkan
     * @param string $kategori   Kunci dari kategori_tab($hal), dipakai saat $hal pembayaran/belum-proses
     */
    public function getAll(string $hal = 'semua', int $juragan_id = 0, $cari = '', int $limit = 0, int $offset = 0, string $kategori = 'perlu-cek'): array
    {
        $biayaModel      = new BiayaModel();
        $anggotaModel    = new AnggotaModel();
        $dibeliModel     = new BarangDibeli();
        $invStatusModel  = new StatusModel();
        $juraganModel    = new JuraganModel();
        $labelInvModel   = new LabelInvoice();
        $nilaiModel      = new NilaiUkuranModel();
        $pelangganModel  = new PelangganModel();
        $pembayaranModel = new PembayaranModel();
        $penggunaModel   = new UserModel();
        $pengirimanModel = new PengirimanModel();
        $builder         = $this;
        $return          = [];

        // IN () kosong adalah SQL invalid, gunakan [0] saat tidak ada id yang cocok
        $builder->whereIn('id_invoice', $this->ambilIds($cari) ?: [0]);

        $this->terapkanTab($builder, $hal, $kategori);

        if ($juragan_id > 0) {
            $builder->where('juragan_id', $juragan_id);
        }

        if ($hal === 'pembayaran' || $hal === 'cek-bayar') {
            if ($kategori === 'perlu-cek') {
                // digabung dua status, jadi dikelompokkan dulu supaya judul kelompoknya tidak terpotong
                $builder->orderBy('status_pembayaran ASC, tanggal_pesan ASC, update_at ASC');
            } elseif (in_array($kategori, ['dicicil', 'lunas'], true)) {
                $builder->orderBy('tanggal_pesan DESC, update_at DESC');
            } else {
                // yang paling lama menunggu diprioritaskan
                $builder->orderBy('tanggal_pesan ASC, update_at ASC');
            }
        } else {
            $builder->orderBy('status_pesanan ASC, tanggal_pesan DESC, update_at DESC');
        }

        $inv = $builder->findAll($limit, $offset);
        
        foreach ($inv as $invoice) {
            $return[$invoice->id_invoice]               = (array) $invoice;
            $return[$invoice->id_invoice]['barang']     = $dibeliModel->getSimple($invoice->id_invoice);
            $return[$invoice->id_invoice]['biaya']      = $biayaModel->getSimple($invoice->id_invoice);
            $return[$invoice->id_invoice]['juragan']    = $juraganModel->getSimple($invoice->juragan_id);
            $return[$invoice->id_invoice]['kirimKe']    = $pelangganModel->getSimple($invoice->kirimKepada_id);
            $return[$invoice->id_invoice]['pelanggan']  = $pelangganModel->getSimple($invoice->pemesan_id);
            $return[$invoice->id_invoice]['pembayaran'] = $pembayaranModel->getSimple($invoice->id_invoice);
            $return[$invoice->id_invoice]['pengguna']   = $penggunaModel->getSimple($invoice->user_id);
            $return[$invoice->id_invoice]['pengiriman'] = $pengirimanModel->getSimple($invoice->id_invoice);
            $return[$invoice->id_invoice]['source']     = $labelInvModel->getSimple($invoice->id_invoice);
            $return[$invoice->id_invoice]['status']     = $invStatusModel->getSimple($invoice->id_invoice);
        }

        // ukuran terstruktur menempel di baris produknya; order lama tidak punya
        // baris di tabel nilai_ukuran, jadi daftar ini memang kosong untuk mereka
        $ukuran = $nilaiModel->perBarang(array_keys($return));

        foreach ($return as $r) {
            foreach ($r['barang'] as $b) {
                $b->nilai_ukuran = $ukuran[$b->id] ?? [];
            }
        }

        // daftar rombongan menempel di kartunya; order satuan tidak punya baris
        // sama sekali di order_anggota jadi bloknya tidak dirender
        $rombongan = $anggotaModel->banyak(array_keys($return));

        foreach (array_keys($return) as $i) {
            $return[$i]['anggota'] = $rombongan[$i] ?? [];
        }

        return [
            'data'      => json_decode(json_encode(array_values($return))),
            'totalPage' => $this->counter($hal, $juragan_id, $cari, $kategori),
        ];
    }

    /**
     * Nilai invoice.status_pembayaran milik satu kategori pembayaran
     */
    private static function statusKategori(string $kategori): array
    {
        helper('fungsi');

        $daftar = kategori_pembayaran();

        return $daftar[$kategori]['status'] ?? $daftar['perlu-cek']['status'];
    }

    /**
     * Jumlah orderan per kategori pembayaran untuk satu juragan (sekali query).
     * Key 'semua' berisi total orderan pada tab tersebut.
     *
     * @return array<string, int> kategori_tab() slug => jumlah
     */
    public function countPembayaran(int $juragan_id = 0, string $hal = 'pembayaran'): array
    {
        helper('fungsi');

        $b = $this->db->table('invoice');
        $b->select('status_pembayaran, COUNT(*) AS n');
        $b->where('deleted_at', null);

        if ($hal === 'belum-proses') {
            $b->where('status_pesanan', '1');
        }

        if ($juragan_id > 0) {
            $b->where('juragan_id', $juragan_id);
        }

        $b->groupBy('status_pembayaran');

        $per_status = [];

        foreach ($b->get()->getResult() as $r) {
            $per_status[(string) $r->status_pembayaran] = (int) $r->n;
        }

        $jumlah = ['semua' => array_sum($per_status)];

        foreach (array_keys(kategori_tab($hal)) as $slug) {
            if ($slug === 'semua') {
                continue;
            }

            $jumlah[$slug] = 0;

            foreach (self::statusKategori($slug) as $s) {
                $jumlah[$slug] += $per_status[$s] ?? 0;
            }
        }

        return $jumlah;
    }

    /**
     * Tampilkan counter
     *
     * @param mixed $cari Bisa berupa array atau string
     */
    private function counter(string $hal = 'semua', int $juragan_id = 0, $cari = '', string $kategori = 'perlu-cek'): int
    {
        $builder = $this->select('id_invoice');

        $builder->whereIn('id_invoice', $this->ambilIds($cari) ?: [0]);

        $this->terapkanTab($builder, $hal, $kategori);

        if ($juragan_id > 0) {
            $builder->where('juragan_id', $juragan_id);
        }

        return $builder->countAllResults();
    }

    /**
     * Klausul satu tab daftar transaksi. Satu tempat supaya tab, counter, dan
     * angka dasbor tidak bisa lagi berbeda aturan.
     *
     * @param \CodeIgniter\Model|\CodeIgniter\Database\BaseBuilder $builder
     */
    private function terapkanTab($builder, string $hal, string $kategori = 'perlu-cek'): void
    {
        switch ($hal) {
            case 'selesai':
                $builder->where('status_pengiriman', '3');
                break;

            case 'belum-proses':
                $builder->where('status_pesanan', '1');

                if ($kategori !== 'semua') {
                    $builder->whereIn('status_pembayaran', self::statusKategori($kategori));
                }

                break;

            case 'pembayaran':
            case 'cek-bayar':
                $builder->whereIn('status_pembayaran', self::statusKategori($kategori));
                break;

            case 'dalam-proses':
                $builder->where('status_pesanan', '2');
                $builder->whereNotIn('status_pengiriman', ['3']);
                break;

            case 'saring':
                $this->terapkanSaring($builder, $kategori);
                break;
        }
    }

    /**
     * Klausul satu saringan orderan dari kartu/panel dasbor.
     *
     * Semua saringan kerja hanya menghitung orderan yang belum diambil
     * pelanggan; yang sudah diserahkan jadi riwayat, bukan pekerjaan hari ini.
     *
     * @param \CodeIgniter\Model|\CodeIgniter\Database\BaseBuilder $builder
     */
    private function terapkanSaring($builder, string $slug): void
    {
        helper('fungsi');

        $orderan = $this->db->prefixTable('invoice');
        $tahap   = $this->db->prefixTable('invoice_status');
        $packing = array_key_last(tahap_produksi());

        // kartu yang isinya sama dengan tab yang sudah ada ikut tab itu saja
        if ($slug === 'produksi' || $slug === 'belum-diproses') {
            $this->terapkanTab(
                $builder,
                $slug === 'produksi' ? 'dalam-proses' : 'belum-proses',
                'semua'
            );

            return;
        }

        $belum_diambil = "status_pengiriman <> '3' AND ";

        switch ($slug) {
            case 'pesan-hari-ini':
                $sql = 'tanggal_pesan = CURDATE()';
                break;

            case 'aktif':
                $sql = "status_pesanan IN ('1', '2') AND status_pengiriman <> '3'";
                break;

            case 'belum-lunas':
                $sql = "status_pembayaran NOT IN ('5', '6')";
                break;

            case 'siap-diambil':
                $sql = $belum_diambil
                    . "EXISTS (SELECT 1 FROM `{$tahap}` s WHERE s.invoice_id = `{$orderan}`.id_invoice"
                    . " AND s.status = '{$packing}' AND s.tanggal_selesai IS NOT NULL)";
                break;

            case 'ukuran-belum-lengkap':
                $anggota = $this->db->prefixTable('anggota');
                $nilai   = $this->db->prefixTable('nilai_ukuran');

                $sql = $belum_diambil
                    . "EXISTS (SELECT 1 FROM `{$anggota}` a WHERE a.invoice_id = `{$orderan}`.id_invoice)"
                    . " AND NOT EXISTS (SELECT 1 FROM `{$nilai}` v WHERE v.invoice_id = `{$orderan}`.id_invoice)";
                break;

            case 'deadline-hari-ini':
                $sql = $belum_diambil . 'deadline = CURDATE()';
                break;

            case 'deadline-besok':
                $sql = $belum_diambil . 'deadline = CURDATE() + INTERVAL 1 DAY';
                break;

            case 'deadline-3-hari':
                $sql = $belum_diambil . 'deadline BETWEEN CURDATE() AND CURDATE() + INTERVAL 3 DAY';
                break;

            case 'deadline-terlewat':
                $sql = $belum_diambil . 'deadline < CURDATE()';
                break;

            default:
                // slug tidak dikenal: jangan kembalikan seluruh isi tabel
                $sql = '0 = 1';
        }

        $builder->where($sql, null, false);
    }

    /**
     * Pembangun query untuk satu saringan, dibatasi pada daftar juragan pemakai.
     *
     * @param array<int> $juragan_ids
     *
     * @return \CodeIgniter\Database\BaseBuilder
     */
    private function pembangunSaring(array $juragan_ids, string $slug)
    {
        $ids = array_values(array_filter(array_map('intval', $juragan_ids)));

        $builder = $this->db->table('invoice');
        $builder->select('id_invoice');
        $builder->where('deleted_at', null);
        $builder->whereIn('juragan_id', $ids === [] ? [0] : $ids);

        $this->terapkanSaring($builder, $slug);

        return $builder;
    }

    /**
     * Jumlah orderan untuk satu saringan pada kartu/panel dasbor.
     *
     * Dipakai lewat model yang sama dengan daftar transaksinya, jadi angka
     * kartu dan halaman yang dibuka dari kartu itu tidak bisa berbeda lagi.
     *
     * @param array<int> $juragan_ids
     */
    public function hitungSaring(array $juragan_ids, string $slug): int
    {
        return $this->pembangunSaring($juragan_ids, $slug)->countAllResults();
    }

    /**
     * Sebaran tahap produksi tertinggi dari satu saringan orderan untuk blok
     * Ringkasan Produksi. Tahap 0 berarti orderan belum masuk antrean kerja.
     *
     * @param array<int> $juragan_ids
     *
     * @return array<int, int> nomor tahap => jumlah orderan
     */
    public function sebaranTahap(array $juragan_ids, string $slug = 'aktif'): array
    {
        $status = $this->db->prefixTable('invoice_status');
        $dasar  = $this->pembangunSaring($juragan_ids, $slug)->getCompiledSelect(false);

        $baris = $this->db->query('SELECT IFNULL(s.tahap, 0) AS tahap, COUNT(*) AS jumlah'
            . ' FROM (' . $dasar . ') o'
            . ' LEFT JOIN (SELECT invoice_id, MAX(CAST(status AS UNSIGNED)) AS tahap'
            . ' FROM `' . $status . '` WHERE tanggal_masuk IS NOT NULL GROUP BY invoice_id) s'
            . ' ON s.invoice_id = o.id_invoice'
            . ' GROUP BY IFNULL(s.tahap, 0) ORDER BY tahap ASC')->getResultArray();

        $sebaran = [];

        foreach ($baris as $b) {
            $sebaran[(int) $b['tahap']] = (int) $b['jumlah'];
        }

        return $sebaran;
    }

    public function getCount(int $id_juragan = 0): array
    {
        helper('fungsi');
        $countSemua       = $this->counts($id_juragan);
        $countSelesai     = $this->counts($id_juragan, 'selesai');
        $countBelum       = $this->counts($id_juragan, 'belum-proses');
        $countCekBayar    = $this->counts($id_juragan, 'cek-bayar');
        $countDalamProses = $this->counts($id_juragan, 'dalam-proses');

        return [
            'semua' => [
                'num'  => $countSemua,
                'text' => formatRibuan($countSemua),

            ],
            'selesai' => [
                'num'  => $countSelesai,
                'text' => formatRibuan($countSelesai),

            ],
            'belum_proses' => [
                'num'  => $countBelum,
                'text' => formatRibuan($countBelum),

            ],
            'cek_bayar' => [
                'num'  => $countCekBayar,
                'text' => formatRibuan($countCekBayar),

            ],
            'dalam_proses' => [
                'num'  => $countDalamProses,
                'text' => formatRibuan($countDalamProses),

            ],
        ];
    }

    private function counts(int $id_juragan, string $output = 'semua'): int
    {
        $q = $this->join('juragan j', 'j.id_juragan = invoice.juragan_id');

        if ($id_juragan > 0) {
            $q->where(['juragan_id' => $id_juragan]);
        }

        switch ($output) {
            case 'selesai':
                $q->where('invoice.status_pengiriman', '3');

                break;

            case 'belum-proses':
                $q->where('invoice.status_pesanan', '1');

                break;

            case 'cek-bayar':
                $q->whereIn('invoice.status_pembayaran', ['2', '3']);

                break;

            case 'dalam-proses':
                $q->where('invoice.status_pesanan', '2');
                $q->whereNotIn('invoice.status_pengiriman', ['3']);

                break;
        }

        return $q->countAllResults();
    }

    /**
     * Ambil ID dari Invoice berdasarkan kata kunci
     *
     * @param mixed $cari kata pencarian
     *
     * @return array list ID Invoice
     */
    private function ambilIds($cari): array
    {
        $this->select('invoice.id_invoice');

        $this->join('pelanggan p', 'p.id_pelanggan=invoice.pemesan_id');
        $this->join('pelanggan k', 'k.id_pelanggan=invoice.kirimKepada_id');
        $this->join('dibeli b', 'b.invoice_id=invoice.id_invoice');

        if (! empty($cari) && is_array($cari)) {
            if (! empty($cari['pembayaran'])) {
                $status_pembayaran = "invoice.status_pembayaran='" . $cari['pembayaran'] . "'";
                if ($cari['pembayaran'] === '2A') {
                    $status_pembayaran = "(invoice.status_pembayaran='2' OR invoice.status_pembayaran='3')";
                }
                $this->where($status_pembayaran);
            }

            if (! empty($cari['pengiriman'])) {
                $status_pengiriman = "invoice.status_pengiriman='" . $cari['pengiriman'] . "'";
                if ($cari['pengiriman'] === '2A') {
                    $status_pengiriman = "(invoice.status_pengiriman='2' OR invoice.status_pengiriman='3')";
                }
                $this->where($status_pengiriman);
            }

            if (! empty($cari['orderan'])) {
                $this->where('invoice.status_pesanan', $cari['orderan']);
            }

            if (! empty($cari['kolom'])) {
                switch ($cari['kolom']) {
                    case 'id':
                        $this->where('invoice.id_invoice', $cari['q']);
                        break;

                    case 'faktur':
                        $this->where('invoice.seri', $cari['q']);
                        break;

                    case 'nama':
                        $this->where("(p.nama_pelanggan LIKE '%" . $cari['q'] . "%' OR k.nama_pelanggan LIKE '%" . $cari['q'] . "%')");
                        break;

                    case 'hp':
                        $this->where("(p.hp LIKE '%" . $cari['q'] . "%' OR k.hp LIKE '%" . $cari['q'] . "%')");
                        break;

                    case 'kode':
                        $this->where("(b.kode LIKE '%" . $cari['q'] . "%')");
                        break;

                    case 'tanggal_pesan':
                        // DATE(CONVERT_TZ(FROM_UNIXTIME(tanggal_dibuat, '%Y-%m-%d %H:%i:%s'), '+00:00', '+00:00'))='".$cari['tanggal']."'
                        $this->where('invoice.tanggal_pesan', $cari['q']);
                        break;
                }
            } else {
                $query = '(';
                $query .= "b.kode LIKE '%" . $cari['q'] . "%' ";
                preg_match_all('/%22(?:\\\\.|(?!%22).)*%22|\S+/', $cari['q'], $matches);

                $term = '';

                foreach ($matches[0] as $term) {
                    $term = trim($term);
                    if (! empty($term)) {
                        $term = str_replace('"', '', $term);
                        $query .= "OR invoice.id_invoice LIKE '%" . $term . "%' ";
                        $query .= "OR invoice.seri LIKE '%" . $term . "%' ";
                        $query .= "OR p.nama_pelanggan LIKE '%" . $term . "%' ";
                        $query .= "OR k.nama_pelanggan LIKE '%" . $term . "%' ";
                        $query .= "OR p.hp LIKE '%" . $term . "%' ";
                        $query .= "OR k.hp LIKE '%" . $term . "%' ";
                        $query .= "OR p.alamat LIKE '%" . $term . "%' ";
                        $query .= "OR k.alamat LIKE '%" . $term . "%' ";
                        $query .= "OR invoice.keterangan LIKE '%" . $term . "%' ";
                    }
                }

                $query .= ')';
                $this->where($query);
            }
        }
        $return = [];

        foreach ($this->findAll() as $inv) {
            $return[] = $inv->id_invoice;
        }

        return $return;
    }

    /**
     * Ringkasan uang satu invoice: barang, lain-lain, sudah terbayar, dan yang belum dicek.
     */
    public function total_biaya($invoice_id)
    {
        $id = (int) $invoice_id;

        // masing-masing tabel dijumlah sendiri; digabung lewat JOIN malah membuat nilai
        // yang sama persis ikut terhapus oleh SUM(DISTINCT ...)
        $barang = $this->db->table('dibeli')
            ->select('IFNULL(SUM(harga * qty), 0) AS n', false)
            ->where('invoice_id', $id)
            ->getCompiledSelect();

        $lain = $this->db->table('biaya')
            ->select('IFNULL(SUM(nominal), 0) AS n', false)
            ->where('invoice_id', $id)
            ->getCompiledSelect();

        $terbayar = $this->db->table('pembayaran')
            ->select('IFNULL(SUM(total_pembayaran), 0) AS n', false)
            ->where('invoice_id', $id)
            ->where('status', '3')
            ->getCompiledSelect();

        $belumcek = $this->db->table('pembayaran')
            ->select('IFNULL(SUM(total_pembayaran), 0) AS n', false)
            ->where('invoice_id', $id)
            ->where('status', '1')
            ->getCompiledSelect();

        return $this->db->query("SELECT ({$barang}) AS barang, ({$lain}) AS lain, ({$terbayar}) AS terbayar, ({$belumcek}) AS belumcek");
    }

    /**
     * Aturan status pembayaran, hanya dari angka: 1 belum bayar, 2 menunggu konfirmasi
     * (belum ada dana masuk), 3 menunggu konfirmasi (kredit), 4 dicicil, 5 lunas ada kelebihan, 6 lunas.
     */
    public static function statusPembayaran(int $total, int $terbayar, int $belumcek): string
    {
        if ($belumcek > 0) {
            return $terbayar > 0 ? '3' : '2';
        }
        if ($terbayar === 0) {
            return '1';
        }
        if ($terbayar === $total) {
            return '6';
        }

        return $terbayar < $total ? '4' : '5';
    }

    /**
     * Hitung lalu simpan status_pembayaran satu invoice.
     */
    public function perbaruiStatusPembayaran($invoice_id): string
    {
        $cek    = $this->total_biaya($invoice_id)->getResult()[0];
        $status = self::statusPembayaran(
            (int) $cek->barang + (int) $cek->lain,
            (int) $cek->terbayar,
            (int) $cek->belumcek
        );

        $this->save([
            'id_invoice'        => $invoice_id,
            'status_pembayaran' => $status,
        ]);

        return $status;
    }

    public function status($invoice_id)
    {
        $status = $this->db->table('invoice_status s');
        $status->where('s.invoice_id', $invoice_id);
        $status->orderBy('s.id_status', 'ASC');

        return $status->get();
    }

    public function jumlah_produk($invoice_id)
    {
        $db      = \Config\Database::connect();
        $builder = $this->db->table('pengiriman p');

        $builder->select('(SELECT SUM(p.qty_kirim) FROM ' . $db->prefixTable('pengiriman') . ' p WHERE p.invoice_id=' . $invoice_id . ') AS sudah_kirim', false);

        $builder->select('(SELECT SUM(d.qty) FROM ' . $db->prefixTable('dibeli') . ' d WHERE d.invoice_id=' . $invoice_id . ') AS wajib_kirim', false);

        return $builder->get();
    }

    public function counter_terkirim()
    {
        $awalBulanIni  = tanggalDefault('mulai');
        $akhirBulanIni = tanggalDefault('akhir');

        $awalBulanLalu  = satuBulanSebelumnya($awalBulanIni);
        $akhirBulanLalu = satuBulanSebelumnya($akhirBulanIni);

        $counter = $this->db->table('juragan j');
        $counter->select('j.*');

        $counter->select('COUNT(DISTINCT(CASE WHEN i.deleted_at IS NULL AND i.tanggal_pesan between "' . $awalBulanIni . '" AND "' . $akhirBulanIni . '" THEN i.id_invoice END)) as jumlahOrderBulanIni');

        $counter->select('COUNT(DISTINCT(CASE WHEN i.deleted_at IS NULL AND i.tanggal_pesan between "' . $awalBulanLalu . '" AND "' . $akhirBulanLalu . '" THEN i.id_invoice END)) as jumlahOrderBulanLalu');

        $counter->select('SUM(CASE WHEN i.deleted_at IS NULL AND i.status_pengiriman = "3" AND DATE_FORMAT(FROM_UNIXTIME(s.tanggal_kirim), "%Y-%m-%d") between "' . $awalBulanIni . '" AND "' . $akhirBulanIni . '" THEN s.qty_kirim ELSE 0 END) as terkirimBulanIni');

        $counter->select('SUM(CASE WHEN i.deleted_at IS NULL AND i.status_pengiriman = "3" AND DATE_FORMAT(FROM_UNIXTIME(s.tanggal_kirim), "%Y-%m-%d") between "' . $awalBulanLalu . '" AND "' . $akhirBulanLalu . '" THEN s.qty_kirim ELSE 0 END) as terkirimBulanLalu');

        $counter->join('invoice i', 'j.id_juragan = i.juragan_id', 'left outer');
        $counter->join('dibeli b', 'b.invoice_id = i.id_invoice', 'left outer');
        $counter->join('pengiriman s', 's.invoice_id = i.id_invoice', 'left outer');

        $counter->groupBy('j.id_juragan');

        return $counter->get();
    }
}
