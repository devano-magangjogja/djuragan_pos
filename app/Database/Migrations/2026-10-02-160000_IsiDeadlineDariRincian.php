<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Deadline lama dipindah dari teks bebas di rincian ke kolom order_invoice.deadline.
 *
 * Kolom deadline dan jalur simpannya sudah ada sejak revisi sebelumnya, tapi
 * orderan yang ditulis sebelum itu tetap NULL walaupun JSON `rincian`-nya sudah
 * menyimpan deadline. Padahal isi kolom inilah yang dibaca kartu "Deadline
 * hari ini / terlewat" di dasbor, jadi tanpa backfill angka dasbor selalu nol.
 *
 * Hanya teks yang benar-benar tanggal yang dipindah (deadline_iso()); teks seperti
 * "12 september" tanpa tahun sengaja dibiarkan NULL daripada ditebak tahunnya.
 * `rincian` lama tidak disentuh, jadi tampilan detail order tidak berubah.
 *
 * Nilai lama (selalu NULL) dicatat di tabel log supaya down() mengembalikan
 * persis baris yang disentuh migrasi ini.
 */
class IsiDeadlineDariRincian extends Migration
{
    private const LOG = 'deadline_backfill_log';

    public function up()
    {
        helper('fungsi');

        if (! $this->db->fieldExists('deadline', $this->db->prefixTable('invoice'))) {
            echo "\n  kolom deadline belum ada, lewati backfill\n";

            return;
        }

        $isi = [];

        foreach ($this->rincianBerdeadline() as $baris) {
            $tanggal = deadline_iso($baris['rincian']);

            if ($tanggal === null || $tanggal === $baris['deadline']) {
                continue;
            }

            $isi[] = [
                'id_invoice' => (int) $baris['id_invoice'],
                'lama'       => $baris['deadline'],
                'deadline'   => $tanggal,
            ];
        }

        if ($isi === []) {
            echo "\n  tidak ada deadline lama yang bisa dipindah ke kolom deadline\n";

            return;
        }

        $this->buatTabelLog();

        $this->db->transBegin();

        foreach ($isi as $r) {
            $this->db->table(self::LOG)->insert([
                'invoice_id'     => $r['id_invoice'],
                'deadline_lama'  => $r['lama'],
                'deadline_baru'  => $r['deadline'],
                'dibuat'         => time(),
            ]);

            $this->db->table('invoice')
                ->where('id_invoice', $r['id_invoice'])
                ->update(['deadline' => $r['deadline']]);
        }

        if (! $this->db->transStatus()) {
            $this->db->transRollback();

            throw new \RuntimeException('Backfill deadline gagal disimpan.');
        }

        $this->db->transCommit();

        printf("\n  %d deadline dipindah dari rincian ke kolom deadline, nilai lama tersimpan di tabel %s\n", count($isi), $this->db->DBPrefix . self::LOG);
    }

    public function down()
    {
        if (! $this->db->tableExists(self::LOG)) {
            return;
        }

        $lama = $this->db->table(self::LOG)->get()->getResultArray();

        if ($lama !== []) {
            $this->db->transBegin();

            foreach ($lama as $r) {
                $this->db->table('invoice')
                    ->where('id_invoice', (int) $r['invoice_id'])
                    ->update(['deadline' => $r['deadline_lama']]);
            }

            $this->db->transCommit();
        }

        $this->db->query('DROP TABLE IF EXISTS ' . $this->db->prefixTable(self::LOG));

        printf("\n  %d deadline dikembalikan seperti semula\n", count($lama));
    }

    private function buatTabelLog(): void
    {
        if ($this->db->tableExists(self::LOG)) {
            return;
        }

        $this->forge->addField([
            'invoice_id'    => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'deadline_lama' => ['type' => 'DATE', 'null' => true],
            'deadline_baru' => ['type' => 'DATE', 'null' => true],
            'dibuat'        => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
        ]);
        $this->forge->addPrimaryKey('invoice_id');
        $this->forge->createTable(self::LOG, true);
    }

    /**
     * Orderan yang kolom deadline-nya masih kosong tapi JSON rincian-nya
     * menyimpan key deadline. Jumlahnya kecil (hanya orderan yang ditulis
     * lewat form baru), jadi dibaca satu per satu tanpa indeks tambahan.
     */
    private function rincianBerdeadline(): array
    {
        return $this->db->table('invoice')
            ->select('id_invoice, deadline, rincian')
            ->where('deleted_at', null)
            ->where('deadline IS NULL')
            ->like('rincian', '"deadline"', 'both')
            ->orderBy('id_invoice', 'ASC')
            ->get()
            ->getResultArray();
    }
}
