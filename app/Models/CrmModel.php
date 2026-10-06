<?php

namespace App\Models;

use CodeIgniter\Model;

class CrmModel extends Model
{
    /**
     * Mengambil ringkasan metrik CRM pelanggan yang bercerita
     */
    public function getRingkasan(): array
    {
        $pPel = $this->db->prefixTable('pelanggan');
        $pInv = $this->db->prefixTable('invoice');
        $pLog = $this->db->prefixTable('crm_pesan_log');
        $pPro = $this->db->prefixTable('crm_pelanggan_profil');
        $pFol = $this->db->prefixTable('crm_followup');

        // 1. Metrik Dasar dari View crm_pelanggan
        $rowPelanggan = $this->db->query('SELECT 
            COUNT(*) as total_pelanggan,
            COUNT(CASE WHEN jumlah_order > 1 THEN 1 END) as repeat_order,
            COUNT(CASE WHEN jumlah_order = 1 THEN 1 END) as order_sekali,
            COUNT(CASE WHEN sisa > 0 THEN 1 END) as punya_piutang,
            SUM(nilai) as total_omset,
            SUM(sisa) as total_piutang
        FROM crm_pelanggan')->getRowArray();

        // 2. Customer Aktif Bulan Ini (memiliki transaksi dalam 30 hari terakhir)
        $tigaPuluhHariLalu = date('Y-m-d', strtotime('-30 days'));
        $enamPuluhHariLalu  = date('Y-m-d', strtotime('-60 days'));
        $awalBulanIni       = date('Y-m-01');

        $rowAktif = $this->db->query('SELECT 
            COUNT(DISTINCT CASE WHEN order_terakhir >= ? THEN id_pelanggan END) as aktif_bulan_ini,
            COUNT(DISTINCT CASE WHEN order_terakhir < ? AND jumlah_order > 0 THEN id_pelanggan END) as pasif_pelanggan,
            COUNT(DISTINCT CASE WHEN order_pertama >= ? THEN id_pelanggan END) as pelanggan_baru_bulan_ini
        FROM crm_pelanggan', [$tigaPuluhHariLalu, $enamPuluhHariLalu, $awalBulanIni])->getRowArray();

        // 3. Prospek Belum Closing (status_crm = 'prospek' ATAU pelanggan yang ordernya 0)
        $rowProspek = $this->db->query("SELECT 
            COUNT(*) as prospek_count
        FROM crm_pelanggan p
        LEFT JOIN `{$pPro}` pro ON pro.pelanggan_id = p.id_pelanggan
        WHERE pro.status_crm = 'prospek' OR (p.jumlah_order = 0 AND p.terdaftar_sejak >= UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 90 DAY)))")->getRowArray();

        // 4. Follow-up Hari Ini
        $hariIni = date('Y-m-d');
        $rowFollowup = $this->db->query("SELECT 
            COUNT(CASE WHEN tanggal_jatuh_tempo = ? AND status = 'menunggu' THEN 1 END) as followup_hari_ini,
            COUNT(CASE WHEN tanggal_jatuh_tempo < ? AND status = 'menunggu' THEN 1 END) as followup_terlambat,
            COUNT(CASE WHEN status = 'menunggu' THEN 1 END) as followup_total_pending
        FROM `{$pFol}`", [$hariIni, $hariIni])->getRowArray();

        // 5. Pesan WhatsApp Log
        $rowPesan = $this->db->query("SELECT 
            COUNT(*) as total_pesan,
            COUNT(CASE WHEN status = 'terkirim' THEN 1 END) as pesan_terkirim,
            COUNT(CASE WHEN status = 'gagal' THEN 1 END) as pesan_gagal
        FROM `{$pLog}`")->getRowArray();

        // 6. Tagihan Pending
        $rowInv = $this->db->query("SELECT COUNT(*) as tagihan_pending FROM `{$pInv}` WHERE deleted_at IS NULL AND status_pembayaran IN ('1','2','3','4')")->getRowArray();

        // 7. Duplikat Terdeteksi
        $duplikatCount = 0;
        try {
            $rowDup = $this->db->query('SELECT COUNT(*) as dup_count FROM crm_pelanggan_duplikat')->getRowArray();
            $duplikatCount = (int) ($rowDup['dup_count'] ?? 0);
        } catch (\Throwable $e) {
            $duplikatCount = 0;
        }

        return [
            'total_pelanggan'         => (int) ($rowPelanggan['total_pelanggan'] ?? 0),
            'repeat_order'            => (int) ($rowPelanggan['repeat_order'] ?? 0),
            'order_sekali'            => (int) ($rowPelanggan['order_sekali'] ?? 0),
            'punya_piutang'           => (int) ($rowPelanggan['punya_piutang'] ?? 0),
            'total_omset'             => (int) ($rowPelanggan['total_omset'] ?? 0),
            'total_piutang'           => (int) ($rowPelanggan['total_piutang'] ?? 0),
            'aktif_bulan_ini'         => (int) ($rowAktif['aktif_bulan_ini'] ?? 0),
            'pasif_pelanggan'         => (int) ($rowAktif['pasif_pelanggan'] ?? 0),
            'pelanggan_baru_bulan_ini'=> (int) ($rowAktif['pelanggan_baru_bulan_ini'] ?? 0),
            'prospek_belum_closing'   => (int) ($rowProspek['prospek_count'] ?? 0),
            'followup_hari_ini'       => (int) ($rowFollowup['followup_hari_ini'] ?? 0),
            'followup_terlambat'      => (int) ($rowFollowup['followup_terlambat'] ?? 0),
            'followup_total_pending'  => (int) ($rowFollowup['followup_total_pending'] ?? 0),
            'total_pesan'             => (int) ($rowPesan['total_pesan'] ?? 0),
            'pesan_terkirim'          => (int) ($rowPesan['pesan_terkirim'] ?? 0),
            'pesan_gagal'             => (int) ($rowPesan['pesan_gagal'] ?? 0),
            'tagihan_pending'         => (int) ($rowInv['tagihan_pending'] ?? 0),
            'duplikat_terdeteksi'     => $duplikatCount,
        ];
    }

    /**
     * Mengambil daftar pelanggan dengan filter status CRM, tag, segmen, dan pencarian diperkuat
     */
    public function getDaftarPelanggan(array $filter = [], int $limit = 20, int $offset = 0): array
    {
        $pPro = $this->db->prefixTable('crm_pelanggan_profil');
        $pTag = $this->db->prefixTable('crm_pelanggan_tag');
        $pInv = $this->db->prefixTable('invoice');

        $builder = $this->db->table('crm_pelanggan cp');
        $builder->select('cp.*, pro.status_crm, pro.opt_in_broadcast, pro.catatan as catatan_crm');
        $builder->join("{$pPro} pro", 'pro.pelanggan_id = cp.id_pelanggan', 'left');

        $this->terapkanFilterPelanggan($builder, $filter);

        $builder->orderBy('cp.jumlah_order', 'DESC');
        $builder->orderBy('cp.nilai', 'DESC');

        $rows = $builder->get($limit, $offset)->getResultArray();

        // Ambil tag untuk tiap pelanggan
        if (!empty($rows)) {
            $ids = array_column($rows, 'id_pelanggan');
            $tagsByPelanggan = $this->getTagsUntukPelangganList($ids);
            foreach ($rows as &$r) {
                $r['tags'] = $tagsByPelanggan[$r['id_pelanggan']] ?? [];
                // Tentukan status efektif jika di profil belum di-set manual
                if (empty($r['status_crm'])) {
                    $r['status_crm'] = $this->hitungStatusOtomatis($r);
                }
            }
        }

        return $rows;
    }

    public function countDaftarPelanggan(array $filter = []): int
    {
        $pPro = $this->db->prefixTable('crm_pelanggan_profil');
        $builder = $this->db->table('crm_pelanggan cp');
        $builder->join("{$pPro} pro", 'pro.pelanggan_id = cp.id_pelanggan', 'left');

        $this->terapkanFilterPelanggan($builder, $filter);

        return $builder->countAllResults();
    }

    private function terapkanFilterPelanggan(&$builder, array $filter): void
    {
        $pTag = $this->db->prefixTable('crm_pelanggan_tag');
        $pInv = $this->db->prefixTable('invoice');

        $cari = trim($filter['cari'] ?? '');
        if ($cari !== '') {
            $builder->groupStart()
                ->like('cp.nama_pelanggan', $cari)
                ->orLike('cp.nomor_utama', $cari)
                ->orLike('cp.kota', $cari)
                ->orLike('cp.alamat', $cari);
            if (is_numeric($cari)) {
                $builder->orWhere('cp.id_pelanggan', (int) $cari);
            }
            $builder->groupEnd();
        }

        // Filter status CRM
        $status = trim($filter['status'] ?? '');
        if (!empty($status) && $status !== 'semua') {
            if ($status === 'prospek') {
                $builder->groupStart()
                    ->where('pro.status_crm', 'prospek')
                    ->orWhere('cp.jumlah_order', 0)
                    ->groupEnd();
            } elseif ($status === 'loyal') {
                $builder->groupStart()
                    ->where('pro.status_crm', 'loyal')
                    ->orWhere('cp.jumlah_order >=', 3)
                    ->groupEnd();
            } elseif ($status === 'aktif') {
                $batas = date('Y-m-d', strtotime('-60 days'));
                $builder->groupStart()
                    ->where('pro.status_crm', 'aktif')
                    ->orGroupStart()
                        ->where('cp.order_terakhir >=', $batas)
                        ->where('cp.jumlah_order >', 0)
                    ->groupEnd()
                    ->groupEnd();
            } elseif ($status === 'tidak_aktif') {
                $batas = date('Y-m-d', strtotime('-60 days'));
                $builder->groupStart()
                    ->where('pro.status_crm', 'tidak_aktif')
                    ->orGroupStart()
                        ->where('cp.order_terakhir <', $batas)
                        ->where('cp.jumlah_order >', 0)
                    ->groupEnd()
                    ->groupEnd();
            } elseif ($status === 'baru') {
                $builder->groupStart()
                    ->where('pro.status_crm', 'baru')
                    ->orWhere('cp.jumlah_order', 1)
                    ->groupEnd();
            } else {
                $builder->where('pro.status_crm', $status);
            }
        }

        // Filter Tag
        $tagId = (int) ($filter['tag_id'] ?? 0);
        if ($tagId > 0) {
            $builder->where("cp.id_pelanggan IN (SELECT pelanggan_id FROM `{$pTag}` WHERE tag_id = {$tagId})");
        }

        // Filter Segmen Karakteristik & Perilaku
        $segmen = $filter['segmen'] ?? 'semua';
        if ($segmen === 'repeat') {
            $builder->where('cp.jumlah_order >', 1);
        } elseif ($segmen === 'sekali') {
            $builder->where('cp.jumlah_order', 1);
        } elseif ($segmen === 'piutang') {
            $builder->where('cp.sisa >', 0);
        } elseif ($segmen === 'pasif') {
            $batas = date('Y-m-d', strtotime('-60 days'));
            $builder->where('cp.order_terakhir <', $batas)->where('cp.jumlah_order >', 0);
        } elseif ($segmen === 'prospek') {
            $builder->where('cp.jumlah_order', 0);
        } elseif ($segmen === 'sewa') {
            // Customer yang pernah order sewa
            $builder->where("cp.id_pelanggan IN (SELECT pemesan_id FROM `{$pInv}` WHERE deleted_at IS NULL AND rincian LIKE '%sewa%')");
        } elseif ($segmen === 'custom') {
            // Customer yang pernah custom order
            $builder->where("cp.id_pelanggan IN (SELECT pemesan_id FROM `{$pInv}` WHERE deleted_at IS NULL AND (rincian LIKE '%pembuatan%' OR rincian LIKE '%deadline%'))");
        } elseif ($segmen === 'high_spender') {
            $builder->where('cp.nilai >=', 2000000);
        }
    }

    private function hitungStatusOtomatis(array $p): string
    {
        $jml = (int) ($p['jumlah_order'] ?? 0);
        if ($jml === 0) {
            return 'prospek';
        }
        if ($jml >= 3) {
            return 'loyal';
        }
        if ($jml === 1) {
            return 'baru';
        }
        $terakhir = $p['order_terakhir'] ?? null;
        if ($terakhir && strtotime($terakhir) < strtotime('-60 days')) {
            return 'tidak_aktif';
        }
        return 'aktif';
    }

    /**
     * Mengambil detail Customer 360 lengkap
     */
    public function getPelangganDetail(int $id_pelanggan): ?array
    {
        $pPro = $this->db->prefixTable('crm_pelanggan_profil');
        $pPel = $this->db->prefixTable('pelanggan');
        $pKon = $this->db->prefixTable('pelanggan_kontak');

        // Ambil data dari crm_pelanggan view
        $row = $this->db->table('crm_pelanggan cp')
            ->select('cp.*, pro.status_crm, pro.opt_in_broadcast, pro.catatan as catatan_crm,
                      pro.preferensi_warna, pro.preferensi_bahan, pro.preferensi_model,
                      pro.ukuran_baju_standar, pro.ukuran_celana_standar, pro.ukuran_jas_standar,
                      pro.detail_ukuran, pro.updated_at as profil_updated_at')
            ->join("{$pPro} pro", 'pro.pelanggan_id = cp.id_pelanggan', 'left')
            ->where('cp.id_pelanggan', $id_pelanggan)
            ->get()
            ->getRowArray();

        if (!$row) {
            // Cek di tabel asli jika di view belum muncul
            $raw = $this->db->table('pelanggan')->where('id_pelanggan', $id_pelanggan)->where('deleted_at IS NULL')->get()->getRowArray();
            if (!$raw) {
                return null;
            }
            $row = [
                'id_pelanggan'    => $raw['id_pelanggan'],
                'nama_pelanggan'  => $raw['nama_pelanggan'],
                'cod'             => $raw['cod'],
                'alamat'          => $raw['alamat'],
                'kodepos'         => $raw['kodepos'],
                'terdaftar_sejak' => $raw['created_at'],
                'provinsi'        => '',
                'kota'            => '',
                'kecamatan'       => '',
                'nomor_utama'     => '',
                'jumlah_nomor'    => 0,
                'jumlah_order'    => 0,
                'qty'             => 0,
                'nilai'           => 0,
                'dibayar'         => 0,
                'sisa'            => 0,
                'order_pertama'   => null,
                'order_terakhir'  => null,
                'status_crm'      => 'prospek',
                'opt_in_broadcast'=> 1,
                'catatan_crm'     => '',
            ];
        }

        // Parse detail ukuran JSON jika ada
        if (!empty($row['detail_ukuran'])) {
            $row['detail_ukuran_array'] = json_decode($row['detail_ukuran'], true) ?: [];
        } else {
            $row['detail_ukuran_array'] = [];
        }

        // Ambil semua nomor kontak pelanggan
        $kontak = $this->db->table('pelanggan_kontak')
            ->where('pelanggan_id', $id_pelanggan)
            ->orderBy('urutan', 'ASC')
            ->get()
            ->getResultArray();
        $row['kontak_list'] = $kontak;
        if (empty($row['nomor_utama']) && !empty($kontak)) {
            $row['nomor_utama'] = $kontak[0]['nomor'];
        }

        // Ambil Tags
        $row['tags'] = $this->getPelangganTags($id_pelanggan);

        // Status CRM efektif
        if (empty($row['status_crm'])) {
            $row['status_crm'] = $this->hitungStatusOtomatis($row);
        }

        return $row;
    }

    /**
     * Mengambil ringkasan nilai dan kontribusi customer (Total, Rata-rata, Penjualan/Custom/Rental)
     */
    public function getRingkasanNilaiPelanggan(int $id_pelanggan): array
    {
        $pInv = $this->db->prefixTable('invoice');
        $pDib = $this->db->prefixTable('dibeli');
        $pBi  = $this->db->prefixTable('biaya');
        $pBay = $this->db->prefixTable('pembayaran');

        $sql = "SELECT 
            inv.id_invoice,
            inv.seri,
            inv.tanggal_pesan,
            inv.rincian,
            COALESCE(b.total_barang, 0) + COALESCE(bc.total_biaya, 0) as total_invoice,
            COALESCE(pm.total_bayar, 0) as telah_dibayar
        FROM `{$pInv}` inv
        LEFT JOIN (
            SELECT invoice_id, SUM(qty * harga) as total_barang 
            FROM `{$pDib}` GROUP BY invoice_id
        ) b ON b.invoice_id = inv.id_invoice
        LEFT JOIN (
            SELECT invoice_id, SUM(nominal) as total_biaya 
            FROM `{$pBi}` GROUP BY invoice_id
        ) bc ON bc.invoice_id = inv.id_invoice
        LEFT JOIN (
            SELECT invoice_id, SUM(total_pembayaran) as total_bayar 
            FROM `{$pBay}` GROUP BY invoice_id
        ) pm ON pm.invoice_id = inv.id_invoice
        WHERE inv.pemesan_id = ? AND inv.deleted_at IS NULL
        ORDER BY inv.tanggal_pesan DESC";

        $invoices = $this->db->query($sql, [$id_pelanggan])->getResultArray();

        $totalTransaksi = count($invoices);
        $totalPengeluaran = 0;
        $totalDibayar = 0;
        $countPenjualan = 0;
        $countCustom = 0;
        $countSewa = 0;
        $transaksiTerakhir = null;

        foreach ($invoices as $inv) {
            $totalPengeluaran += (int) $inv['total_invoice'];
            $totalDibayar += (int) $inv['telah_dibayar'];
            if (!$transaksiTerakhir) {
                $transaksiTerakhir = $inv['tanggal_pesan'];
            }

            // Tentukan tipe order dari rincian
            $rincian = json_decode((string) $inv['rincian'], true) ?: [];
            $tipe = strtolower($rincian['tipe'] ?? '');
            if ($tipe === 'sewa' || !empty($rincian['ambil']) || !empty($rincian['kembali'])) {
                $countSewa++;
            } elseif ($tipe === 'pembuatan' || !empty($rincian['deadline'])) {
                $countCustom++;
            } else {
                $countPenjualan++;
            }
        }

        $rataRata = $totalTransaksi > 0 ? (int) round($totalPengeluaran / $totalTransaksi) : 0;
        $sisaPiutang = max(0, $totalPengeluaran - $totalDibayar);

        return [
            'total_transaksi'    => $totalTransaksi,
            'total_pengeluaran'  => $totalPengeluaran,
            'total_dibayar'      => $totalDibayar,
            'rata_rata_nilai'    => $rataRata,
            'sisa_piutang'       => $sisaPiutang,
            'count_penjualan'    => $countPenjualan,
            'count_custom'       => $countCustom,
            'count_sewa'         => $countSewa,
            'transaksi_terakhir' => $transaksiTerakhir,
        ];
    }

    /**
     * Mengambil riwayat transaksi POS lengkap untuk customer
     */
    public function getRiwayatTransaksiPelanggan(int $id_pelanggan): array
    {
        $pInv = $this->db->prefixTable('invoice');
        $pDib = $this->db->prefixTable('dibeli');
        $pBi  = $this->db->prefixTable('biaya');
        $pBay = $this->db->prefixTable('pembayaran');
        $pJur = $this->db->prefixTable('juragan');

        $sql = "SELECT 
            inv.id_invoice,
            inv.seri,
            inv.tanggal_pesan,
            inv.status_pesanan,
            inv.status_pembayaran,
            inv.status_pengiriman,
            inv.keterangan,
            inv.rincian,
            jur.nama_juragan,
            COALESCE(b.total_barang, 0) + COALESCE(bc.total_biaya, 0) as total_tagihan,
            COALESCE(pm.total_bayar, 0) as telah_dibayar,
            (COALESCE(b.total_barang, 0) + COALESCE(bc.total_biaya, 0)) - COALESCE(pm.total_bayar, 0) as sisa_tagihan
        FROM `{$pInv}` inv
        JOIN `{$pJur}` jur ON jur.id_juragan = inv.juragan_id
        LEFT JOIN (
            SELECT invoice_id, SUM(qty * harga) as total_barang 
            FROM `{$pDib}` GROUP BY invoice_id
        ) b ON b.invoice_id = inv.id_invoice
        LEFT JOIN (
            SELECT invoice_id, SUM(nominal) as total_biaya 
            FROM `{$pBi}` GROUP BY invoice_id
        ) bc ON bc.invoice_id = inv.id_invoice
        LEFT JOIN (
            SELECT invoice_id, SUM(total_pembayaran) as total_bayar 
            FROM `{$pBay}` GROUP BY invoice_id
        ) pm ON pm.invoice_id = inv.id_invoice
        WHERE inv.pemesan_id = ? AND inv.deleted_at IS NULL
        ORDER BY inv.tanggal_pesan DESC, inv.id_invoice DESC";

        $rows = $this->db->query($sql, [$id_pelanggan])->getResultArray();

        // Ambil item produk untuk masing-masing invoice
        foreach ($rows as &$r) {
            $rincian = json_decode((string) $r['rincian'], true) ?: [];
            $tipe = strtolower($rincian['tipe'] ?? '');
            if ($tipe === 'sewa' || !empty($rincian['ambil']) || !empty($rincian['kembali'])) {
                $r['tipe_label'] = 'Sewa / Rental';
                $r['tipe_badge'] = 'info';
            } elseif ($tipe === 'pembuatan' || !empty($rincian['deadline'])) {
                $r['tipe_label'] = 'Custom / Pembuatan';
                $r['tipe_badge'] = 'success';
            } else {
                $r['tipe_label'] = 'Penjualan Ready';
                $r['tipe_badge'] = 'primary';
            }
            $r['rincian_data'] = $rincian;

            // Ambil items produk dibeli
            $items = $this->db->table('dibeli')
                ->where('invoice_id', $r['id_invoice'])
                ->get()
                ->getResultArray();
            $r['items'] = $items;
        }

        return $rows;
    }

    /**
     * Mengambil riwayat mutasi pembayaran customer
     */
    public function getRiwayatPembayaranPelanggan(int $id_pelanggan): array
    {
        $pInv = $this->db->prefixTable('invoice');
        $pBay = $this->db->prefixTable('pembayaran');
        $pBnk = $this->db->prefixTable('bank');

        $sql = "SELECT 
            pm.id_pembayaran,
            pm.invoice_id,
            pm.total_pembayaran,
            pm.status,
            pm.tanggal_pembayaran,
            pm.tanggal_cek,
            inv.seri as invoice_seri,
            COALESCE(bnk.nama_bank, 'Tunai / Manual') as nama_bank,
            COALESCE(bnk.atas_nama, '') as atas_nama
        FROM `{$pBay}` pm
        JOIN `{$pInv}` inv ON inv.id_invoice = pm.invoice_id
        LEFT JOIN `{$pBnk}` bnk ON bnk.id_bank = pm.sumber_dana
        WHERE inv.pemesan_id = ? AND inv.deleted_at IS NULL
        ORDER BY pm.tanggal_pembayaran DESC, pm.id_pembayaran DESC";

        return $this->db->query($sql, [$id_pelanggan])->getResultArray();
    }

    /**
     * Mengambil timeline aktivitas multi-channel untuk customer
     */
    public function getTimelinePelanggan(int $id_pelanggan): array
    {
        $timeline = [];

        // 1. Aktivitas CRM (catatan staff, fitting, telepon, pengambilan, pengembalian)
        $aktList = $this->db->table('crm_aktivitas a')
            ->select('a.*, u.name as nama_staff')
            ->join('user u', 'u.id = a.user_id', 'left')
            ->where('a.pelanggan_id', $id_pelanggan)
            ->orderBy('a.created_at', 'DESC')
            ->get()
            ->getResultArray();

        foreach ($aktList as $a) {
            $timeline[] = [
                'id'        => 'akt_' . $a['id_aktivitas'],
                'waktu'     => (int) $a['created_at'],
                'tipe'      => $a['tipe'],
                'judul'     => $a['judul'],
                'deskripsi' => $a['deskripsi'],
                'icon'      => $this->getTimelineIcon($a['tipe']),
                'warna'     => $this->getTimelineWarna($a['tipe']),
                'actor'     => $a['nama_staff'] ?? 'Staff',
                'ref_id'    => $a['invoice_id'],
            ];
        }

        // 2. Transaksi Invoice Dibuat
        $invList = $this->db->table('invoice')
            ->where('pemesan_id', $id_pelanggan)
            ->where('deleted_at IS NULL')
            ->orderBy('created_at', 'DESC')
            ->get()
            ->getResultArray();

        foreach ($invList as $inv) {
            $rincian = json_decode((string) $inv['rincian'], true) ?: [];
            $tipePesanan = $rincian['tipe'] ?? 'penjualan';
            $judul = "Order dibuat: Invoice #{$inv['seri']} (" . ucfirst($tipePesanan) . ")";
            $timeline[] = [
                'id'        => 'inv_' . $inv['id_invoice'],
                'waktu'     => (int) $inv['created_at'],
                'tipe'      => 'order',
                'judul'     => $judul,
                'deskripsi' => !empty($inv['keterangan']) ? $inv['keterangan'] : "Pesanan berhasil dibuat di POS.",
                'icon'      => 'fa-receipt',
                'warna'     => 'primary',
                'actor'     => 'Sistem POS',
                'ref_id'    => $inv['id_invoice'],
            ];
        }

        // 3. Pembayaran Transaksi
        $bayarList = $this->getRiwayatPembayaranPelanggan($id_pelanggan);
        foreach ($bayarList as $b) {
            $timeline[] = [
                'id'        => 'pay_' . $b['id_pembayaran'],
                'waktu'     => (int) $b['tanggal_pembayaran'],
                'tipe'      => 'pembayaran',
                'judul'     => 'Pembayaran diterima: Rp ' . number_format($b['total_pembayaran'], 0, ',', '.'),
                'deskripsi' => "Untuk Invoice #{$b['invoice_seri']} via {$b['nama_bank']}",
                'icon'      => 'fa-check-circle',
                'warna'     => 'success',
                'actor'     => 'Kasir',
                'ref_id'    => $b['invoice_id'],
            ];
        }

        // 4. Riwayat Chat WhatsApp
        // Cari nomor kontak customer
        $kontakList = $this->db->table('pelanggan_kontak')
            ->where('pelanggan_id', $id_pelanggan)
            ->get()
            ->getResultArray();

        $nomorPureList = array_filter(array_column($kontakList, 'nomor_pure'));
        if (!empty($nomorPureList)) {
            $pChat = $this->db->prefixTable('crm_chat_percakapan');
            $pMsg  = $this->db->prefixTable('crm_chat_pesan');
            $placeholders = implode(',', array_fill(0, count($nomorPureList), '?'));

            $sqlChat = "SELECT m.*, u.name as nama_staff 
                FROM `{$pMsg}` m
                JOIN `{$pChat}` c ON c.id_percakapan = m.percakapan_id
                LEFT JOIN `{$this->db->prefixTable('user')}` u ON u.id = m.user_id
                WHERE (c.pelanggan_id = ? OR m.nomor_wa IN ({$placeholders}) OR REGEXP_REPLACE(m.nomor_wa, '[^0-9]', '') IN ({$placeholders}))
                ORDER BY m.created_at DESC LIMIT 30";

            $params = array_merge([$id_pelanggan], $nomorPureList, $nomorPureList);
            $chatMsgs = $this->db->query($sqlChat, $params)->getResultArray();

            foreach ($chatMsgs as $msg) {
                $arah = $msg['arah'] === 'masuk' ? 'Customer' : ($msg['nama_staff'] ?? 'Staff');
                $judul = $msg['arah'] === 'masuk' ? 'Pesan WhatsApp dari Customer' : 'Pesan WhatsApp dikirim ke Customer';
                $timeline[] = [
                    'id'        => 'chat_' . $msg['id_pesan'],
                    'waktu'     => (int) $msg['created_at'],
                    'tipe'      => 'wa',
                    'judul'     => $judul,
                    'deskripsi' => $msg['isi_pesan'],
                    'icon'      => 'fa-whatsapp',
                    'warna'     => $msg['arah'] === 'masuk' ? 'success' : 'info',
                    'actor'     => $arah,
                    'ref_id'    => null,
                ];
            }
        }

        // 5. Follow-up Selesai
        $folList = $this->db->table('crm_followup f')
            ->select('f.*, u.name as nama_staff')
            ->join('user u', 'u.id = f.selesai_oleh', 'left')
            ->where('f.pelanggan_id', $id_pelanggan)
            ->where('f.status', 'selesai')
            ->get()
            ->getResultArray();

        foreach ($folList as $f) {
            $timeline[] = [
                'id'        => 'fol_' . $f['id_followup'],
                'waktu'     => (int) ($f['selesai_at'] ?: $f['updated_at']),
                'tipe'      => 'follow_up',
                'judul'     => 'Follow-up diselesaikan: ' . $f['judul'],
                'deskripsi' => $f['catatan'] ?? '',
                'icon'      => 'fa-clipboard-check',
                'warna'     => 'warning',
                'actor'     => $f['nama_staff'] ?? 'Staff',
                'ref_id'    => $f['invoice_id'],
            ];
        }

        // Urutkan timeline dari yang paling baru
        usort($timeline, static function ($a, $b) {
            return $b['waktu'] <=> $a['waktu'];
        });

        return $timeline;
    }

    private function getTimelineIcon(string $tipe): string
    {
        return match ($tipe) {
            'wa'           => 'fa-whatsapp',
            'telepon'      => 'fa-phone-alt',
            'datang'       => 'fa-store',
            'fitting'      => 'fa-tape',
            'pembayaran'   => 'fa-money-bill-wave',
            'order'        => 'fa-shopping-bag',
            'pengambilan'  => 'fa-box-check',
            'pengembalian' => 'fa-undo',
            'follow_up'    => 'fa-calendar-check',
            'broadcast'    => 'fa-bullhorn',
            'sistem'       => 'fa-cog',
            default        => 'fa-comment-alt-lines',
        };
    }

    private function getTimelineWarna(string $tipe): string
    {
        return match ($tipe) {
            'wa'           => 'success',
            'telepon'      => 'primary',
            'datang'       => 'info',
            'fitting'      => 'warning',
            'pembayaran'   => 'success',
            'order'        => 'primary',
            'pengambilan'  => 'dark',
            'pengembalian' => 'secondary',
            'follow_up'    => 'warning',
            'broadcast'    => 'info',
            default        => 'secondary',
        };
    }

    /**
     * Tambah aktivitas manual / catatan ke timeline
     */
    public function tambahAktivitas(array $data): int
    {
        $payload = [
            'pelanggan_id' => (int) $data['pelanggan_id'],
            'tipe'         => $data['tipe'] ?? 'catatan',
            'judul'        => $data['judul'],
            'deskripsi'    => $data['deskripsi'] ?? null,
            'invoice_id'   => !empty($data['invoice_id']) ? (int) $data['invoice_id'] : null,
            'user_id'      => !empty($data['user_id']) ? (int) $data['user_id'] : null,
            'created_at'   => time(),
        ];

        $this->db->table('crm_aktivitas')->insert($payload);
        return $this->db->insertID();
    }

    /**
     * Simpan profil status CRM, opt-in broadcast, dan catatan customer
     */
    public function simpanProfilCrm(int $pelanggan_id, array $data): bool
    {
        $existing = $this->db->table('crm_pelanggan_profil')->where('pelanggan_id', $pelanggan_id)->get()->getRowArray();
        $time = time();

        $updateData = [];
        if (isset($data['status_crm'])) {
            $updateData['status_crm'] = $data['status_crm'];
        }
        if (isset($data['opt_in_broadcast'])) {
            $updateData['opt_in_broadcast'] = (int) $data['opt_in_broadcast'];
        }
        if (isset($data['catatan'])) {
            $updateData['catatan'] = $data['catatan'];
        }
        $updateData['updated_at'] = $time;

        if ($existing) {
            return $this->db->table('crm_pelanggan_profil')->where('pelanggan_id', $pelanggan_id)->update($updateData);
        } else {
            $updateData['pelanggan_id'] = $pelanggan_id;
            $updateData['created_at']   = $time;
            return $this->db->table('crm_pelanggan_profil')->insert($updateData);
        }
    }

    /**
     * Simpan data ukuran dan preferensi pakaian customer
     */
    public function simpanUkuranPreferensi(int $pelanggan_id, array $data): bool
    {
        $existing = $this->db->table('crm_pelanggan_profil')->where('pelanggan_id', $pelanggan_id)->get()->getRowArray();
        $time = time();

        $updateData = [
            'preferensi_warna'      => $data['preferensi_warna'] ?? null,
            'preferensi_bahan'      => $data['preferensi_bahan'] ?? null,
            'preferensi_model'      => $data['preferensi_model'] ?? null,
            'ukuran_baju_standar'   => $data['ukuran_baju_standar'] ?? null,
            'ukuran_celana_standar' => $data['ukuran_celana_standar'] ?? null,
            'ukuran_jas_standar'    => $data['ukuran_jas_standar'] ?? null,
            'detail_ukuran'         => !empty($data['detail_ukuran']) ? (is_array($data['detail_ukuran']) ? json_encode($data['detail_ukuran']) : $data['detail_ukuran']) : null,
            'updated_at'            => $time,
        ];

        if ($existing) {
            return $this->db->table('crm_pelanggan_profil')->where('pelanggan_id', $pelanggan_id)->update($updateData);
        } else {
            $updateData['pelanggan_id'] = $pelanggan_id;
            $updateData['created_at']   = $time;
            return $this->db->table('crm_pelanggan_profil')->insert($updateData);
        }
    }

    /**
     * Master Tags & Relasi Tag Pelanggan
     */
    public function getAllTags(): array
    {
        return $this->db->table('crm_tag')->orderBy('nama_tag', 'ASC')->get()->getResultArray();
    }

    public function getPelangganTags(int $pelanggan_id): array
    {
        $pTag = $this->db->prefixTable('crm_tag');
        $pRel = $this->db->prefixTable('crm_pelanggan_tag');

        return $this->db->query("SELECT t.* 
            FROM `{$pTag}` t
            JOIN `{$pRel}` r ON r.tag_id = t.id_tag
            WHERE r.pelanggan_id = ?
            ORDER BY t.nama_tag ASC", [$pelanggan_id])->getResultArray();
    }

    public function getTagsUntukPelangganList(array $pelanggan_ids): array
    {
        if (empty($pelanggan_ids)) {
            return [];
        }

        $pTag = $this->db->prefixTable('crm_tag');
        $pRel = $this->db->prefixTable('crm_pelanggan_tag');
        $in   = implode(',', array_map('intval', $pelanggan_ids));

        $rows = $this->db->query("SELECT r.pelanggan_id, t.* 
            FROM `{$pTag}` t
            JOIN `{$pRel}` r ON r.tag_id = t.id_tag
            WHERE r.pelanggan_id IN ({$in})
            ORDER BY t.nama_tag ASC")->getResultArray();

        $result = [];
        foreach ($rows as $row) {
            $result[$row['pelanggan_id']][] = $row;
        }

        return $result;
    }

    public function tambahTagPelanggan(int $pelanggan_id, int $tag_id): bool
    {
        $exists = $this->db->table('crm_pelanggan_tag')
            ->where('pelanggan_id', $pelanggan_id)
            ->where('tag_id', $tag_id)
            ->countAllResults();

        if ($exists === 0) {
            return $this->db->table('crm_pelanggan_tag')->insert([
                'pelanggan_id' => $pelanggan_id,
                'tag_id'       => $tag_id,
                'created_at'   => time(),
            ]);
        }
        return true;
    }

    public function hapusTagPelanggan(int $pelanggan_id, int $tag_id): bool
    {
        return $this->db->table('crm_pelanggan_tag')
            ->where('pelanggan_id', $pelanggan_id)
            ->where('tag_id', $tag_id)
            ->delete();
    }

    public function buatMasterTag(string $nama, string $warna = 'primary'): int
    {
        $existing = $this->db->table('crm_tag')->where('nama_tag', $nama)->get()->getRowArray();
        if ($existing) {
            return (int) $existing['id_tag'];
        }
        $this->db->table('crm_tag')->insert([
            'nama_tag'   => $nama,
            'warna'      => $warna,
            'created_at' => time(),
        ]);
        return $this->db->insertID();
    }

    /**
     * Follow-up Customer & Reminder
     */
    public function getFollowupList(array $filter = [], int $limit = 30, int $offset = 0): array
    {
        $pFol = $this->db->prefixTable('crm_followup');
        $pPel = $this->db->prefixTable('pelanggan');
        $pKon = $this->db->prefixTable('pelanggan_kontak');
        $pUsr = $this->db->prefixTable('user');
        $pInv = $this->db->prefixTable('invoice');

        $builder = $this->db->table('crm_followup f');
        $builder->select('f.*, p.nama_pelanggan, kon.nomor as nomor_wa, u.name as nama_staff, inv.seri as invoice_seri');
        $builder->join('pelanggan p', 'p.id_pelanggan = f.pelanggan_id');
        $builder->join('user u', 'u.id = f.user_id', 'left');
        $builder->join('invoice inv', 'inv.id_invoice = f.invoice_id', 'left');
        $builder->join("(SELECT pelanggan_id, MIN(nomor) as nomor FROM `{$pKon}` GROUP BY pelanggan_id) kon", 'kon.pelanggan_id = p.id_pelanggan', 'left');

        if (!empty($filter['tipe'])) {
            $builder->where('f.tipe', $filter['tipe']);
        }
        if (!empty($filter['status'])) {
            $builder->where('f.status', $filter['status']);
        }
        if (!empty($filter['pelanggan_id'])) {
            $builder->where('f.pelanggan_id', (int) $filter['pelanggan_id']);
        }
        if (!empty($filter['cari'])) {
            $c = $filter['cari'];
            $builder->groupStart()
                ->like('p.nama_pelanggan', $c)
                ->orLike('f.judul', $c)
                ->orLike('f.catatan', $c)
                ->groupEnd();
        }

        $builder->orderBy('f.status', 'ASC'); // 'menunggu' duluan
        $builder->orderBy('f.tanggal_jatuh_tempo', 'ASC');
        $builder->orderBy('f.id_followup', 'DESC');

        return $builder->get($limit, $offset)->getResultArray();
    }

    public function simpanFollowup(array $data): int
    {
        $time = time();
        $payload = [
            'pelanggan_id'        => (int) $data['pelanggan_id'],
            'tipe'                => $data['tipe'] ?? 'customer',
            'kategori'            => $data['kategori'] ?? 'lainnya',
            'judul'               => $data['judul'],
            'catatan'             => $data['catatan'] ?? null,
            'tanggal_jatuh_tempo' => $data['tanggal_jatuh_tempo'] ?? date('Y-m-d'),
            'status'              => 'menunggu',
            'invoice_id'          => !empty($data['invoice_id']) ? (int) $data['invoice_id'] : null,
            'user_id'             => !empty($data['user_id']) ? (int) $data['user_id'] : null,
            'created_at'          => $time,
            'updated_at'          => $time,
        ];

        $this->db->table('crm_followup')->insert($payload);
        return $this->db->insertID();
    }

    public function selesaikanFollowup(int $id_followup, int $user_id, string $catatanPenyelesaian = ''): bool
    {
        $time = time();
        $fol = $this->db->table('crm_followup')->where('id_followup', $id_followup)->get()->getRowArray();
        if (!$fol) {
            return false;
        }

        $update = [
            'status'       => 'selesai',
            'selesai_oleh' => $user_id,
            'selesai_at'   => $time,
            'updated_at'   => $time,
        ];
        if (!empty($catatanPenyelesaian)) {
            $update['catatan'] = ($fol['catatan'] ? $fol['catatan'] . "\n---\nPenyelesaian: " : "Penyelesaian: ") . $catatanPenyelesaian;
        }

        $this->db->table('crm_followup')->where('id_followup', $id_followup)->update($update);

        // Catat ke timeline pelanggan
        $this->tambahAktivitas([
            'pelanggan_id' => $fol['pelanggan_id'],
            'tipe'         => 'follow_up',
            'judul'        => 'Follow-up diselesaikan: ' . $fol['judul'],
            'deskripsi'    => $catatanPenyelesaian ?: $fol['catatan'],
            'invoice_id'   => $fol['invoice_id'],
            'user_id'      => $user_id,
        ]);

        return true;
    }

    /**
     * Mengambil Pengingat Otomatis / Reminders:
     * 1. Pengambilan pakaian pesanan custom/selesai
     * 2. Pengembalian pakaian sewa
     * 3. Jadwal fitting
     * 4. Follow-up yang jatuh tempo hari ini/lewat
     */
    public function getReminders(): array
    {
        $reminders = [];
        $today = date('Y-m-d');
        $pInv = $this->db->prefixTable('invoice');
        $pPel = $this->db->prefixTable('pelanggan');
        $pKon = $this->db->prefixTable('pelanggan_kontak');

        // 1. Follow-up menunggu jatuh tempo hari ini atau terlambat
        $followups = $this->db->table('crm_followup f')
            ->select('f.*, p.nama_pelanggan, kon.nomor as nomor_wa')
            ->join('pelanggan p', 'p.id_pelanggan = f.pelanggan_id')
            ->join("(SELECT pelanggan_id, MIN(nomor) as nomor FROM `{$pKon}` GROUP BY pelanggan_id) kon", 'kon.pelanggan_id = p.id_pelanggan', 'left')
            ->where('f.status', 'menunggu')
            ->where('f.tanggal_jatuh_tempo <=', date('Y-m-d', strtotime('+3 days')))
            ->orderBy('f.tanggal_jatuh_tempo', 'ASC')
            ->get()
            ->getResultArray();

        foreach ($followups as $f) {
            $isTerlambat = $f['tanggal_jatuh_tempo'] < $today;
            $reminders[] = [
                'kategori'    => 'followup',
                'judul'       => $f['judul'],
                'deskripsi'   => $f['catatan'] ?: 'Follow-up kepada ' . $f['nama_pelanggan'],
                'tanggal'     => $f['tanggal_jatuh_tempo'],
                'status_waktu'=> $isTerlambat ? 'Terlambat' : ($f['tanggal_jatuh_tempo'] === $today ? 'Hari Ini' : 'Mendatang'),
                'warna'       => $isTerlambat ? 'danger' : ($f['tanggal_jatuh_tempo'] === $today ? 'warning' : 'info'),
                'pelanggan_id'=> $f['pelanggan_id'],
                'nama_pelanggan' => $f['nama_pelanggan'],
                'nomor_wa'    => $f['nomor_wa'],
                'id_followup' => $f['id_followup'],
            ];
        }

        // 2. Invoice Sewa & Custom dari rincian JSON (diambil, dikembalikan, deadline)
        $sqlRincian = "SELECT 
            inv.id_invoice,
            inv.seri,
            inv.rincian,
            inv.status_pesanan,
            inv.pemesan_id,
            pel.nama_pelanggan,
            COALESCE(kon.nomor, '') as nomor_wa
        FROM `{$pInv}` inv
        JOIN `{$pPel}` pel ON pel.id_pelanggan = inv.pemesan_id
        LEFT JOIN (
            SELECT pelanggan_id, MIN(nomor) as nomor FROM `{$pKon}` GROUP BY pelanggan_id
        ) kon ON kon.pelanggan_id = pel.id_pelanggan
        WHERE inv.deleted_at IS NULL AND inv.status_pesanan IN ('1','2') AND inv.rincian IS NOT NULL
        ORDER BY inv.id_invoice DESC LIMIT 100";

        $invs = $this->db->query($sqlRincian)->getResultArray();

        foreach ($invs as $inv) {
            $r = json_decode((string) $inv['rincian'], true) ?: [];
            // Cek jadwal ambil sewa
            if (!empty($r['ambil'])) {
                $tglAmbil = $r['ambil'];
                if (strtotime($tglAmbil) >= strtotime('-3 days') && strtotime($tglAmbil) <= strtotime('+7 days')) {
                    $terlambat = $tglAmbil < $today;
                    $reminders[] = [
                        'kategori'    => 'pengambilan_sewa',
                        'judul'       => "Pengambilan Sewa: Invoice #{$inv['seri']}",
                        'deskripsi'   => "Customer {$inv['nama_pelanggan']} dijadwalkan mengambil pakaian sewa.",
                        'tanggal'     => $tglAmbil,
                        'status_waktu'=> $terlambat ? 'Terlambat Diambil' : ($tglAmbil === $today ? 'Hari Ini' : 'Mendatang'),
                        'warna'       => $terlambat ? 'danger' : 'primary',
                        'pelanggan_id'=> $inv['pemesan_id'],
                        'nama_pelanggan' => $inv['nama_pelanggan'],
                        'nomor_wa'    => $inv['nomor_wa'],
                        'invoice_id'  => $inv['id_invoice'],
                    ];
                }
            }
            // Cek jadwal kembali sewa
            if (!empty($r['kembali'])) {
                $tglKembali = $r['kembali'];
                if (strtotime($tglKembali) >= strtotime('-3 days') && strtotime($tglKembali) <= strtotime('+7 days')) {
                    $terlambat = $tglKembali < $today;
                    $reminders[] = [
                        'kategori'    => 'pengembalian_sewa',
                        'judul'       => "Pengembalian Sewa: Invoice #{$inv['seri']}",
                        'deskripsi'   => "Batas pengembalian pakaian sewa oleh {$inv['nama_pelanggan']}.",
                        'tanggal'     => $tglKembali,
                        'status_waktu'=> $terlambat ? 'Terlambat Kembali' : ($tglKembali === $today ? 'Hari Ini' : 'Mendatang'),
                        'warna'       => $terlambat ? 'danger' : 'warning',
                        'pelanggan_id'=> $inv['pemesan_id'],
                        'nama_pelanggan' => $inv['nama_pelanggan'],
                        'nomor_wa'    => $inv['nomor_wa'],
                        'invoice_id'  => $inv['id_invoice'],
                    ];
                }
            }
            // Cek deadline custom
            if (!empty($r['deadline'])) {
                $tglDeadline = $r['deadline'];
                if (strtotime($tglDeadline) >= strtotime('-3 days') && strtotime($tglDeadline) <= strtotime('+7 days')) {
                    $terlambat = $tglDeadline < $today;
                    $reminders[] = [
                        'kategori'    => 'deadline_custom',
                        'judul'       => "Deadline Custom: Invoice #{$inv['seri']}",
                        'deskripsi'   => "Target penyelesaian pembuatan pakaian {$inv['nama_pelanggan']}.",
                        'tanggal'     => $tglDeadline,
                        'status_waktu'=> $terlambat ? 'Lewat Deadline' : ($tglDeadline === $today ? 'Hari Ini' : 'Mendatang'),
                        'warna'       => $terlambat ? 'danger' : 'info',
                        'pelanggan_id'=> $inv['pemesan_id'],
                        'nama_pelanggan' => $inv['nama_pelanggan'],
                        'nomor_wa'    => $inv['nomor_wa'],
                        'invoice_id'  => $inv['id_invoice'],
                    ];
                }
            }
        }

        // Urutkan reminders berdasarkan tanggal
        usort($reminders, static function ($a, $b) {
            return strcmp($a['tanggal'], $b['tanggal']);
        });

        return $reminders;
    }

    /**
     * Mencocokkan nomor WhatsApp secara otomatis dengan customer di database
     */
    public function cariCustomerByWa(string $nomor): ?array
    {
        $clean = preg_replace('/[^0-9]/', '', $nomor);
        if (strlen($clean) < 8) {
            return null;
        }

        // Siapkan variasi nomor: 628... vs 08...
        $variasi = [$clean];
        if (str_starts_with($clean, '62')) {
            $variasi[] = '0' . substr($clean, 2);
        } elseif (str_starts_with($clean, '0')) {
            $variasi[] = '62' . substr($clean, 1);
        }

        $pKon = $this->db->prefixTable('pelanggan_kontak');
        $placeholders = implode(',', array_fill(0, count($variasi), '?'));

        $sql = "SELECT pelanggan_id FROM `{$pKon}` WHERE nomor_pure IN ({$placeholders}) LIMIT 1";
        $row = $this->db->query($sql, $variasi)->getRowArray();

        if ($row) {
            return $this->getPelangganDetail((int) $row['pelanggan_id']);
        }

        return null;
    }

    /**
     * Buat Customer Baru langsung dari percakapan WhatsApp
     */
    public function buatCustomerDariWa(string $nomor, string $nama, string $kota = '', string $alamat = '', int $user_id = 0): array
    {
        $clean = preg_replace('/[^0-9]/', '', $nomor);
        $time = time();

        // 1. Cek apakah nomor sudah ada
        $existing = $this->cariCustomerByWa($clean);
        if ($existing) {
            return [
                'success' => false,
                'message' => 'Nomor ini sudah terdaftar sebagai: ' . $existing['nama_pelanggan'],
                'data'    => $existing,
            ];
        }

        // 2. Format nomor standar
        $nomorSimpan = $clean;
        if (str_starts_with($clean, '62')) {
            $nomorSimpan = '0' . substr($clean, 2);
        }

        // 3. Insert ke order_pelanggan
        $dataPelanggan = [
            'nama_pelanggan' => trim($nama),
            'hp'             => json_encode([$nomorSimpan]),
            'alamat'         => trim($alamat) ?: null,
            'created_at'     => $time,
            'updated_at'     => $time,
        ];
        $this->db->table('pelanggan')->insert($dataPelanggan);
        $pelangganId = $this->db->insertID();

        // 4. Insert ke order_pelanggan_kontak
        $this->db->table('pelanggan_kontak')->insert([
            'pelanggan_id' => $pelangganId,
            'nomor'        => $nomorSimpan,
            'urutan'       => 1,
            'created_at'   => $time,
        ]);

        // 5. Inisialisasi profil CRM
        $this->db->table('crm_pelanggan_profil')->insert([
            'pelanggan_id'     => $pelangganId,
            'status_crm'       => 'prospek', // Default baru dari WA adalah prospek
            'opt_in_broadcast' => 1,
            'catatan'          => "Didaftarkan dari percakapan WhatsApp ({$nomor}).",
            'created_at'       => $time,
            'updated_at'       => $time,
        ]);

        // 6. Hubungkan dengan percakapan chat jika ada
        $this->db->table('crm_chat_percakapan')
            ->where('nomor_wa', $clean)
            ->orWhere('nomor_wa', $nomor)
            ->orWhere('nomor_wa', $nomorSimpan)
            ->update([
                'pelanggan_id' => $pelangganId,
                'nama_kontak'  => trim($nama),
            ]);

        // 7. Catat aktivitas
        $this->tambahAktivitas([
            'pelanggan_id' => $pelangganId,
            'tipe'         => 'wa',
            'judul'        => 'Customer pertama kali terdaftar melalui WhatsApp',
            'deskripsi'    => "Nomor {$nomor} didaftarkan oleh staff.",
            'user_id'      => $user_id,
        ]);

        $detail = $this->getPelangganDetail($pelangganId);

        return [
            'success' => true,
            'message' => 'Customer berhasil didaftarkan ke CRM!',
            'data'    => $detail,
        ];
    }

    /**
     * Customer Duplicate / Merge
     */
    public function getDuplikatList(): array
    {
        try {
            return $this->db->query('SELECT * FROM crm_pelanggan_duplikat LIMIT 100')->getResultArray();
        } catch (\Throwable $e) {
            return [];
        }
    }

    public function mergeCustomer(int $id_utama, int $id_duplikat, int $user_id = 0): array
    {
        if ($id_utama === $id_duplikat) {
            return ['success' => false, 'message' => 'Customer utama dan duplikat tidak boleh sama.'];
        }

        $utama = $this->db->table('pelanggan')->where('id_pelanggan', $id_utama)->where('deleted_at IS NULL')->get()->getRowArray();
        $duplikat = $this->db->table('pelanggan')->where('id_pelanggan', $id_duplikat)->where('deleted_at IS NULL')->get()->getRowArray();

        if (!$utama || !$duplikat) {
            return ['success' => false, 'message' => 'Salah satu data customer tidak ditemukan atau sudah dihapus.'];
        }

        $time = time();

        $this->db->transStart();

        // 1. Pindahkan seluruh invoice pemesan_id & kirimKepada_id
        $this->db->table('invoice')->where('pemesan_id', $id_duplikat)->update(['pemesan_id' => $id_utama]);
        $this->db->table('invoice')->where('kirimKepada_id', $id_duplikat)->update(['kirimKepada_id' => $id_utama]);

        // 2. Pindahkan kontak yang belum ada di utama
        $kontakDup = $this->db->table('pelanggan_kontak')->where('pelanggan_id', $id_duplikat)->get()->getResultArray();
        foreach ($kontakDup as $kd) {
            $ada = $this->db->table('pelanggan_kontak')->where('pelanggan_id', $id_utama)->where('nomor_pure', $kd['nomor_pure'])->countAllResults();
            if ($ada === 0) {
                $this->db->table('pelanggan_kontak')->where('id_kontak', $kd['id_kontak'])->update(['pelanggan_id' => $id_utama]);
            }
        }

        // 3. Pindahkan live chat percakapan
        $this->db->table('crm_chat_percakapan')->where('pelanggan_id', $id_duplikat)->update(['pelanggan_id' => $id_utama]);

        // 4. Pindahkan aktivitas CRM
        $this->db->table('crm_aktivitas')->where('pelanggan_id', $id_duplikat)->update(['pelanggan_id' => $id_utama]);

        // 5. Pindahkan follow-up CRM
        $this->db->table('crm_followup')->where('pelanggan_id', $id_duplikat)->update(['pelanggan_id' => $id_utama]);

        // 6. Pindahkan tag-tag
        $tagDup = $this->db->table('crm_pelanggan_tag')->where('pelanggan_id', $id_duplikat)->get()->getResultArray();
        foreach ($tagDup as $td) {
            $this->tambahTagPelanggan($id_utama, (int) $td['tag_id']);
        }

        // 7. Soft delete akun duplikat
        $this->db->table('pelanggan')->where('id_pelanggan', $id_duplikat)->update([
            'deleted_at' => $time,
        ]);

        // 8. Catat aktivitas merge di customer utama
        $this->tambahAktivitas([
            'pelanggan_id' => $id_utama,
            'tipe'         => 'sistem',
            'judul'        => "Penggabungan Customer (Merge)",
            'deskripsi'    => "Customer #{$id_duplikat} ({$duplikat['nama_pelanggan']}) berhasil digabungkan ke profil ini.",
            'user_id'      => $user_id,
        ]);

        $this->db->transComplete();

        if ($this->db->transStatus() === false) {
            return ['success' => false, 'message' => 'Gagal menggabungkan customer karena kesalahan database.'];
        }

        return ['success' => true, 'message' => "Customer '{$duplikat['nama_pelanggan']}' berhasil digabungkan ke '{$utama['nama_pelanggan']}'!"];
    }

    /**
     * Mengambil daftar invoice belum lunas untuk Follow-up Tagihan
     */
    public function getInvoiceBelumLunas(string $cari = '', int $limit = 50, int $offset = 0): array
    {
        $pInv  = $this->db->prefixTable('invoice');
        $pPel  = $this->db->prefixTable('pelanggan');
        $pJur  = $this->db->prefixTable('juragan');
        $pDib  = $this->db->prefixTable('dibeli');
        $pBi   = $this->db->prefixTable('biaya');
        $pBay  = $this->db->prefixTable('pembayaran');
        $pKon  = $this->db->prefixTable('pelanggan_kontak');

        $whereCari = '';
        $params = [];
        if (!empty($cari)) {
            $whereCari = "AND (inv.seri LIKE ? OR pel.nama_pelanggan LIKE ? OR kon.nomor LIKE ?)";
            $params = ["%{$cari}%", "%{$cari}%", "%{$cari}%"];
        }

        $sql = "SELECT 
            inv.id_invoice,
            inv.seri,
            inv.tanggal_pesan,
            inv.status_pembayaran,
            inv.status_pesanan,
            jur.nama_juragan,
            pel.id_pelanggan,
            pel.nama_pelanggan,
            COALESCE(kon.nomor, '') as nomor_hp,
            COALESCE(b.total_barang, 0) + COALESCE(bc.total_biaya, 0) as total_tagihan,
            COALESCE(pm.total_bayar, 0) as telah_dibayar,
            (COALESCE(b.total_barang, 0) + COALESCE(bc.total_biaya, 0)) - COALESCE(pm.total_bayar, 0) as sisa_tagihan
        FROM `{$pInv}` inv
        JOIN `{$pPel}` pel ON pel.id_pelanggan = inv.pemesan_id
        JOIN `{$pJur}` jur ON jur.id_juragan = inv.juragan_id
        LEFT JOIN (
            SELECT pelanggan_id, MIN(nomor_pure) as nomor 
            FROM `{$pKon}` GROUP BY pelanggan_id
        ) kon ON kon.pelanggan_id = pel.id_pelanggan
        LEFT JOIN (
            SELECT invoice_id, SUM(qty * harga) as total_barang 
            FROM `{$pDib}` GROUP BY invoice_id
        ) b ON b.invoice_id = inv.id_invoice
        LEFT JOIN (
            SELECT invoice_id, SUM(nominal) as total_biaya 
            FROM `{$pBi}` GROUP BY invoice_id
        ) bc ON bc.invoice_id = inv.id_invoice
        LEFT JOIN (
            SELECT invoice_id, SUM(total_pembayaran) as total_bayar 
            FROM `{$pBay}` GROUP BY invoice_id
        ) pm ON pm.invoice_id = inv.id_invoice
        WHERE inv.deleted_at IS NULL 
          AND inv.status_pembayaran IN ('1','2','3','4')
          {$whereCari}
        ORDER BY inv.tanggal_pesan DESC, inv.id_invoice DESC
        LIMIT {$limit} OFFSET {$offset}";

        return $this->db->query($sql, $params)->getResultArray();
    }

    /**
     * Mengambil daftar invoice untuk notifikasi pengiriman resi
     */
    public function getInvoicePengiriman(string $cari = '', int $limit = 50, int $offset = 0): array
    {
        $pInv  = $this->db->prefixTable('invoice');
        $pPel  = $this->db->prefixTable('pelanggan');
        $pJur  = $this->db->prefixTable('juragan');
        $pKir  = $this->db->prefixTable('pengiriman');
        $pKon  = $this->db->prefixTable('pelanggan_kontak');

        $whereCari = '';
        $params = [];
        if (!empty($cari)) {
            $whereCari = "AND (inv.seri LIKE ? OR pel.nama_pelanggan LIKE ? OR kir.resi LIKE ? OR kon.nomor LIKE ?)";
            $params = ["%{$cari}%", "%{$cari}%", "%{$cari}%", "%{$cari}%"];
        }

        $sql = "SELECT 
            inv.id_invoice,
            inv.seri,
            inv.tanggal_pesan,
            inv.status_pengiriman,
            jur.nama_juragan,
            pel.id_pelanggan,
            pel.nama_pelanggan,
            COALESCE(kon.nomor, '') as nomor_hp,
            kir.kurir,
            kir.resi,
            kir.tanggal_kirim,
            kir.ongkir
        FROM `{$pInv}` inv
        JOIN `{$pPel}` pel ON pel.id_pelanggan = inv.kirimKepada_id
        JOIN `{$pJur}` jur ON jur.id_juragan = inv.juragan_id
        JOIN `{$pKir}` kir ON kir.invoice_id = inv.id_invoice
        LEFT JOIN (
            SELECT pelanggan_id, MIN(nomor_pure) as nomor 
            FROM `{$pKon}` GROUP BY pelanggan_id
        ) kon ON kon.pelanggan_id = pel.id_pelanggan
        WHERE inv.deleted_at IS NULL
          AND kir.resi IS NOT NULL AND TRIM(kir.resi) != ''
          {$whereCari}
        ORDER BY kir.tanggal_kirim DESC, inv.id_invoice DESC
        LIMIT {$limit} OFFSET {$offset}";

        return $this->db->query($sql, $params)->getResultArray();
    }

    /**
     * Mengambil data satu invoice lengkap untuk pengisian template pesan
     */
    public function getInvoiceDetailPesan(int $id_invoice): ?array
    {
        $pInv  = $this->db->prefixTable('invoice');
        $pPel  = $this->db->prefixTable('pelanggan');
        $pJur  = $this->db->prefixTable('juragan');
        $pDib  = $this->db->prefixTable('dibeli');
        $pBi   = $this->db->prefixTable('biaya');
        $pBay  = $this->db->prefixTable('pembayaran');
        $pKon  = $this->db->prefixTable('pelanggan_kontak');
        $pKir  = $this->db->prefixTable('pengiriman');

        $sql = "SELECT 
            inv.id_invoice,
            inv.seri,
            inv.tanggal_pesan,
            inv.status_pembayaran,
            inv.status_pengiriman,
            jur.nama_juragan,
            pel.id_pelanggan,
            pel.nama_pelanggan,
            COALESCE(kon.nomor, '') as nomor_hp,
            COALESCE(b.total_barang, 0) + COALESCE(bc.total_biaya, 0) as total_tagihan,
            COALESCE(pm.total_bayar, 0) as telah_dibayar,
            (COALESCE(b.total_barang, 0) + COALESCE(bc.total_biaya, 0)) - COALESCE(pm.total_bayar, 0) as sisa_tagihan,
            kir.kurir,
            kir.resi,
            kir.tanggal_kirim
        FROM `{$pInv}` inv
        JOIN `{$pPel}` pel ON pel.id_pelanggan = inv.pemesan_id
        JOIN `{$pJur}` jur ON jur.id_juragan = inv.juragan_id
        LEFT JOIN (
            SELECT pelanggan_id, MIN(nomor_pure) as nomor 
            FROM `{$pKon}` GROUP BY pelanggan_id
        ) kon ON kon.pelanggan_id = pel.id_pelanggan
        LEFT JOIN (
            SELECT invoice_id, SUM(qty * harga) as total_barang 
            FROM `{$pDib}` GROUP BY invoice_id
        ) b ON b.invoice_id = inv.id_invoice
        LEFT JOIN (
            SELECT invoice_id, SUM(nominal) as total_biaya 
            FROM `{$pBi}` GROUP BY invoice_id
        ) bc ON bc.invoice_id = inv.id_invoice
        LEFT JOIN (
            SELECT invoice_id, SUM(total_pembayaran) as total_bayar 
            FROM `{$pBay}` GROUP BY invoice_id
        ) pm ON pm.invoice_id = inv.id_invoice
        LEFT JOIN (
            SELECT invoice_id, kurir, resi, tanggal_kirim 
            FROM `{$pKir}` WHERE resi IS NOT NULL ORDER BY id_pengiriman DESC LIMIT 1
        ) kir ON kir.invoice_id = inv.id_invoice
        WHERE inv.id_invoice = ?
        LIMIT 1";

        return $this->db->query($sql, [$id_invoice])->getRowArray();
    }

    /**
     * Mengambil riwayat pengiriman pesan WhatsApp
     */
    public function getLogPesan(int $limit = 50, int $offset = 0): array
    {
        $pLog = $this->db->prefixTable('crm_pesan_log');
        $pPel = $this->db->prefixTable('pelanggan');
        $pInv = $this->db->prefixTable('invoice');
        $pUsr = $this->db->prefixTable('user');

        $sql = "SELECT 
            log.*,
            pel.nama_pelanggan,
            inv.seri as invoice_seri,
            usr.name as nama_pengirim
        FROM `{$pLog}` log
        LEFT JOIN `{$pPel}` pel ON pel.id_pelanggan = log.pelanggan_id
        LEFT JOIN `{$pInv}` inv ON inv.id_invoice = log.invoice_id
        LEFT JOIN `{$pUsr}` usr ON usr.id = log.user_id
        ORDER BY log.id_log DESC
        LIMIT {$limit} OFFSET {$offset}";

        return $this->db->query($sql)->getResultArray();
    }
}
