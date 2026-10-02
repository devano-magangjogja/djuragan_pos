<?php

namespace App\Database\Migrations;

use App\Models\InvoiceModel;
use CodeIgniter\Database\Migration;

/**
 * Status pembayaran lama dihitung dengan SUM(DISTINCT ...) sehingga baris yang
 * nilainya sama (dua produk dengan harga x qty identik, dua transfer sebesar itu)
 * ikut terhitung sekali. Nilai invoice yang terpengaruh ikut dibetulkan di sini.
 *
 * Nilai lama disimpan di tabel log supaya down() bisa mengembalikan persis seperti semula.
 */
class BetulkanStatusPembayaran extends Migration
{
    private const LOG = 'status_pembayaran_log';

    public function up()
    {
        $this->buatTabelLog();

        $perbarui = [];

        foreach ($this->angka() as $baris) {
            $harus = InvoiceModel::statusPembayaran(
                (int) $baris['total'],
                (int) $baris['terbayar'],
                (int) $baris['belumcek']
            );

            if ($harus !== $baris['lama']) {
                $perbarui[] = [
                    'id_invoice'        => (int) $baris['id_invoice'],
                    'status_pembayaran' => $harus,
                    'lama'              => $baris['lama'],
                ];
            }
        }

        if ($perbarui === []) {
            echo "\n  tidak ada status pembayaran yang perlu dibetulkan\n";

            return;
        }

        $this->db->transBegin();

        foreach (array_chunk($perbarui, 200) as $chunk) {
            $log = [];
            $set = [];

            foreach ($chunk as $r) {
                $log[] = [
                    'invoice_id'  => $r['id_invoice'],
                    'status_lama' => $r['lama'],
                    'status_baru' => $r['status_pembayaran'],
                    'dibuat'      => time(),
                ];
                $set[] = [
                    'id_invoice'        => $r['id_invoice'],
                    'status_pembayaran' => $r['status_pembayaran'],
                ];
            }

            $this->db->table(self::LOG)->insertBatch($log);
            $this->db->table('invoice')->updateBatch($set, 'id_invoice');
        }

        if (! $this->db->transStatus()) {
            $this->db->transRollback();

            throw new \RuntimeException('Backfill status pembayaran gagal disimpan.');
        }

        $this->db->transCommit();

        printf("\n  %d invoice dibetulkan, nilai lama tersimpan di tabel %s\n", count($perbarui), $this->db->DBPrefix . self::LOG);
    }

    public function down()
    {
        if (! $this->db->tableExists(self::LOG)) {
            return;
        }

        $lama = $this->db->table(self::LOG)->get()->getResultArray();

        if ($lama !== []) {
            $this->db->transBegin();

            foreach (array_chunk($lama, 200) as $chunk) {
                $set = [];

                foreach ($chunk as $r) {
                    $set[] = [
                        'id_invoice'        => (int) $r['invoice_id'],
                        'status_pembayaran' => $r['status_lama'],
                    ];
                }

                $this->db->table('invoice')->updateBatch($set, 'id_invoice');
            }

            $this->db->transCommit();
        }

        $this->db->query('DROP TABLE IF EXISTS ' . $this->db->prefixTable(self::LOG));

        printf("\n  %d invoice dikembalikan ke status sebelumnya\n", count($lama));
    }

    private function buatTabelLog(): void
    {
        if ($this->db->tableExists(self::LOG)) {
            return;
        }

        $this->forge->addField([
            'invoice_id'  => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'status_lama' => ['type' => 'CHAR', 'constraint' => 1],
            'status_baru' => ['type' => 'CHAR', 'constraint' => 1],
            'dibuat'      => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
        ]);
        $this->forge->addPrimaryKey('invoice_id');
        $this->forge->createTable(self::LOG, true);
    }

    /**
     * Total, terbayar, dan yang belum dicek untuk semua invoice aktif, dalam satu query.
     */
    private function angka(): array
    {
        $produk = $this->db->table('dibeli')
            ->select('invoice_id, SUM(qty * harga) AS s', false)
            ->groupBy('invoice_id')
            ->getCompiledSelect();

        $biaya = $this->db->table('biaya')
            ->select('invoice_id, SUM(nominal) AS s', false)
            ->groupBy('invoice_id')
            ->getCompiledSelect();

        $bayar = $this->db->table('pembayaran')
            ->select("invoice_id, SUM(CASE WHEN status='3' THEN total_pembayaran ELSE 0 END) AS t, SUM(CASE WHEN status='1' THEN total_pembayaran ELSE 0 END) AS u", false)
            ->groupBy('invoice_id')
            ->getCompiledSelect();

        $isi = $this->db->query(
            'SELECT i.id_invoice, CAST(i.status_pembayaran AS CHAR) AS lama,'
            . ' IFNULL(p.s, 0) + IFNULL(b.s, 0) AS total,'
            . ' IFNULL(y.t, 0) AS terbayar, IFNULL(y.u, 0) AS belumcek'
            . ' FROM ' . $this->db->prefixTable('invoice') . ' i'
            . ' LEFT JOIN (' . $produk . ') p ON p.invoice_id = i.id_invoice'
            . ' LEFT JOIN (' . $biaya . ') b ON b.invoice_id = i.id_invoice'
            . ' LEFT JOIN (' . $bayar . ') y ON y.invoice_id = i.id_invoice'
            . ' WHERE i.deleted_at IS NULL'
        )->getResultArray();

        return $isi;
    }
}
