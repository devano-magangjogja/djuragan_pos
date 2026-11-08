<?php

namespace App\Commands;

use App\Models\InvoiceModel;
use App\Models\PoinPenjualanModel;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Hitung ulang poin gaji untuk nota yang sudah layak tapi ledgernya belum lengkap.
 *
 * Poin hanya dihitung pada saat status pembayaran atau pengiriman sebuah nota
 * berubah. Karena itu ada dua lubang yang tidak bisa ditutup sendiri:
 *   - poin produk diisi setelah nota-nota sebelumnya selesai — notanya sudah
 *     lunas sejak lama dan tidak pernah berubah status lagi;
 *   - nota penjualan barang stok yang tidak pernah punya resi — aturannya baru
 *     dibenarkan supaya barang berstok dihitung sejak lunas.
 *
 * Perintah ini memanggil sinkron() untuk kandidat-kandidat itu. Aman diulang:
 * sinkron() hanya menambah baris yang belum ada dan tidak pernah menyentuh
 * nilai yang sudah dipotret sebelumnya.
 */
class PoinBackfill extends BaseCommand
{
    protected $group       = 'poin';
    protected $name        = 'poin:backfill';
    protected $description = 'Hitung ulang poin gaji nota yang sudah layak tetapi ledgernya belum terisi.';
    protected $usage       = 'poin:backfill [--cek]';
    protected $options     = [
        '--cek' => 'Hanya laporkan jumlah kandidat, tidak menulis apa pun.',
    ];

    public function run(array $params)
    {
        $db     = \Config\Database::connect();
        $model  = new PoinPenjualanModel();
        // spark tidak menaruh opsi bernilai kosong di $params, jadi argv dibaca langsung
        $hanya  = in_array('--cek', $_SERVER['argv'] ?? [], true);
        $batas  = 500;
        $proses = 0;
        $tambah = 0;

        // nota yang tetap jadi kandidat walau sudah disinkronkan (mis. ledger lama
        // tanpa dibeli_id) jangan dikerjakan dua kali
        $dikerjakan = [];

        CLI::write($hanya ? 'mode cek: tidak ada yang ditulis' : 'mode tulis: ledger akan dilengkapi');

        while (true) {
            $kandidat = $db->query('SELECT d.invoice_id, COUNT(*) AS perlu'
                . ' FROM `' . $db->prefixTable('dibeli') . '` d'
                . ' JOIN `' . $db->prefixTable('produk') . '` p ON p.id_produk = d.produk_id AND p.poin > 0'
                . ' JOIN `' . $db->prefixTable('invoice') . '` i ON i.id_invoice = d.invoice_id'
                . ' WHERE d.qty > 0 AND i.deleted_at IS NULL AND ' . InvoiceModel::klausulLunas('i')
                . ' GROUP BY d.invoice_id'
                . ' HAVING perlu > (SELECT COUNT(*) FROM `' . $db->prefixTable('poin_penjualan')
                . '` pp WHERE pp.invoice_id = d.invoice_id)'
                . ' LIMIT ' . ($batas * 4))->getResultArray();

            $berikut = [];

            foreach ($kandidat as $k) {
                $id = (int) $k['invoice_id'];

                if (isset($dikerjakan[$id])) {
                    continue;
                }

                $berikut[] = $id;

                if (count($berikut) >= $batas) {
                    break;
                }
            }

            if ($berikut === []) {
                break;
            }

            foreach ($berikut as $id) {
                $dikerjakan[$id] = true;
                $proses++;

                if ($hanya) {
                    continue;
                }

                $sebelum = $this->jumlahBaris($db, $id);
                $model->sinkron($id);
                $tambah += $this->jumlahBaris($db, $id) - $sebelum;
            }

            if ($hanya || count($berikut) < $batas) {
                break;
            }
        }

        CLI::write('selesai: ' . $proses . ' nota diperiksa' . ($hanya ? '' : ', ' . $tambah . ' baris poin ditambahkan'));
    }

    private function jumlahBaris(object $db, int $invoice_id): int
    {
        return (int) $db->table('poin_penjualan')->where('invoice_id', $invoice_id)->countAllResults();
    }
}
