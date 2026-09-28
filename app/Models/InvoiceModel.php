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
    protected $allowedFields      = ['seri', 'tanggal_pesan', 'pemesan_id', 'kirimKepada_id', 'juragan_id', 'user_id', 'status_pesanan', 'status_pembayaran', 'status_pengiriman', 'keterangan', 'rincian'];
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
     * @param string $hal        Hanya berisi semua selesai, belum-proses, cek-bayar, dalam-proses
     * @param int    $juragan_id ID Juragan
     * @param string $cari       Kata Pencarian
     * @param int    $limit      Batas/Limit data yang akan ditampilkan
     * @param int    $offset     Awal data yang akan ditampilkan
     */
    public function getAll(string $hal = 'semua', int $juragan_id = 0, $cari = '', int $limit = 0, int $offset = 0): array
    {
        $biayaModel      = new BiayaModel();
        $dibeliModel     = new BarangDibeli();
        $invStatusModel  = new StatusModel();
        $juraganModel    = new JuraganModel();
        $labelInvModel   = new LabelInvoice();
        $pelangganModel  = new PelangganModel();
        $pembayaranModel = new PembayaranModel();
        $penggunaModel   = new UserModel();
        $pengirimanModel = new PengirimanModel();
        $builder         = $this;
        $return          = [];

        // IN () kosong adalah SQL invalid, gunakan [0] saat tidak ada id yang cocok
        $builder->whereIn('id_invoice', $this->ambilIds($cari) ?: [0]);

        switch ($hal) {
            case 'selesai':
                $builder->where('status_pengiriman', '3');
                break;

            case 'belum-proses':
                $builder->where('status_pesanan', '1');
                break;

            case 'cek-bayar':
                $builder->whereIn('status_pembayaran', ['2', '3']);
                break;

            case 'dalam-proses':
                $builder->where('status_pesanan', '2');
                $builder->whereNotIn('status_pengiriman', ['3']);
                break;
        }

        if ($juragan_id > 0) {
            $builder->where('juragan_id', $juragan_id);
        }

        $builder->orderBy('status_pesanan ASC, tanggal_pesan DESC, update_at DESC');

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

        return [
            'data'      => json_decode(json_encode(array_values($return))),
            'totalPage' => $this->counter($hal, $juragan_id, $cari),
        ];
    }

    /**
     * Tampilkan counter
     *
     * @param mixed $cari Bisa berupa array atau string
     */
    private function counter(string $hal = 'semua', int $juragan_id = 0, $cari = ''): int
    {
        $builder = $this->select('id_invoice');

        $builder->whereIn('id_invoice', $this->ambilIds($cari) ?: [0]);

        switch ($hal) {
            case 'selesai':
                $builder->where('status_pengiriman', '3');
                break;

            case 'belum-proses':
                $builder->where('status_pesanan', '1');
                break;

            case 'cek-bayar':
                $builder->whereIn('status_pembayaran', ['2', '3']);
                break;

            case 'dalam-proses':
                $builder->where('status_pesanan', '2');
                $builder->whereNotIn('status_pengiriman', ['3']);
                break;
        }

        if ($juragan_id > 0) {
            $builder->where('juragan_id', $juragan_id);
        }

        return $builder->countAllResults();
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

    public function total_biaya($invoice_id)
    {
        $biaya = $this->db->table('dibeli d');
        $biaya->select('SUM(DISTINCT d.harga*d.qty) as barang');
        $biaya->select('SUM(DISTINCT c.nominal) as lain');
        $biaya->select("SUM(DISTINCT case when x.status='3' then x.total_pembayaran else 0 end) as terbayar");
        $biaya->select("SUM(DISTINCT case when x.status='1' then x.total_pembayaran else 0 end) as belumcek");

        $biaya->join('biaya c', 'c.invoice_id = d.invoice_id', 'left');
        $biaya->join('pembayaran x', 'x.invoice_id = d.invoice_id', 'left');

        $biaya->where('d.invoice_id', $invoice_id);

        // $biaya->groupBy("d.invoice_id");
        return $biaya->get();
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
