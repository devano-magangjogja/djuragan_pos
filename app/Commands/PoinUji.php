<?php

namespace App\Commands;

use App\Models\PoinPenjualanModel;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Skenario uji aturan poin gaji (PoinPenjualanModel::sinkron).
 *
 * Dijalankan terhadap database yang sedang aktif: nota dan baris orderan uji
 * ditiru dari id yang sudah ada, lalu seluruh tulisannya dibatalkan lagi, jadi
 * tidak ada satu baris pun yang tertinggal setelah uji.
 *
 * Pakai: php spark poin:uji
 */
class PoinUji extends BaseCommand
{
    protected $group       = 'Uji';
    protected $name        = 'poin:uji';
    protected $description = 'Menjalankan skenario uji poin gaji; semua tulisan ke database dibatalkan lagi.';

    private $db;
    private $model;
    private int $produk = 0;
    private int $stok = 0;
    private int $pelanggan = 0;
    private int $pemakai = 0;
    private int $gagal = 0;

    public function run(array $params)
    {
        $this->db    = \Config\Database::connect();
        $this->model = new PoinPenjualanModel();

        $this->db->transBegin();

        $master = $this->db->query('SELECT id_produk, kode FROM `' . $this->p('produk') . '` ORDER BY id_produk DESC LIMIT 1')->getRow();
        $stok   = $this->db->query('SELECT id_stok, juragan_id FROM `' . $this->p('stok') . '` WHERE deleted_at IS NULL ORDER BY id_stok DESC LIMIT 1')->getRow();
        $sumber = $this->db->query('SELECT pemesan_id, user_id FROM `' . $this->p('invoice') . '` WHERE deleted_at IS NULL ORDER BY id_invoice DESC LIMIT 1')->getRow();

        $this->produk    = (int) $master->id_produk;
        $this->stok      = (int) $stok->id_stok;
        $this->pelanggan = (int) $sumber->pemesan_id;
        $this->pemakai   = (int) $sumber->user_id;
        $juragan         = (int) $stok->juragan_id;

        $this->poinProduk(5);

        CLI::write('produk uji: ' . $this->produk . ' (' . $master->kode . ') berpoin 5 — stok uji: ' . $this->stok . ' toko ' . $juragan);

        // A: barang dari stok yang dibayar di konter — tidak pernah punya kurir/resi
        $a = $this->nota($juragan, '6', '1', null);
        $this->item($a, true, 2);
        $this->model->sinkron($a);
        $this->klim('A  barang stok + lunas tanpa resi langsung berpoin', 1, $this->jumlah($a));
        $this->klim('A  barisnya menautkan dibeli_id dan stok_id', 1, $this->tautan($a));

        // B: barang tulisan tangan baru berpoin setelah notasinya Selesai
        $b = $this->nota($juragan, '6', '1', null);
        $this->item($b, false, 1);
        $this->model->sinkron($b);
        $this->klim('B  barang custom belum Selesai belum berpoin', 0, $this->jumlah($b));

        $this->ubah($b, ['status_pengiriman' => '3']);
        $this->model->sinkron($b);
        $this->klim('B  setelah Selesai barisnya menyusul', 1, $this->jumlah($b));

        // C: pembayaran dilepas lagi -> poin dicabut
        $this->ubah($a, ['status_pembayaran' => '1']);
        $this->model->sinkron($a);
        $this->klim('C  nota balik belum lunas dicabut', 0, $this->jumlah($a));

        // D: nilai yang sudah dipotret tidak dihitung ulang saat poin produk berubah
        $d = $this->nota($juragan, '6', '1', null);
        $this->item($d, true, 3);
        $this->model->sinkron($d);
        $potret = $this->total($d);

        $this->poinProduk(0);
        $this->model->sinkron($d);
        $this->klim('D  poin produk dinolkan, barisnya tetap ada', 1, $this->jumlah($d));
        $this->klim('D  nilainya tetap potret waktu diberi', $potret, $this->total($d));
        $this->poinProduk(5);

        // E: dipanggil berkali-kali tidak menghasilkan pemberian ganda
        $this->model->sinkron($d);
        $this->model->sinkron($d);
        $this->klim('E  sinkron berulang tetap satu baris', 1, $this->jumlah($d));

        // F: baris orderannya dihapus lewat sunting -> poinnya ikut hilang
        $this->db->query('DELETE FROM `' . $this->p('dibeli') . '` WHERE invoice_id = ?', [$d]);
        $this->model->sinkron($d);
        $this->klim('F  baris orderan hilang ikut mencabut poin', 0, $this->jumlah($d));

        // G: nota sewa menunggu tombol Sudah kembali
        $g = $this->nota($juragan, '6', '1', '{"tipe":"sewa"}');
        $this->item($g, true, 1);
        $this->model->sinkron($g);
        $this->klim('G  sewa belum dikonfirmasi kembali belum berpoin', 0, $this->jumlah($g));

        $this->ubah($g, ['sewa_kembali_at' => time()]);
        $this->model->sinkron($g);
        $this->klim('G  setelah dikonfirmasi kembali berpoin', 1, $this->jumlah($g));

        // H: baris ledger warisan (dibeli_id kosong) dibiarkan, tidak diduplikasi
        $h = $this->nota($juragan, '6', '1', null);
        $this->item($h, true, 1);
        $this->db->query('INSERT INTO `' . $this->p('poin_penjualan') . '` (invoice_id, dibeli_id, user_id, juragan_id, produk_id, kode,'
            . ' ukuran, qty, poin_satuan, poin_total, created_at) VALUES (?, NULL, ?, ?, ?, ?, \'lama\', 1, 5, 5, ?)',
            [$h, $this->pemakai, $juragan, $this->produk, $master->kode, time()]);
        $this->model->sinkron($h);
        $this->klim('H  ledger warisan tidak ditambah ganda', 1, $this->jumlah($h));
        $this->klim('H  barisnya masih tanpa dibeli_id', 1, $this->warisan($h));

        $this->db->transRollback();

        CLI::write('');
        CLI::write('baris ledger tertinggal di basis data: ' . $this->jumlahSemua() . ' (harus 0)');
        CLI::write($this->gagal === 0 ? 'SEMUA UJIAN LULUS' : $this->gagal . ' UJIAN GAGAL');

        return $this->gagal === 0 ? 0 : 1;
    }

    private function klim(string $nama, int $harap, int $nyata): void
    {
        $ok = $harap === $nyata;

        if (! $ok) {
            $this->gagal++;
            CLI::write(' GAGAL ' . $nama . ' (harap ' . $harap . ', nyata ' . $nyata . ')');
            CLI::write('       ledger: ' . json_encode($this->jejak()));
        } else {
            CLI::write('  ok   ' . $nama);
        }
    }

    private function jejak(): array
    {
        return $this->db->query('SELECT invoice_id, dibeli_id, stok_id, qty, poin_total FROM `'
            . $this->p('poin_penjualan') . '` ORDER BY id_poin')->getResultArray();
    }

    private function poinProduk(int $nilai): void
    {
        $this->db->query('UPDATE `' . $this->p('produk') . '` SET poin = ? WHERE id_produk = ?', [$nilai, $this->produk]);
    }

    private function nota(int $juragan, string $bayar, string $kirim, ?string $rincian): int
    {
        $waktu = time();
        $lama  = (int) $this->db->query('SELECT COALESCE(MAX(id_invoice), 0) id FROM `' . $this->p('invoice') . '`')->getRow()->id;

        $this->db->query('INSERT INTO `' . $this->p('invoice') . '` (seri, tanggal_pesan, pemesan_id, kirimKepada_id, juragan_id, user_id,'
            . ' status_pembayaran, status_pengiriman, status_pesanan, rincian, created_at, update_at)'
            . " VALUES ('UJI{$waktu}', CURDATE(), ?, ?, ?, ?, ?, ?, '1', ?, ?, ?)",
            [$this->pelanggan, $this->pelanggan, $juragan, $this->pemakai, $bayar, $kirim, $rincian, $waktu, $waktu]);

        $baru = (int) $this->db->query('SELECT MAX(id_invoice) id FROM `' . $this->p('invoice') . '`')->getRow()->id;

        if ($baru === $lama) {
            throw new \RuntimeException('nota uji gagal dibuat: ' . json_encode($this->db->error()));
        }

        return $baru;
    }

    private function item(int $invoice, bool $pakai_stok, int $qty): void
    {
        $this->db->query('INSERT INTO `' . $this->p('dibeli') . '` (invoice_id, produk_id, stok_id, kode, ukuran, qty, harga, rincian)'
            . ' VALUES (?, ?, ?, ?, ?, ?, 1000, NULL)', [
                $invoice,
                $this->produk,
                $pakai_stok ? $this->stok : null,
                'UJI',
                $pakai_stok ? 'M' : 'custom',
                $qty,
            ]);
    }

    private function ubah(int $invoice, array $kolom): void
    {
        $set   = [];
        $bind  = [];

        foreach ($kolom as $nama => $nilai) {
            $set[] = '`' . $nama . '` = ?';
            $bind[] = $nilai;
        }

        $bind[] = $invoice;

        $this->db->query('UPDATE `' . $this->p('invoice') . '` SET ' . implode(', ', $set) . ' WHERE id_invoice = ?', $bind);
    }

    private function jumlah(int $invoice): int
    {
        return (int) $this->db->query('SELECT COUNT(*) n FROM `' . $this->p('poin_penjualan') . '` WHERE invoice_id = ?', [$invoice])->getRow()->n;
    }

    private function total(int $invoice): int
    {
        return (int) $this->db->query('SELECT COALESCE(SUM(poin_total), 0) n FROM `' . $this->p('poin_penjualan') . '` WHERE invoice_id = ?', [$invoice])->getRow()->n;
    }

    private function tautan(int $invoice): int
    {
        return (int) $this->db->query('SELECT COUNT(*) n FROM `' . $this->p('poin_penjualan')
            . '` WHERE invoice_id = ? AND dibeli_id IS NOT NULL AND stok_id IS NOT NULL', [$invoice])->getRow()->n;
    }

    private function warisan(int $invoice): int
    {
        return (int) $this->db->query('SELECT COUNT(*) n FROM `' . $this->p('poin_penjualan')
            . '` WHERE invoice_id = ? AND dibeli_id IS NULL', [$invoice])->getRow()->n;
    }

    private function jumlahSemua(): int
    {
        return (int) $this->db->query('SELECT COUNT(*) n FROM `' . $this->p('poin_penjualan') . '`')->getRow()->n;
    }

    private function p(string $nama): string
    {
        return $this->db->prefixTable($nama);
    }
}
