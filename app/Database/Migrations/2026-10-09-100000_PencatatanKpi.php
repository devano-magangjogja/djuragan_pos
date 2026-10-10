<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Pencatatan yang diperlukan modul KPI tapi belum pernah direkam.
 *
 * KPI kinerja itu mengukur ORANG, bukan transaksi. Dua hal yang belum tersimpan:
 *
 * 1. Siapa yang menggeser tahap produksi. order_invoice_status cuma mencatat
 *    kapan sebuah tahap masuk/selesai, tidak pernah menyimpan pelaksananya, jadi
 *    kecepatan fulfillment tidak bisa dikaitkan ke admin mana pun. Kolomnya
 *    sengaja NULL: order lama tetap jujur tanpa pelaku, tidak diisi tebakan.
 * 2. Siapa yang membatalkan/menghapus orderan dan alasannya. Soft delete hanya
 *    mengisi deleted_at, sehingga error/void rate tidak punya pembilang maupun
 *    penyebut yang bisa dipertanggungjawabkan.
 *
 * kpi_target adalah tempat manajemen memasang angka target dan bobot. Semua
 * target diisi NULL dan bobot 0: sistem tidak boleh mengarang target, dan
 * bobot final harus diputuskan manajemen lewat halaman konfigurasi.
 *
 * Perubahan murni additive — tidak ada kolom atau data lama yang diubah/dihapus.
 */
class PencatatanKpi extends Migration
{
    public function up()
    {
        // 1. pelaku perpindahan tahap produksi
        if (! $this->punyaKolom('invoice_status', 'user_id')) {
            $this->db->query('ALTER TABLE ' . $this->t('invoice_status')
                . ' ADD COLUMN user_id INT UNSIGNED NULL AFTER status');
            $this->db->query('ALTER TABLE ' . $this->t('invoice_status')
                . ' ADD INDEX idx_invoice_status_user (user_id, tanggal_masuk)');
            $this->fk('invoice_status', 'fk_invoice_status_user', 'user_id', 'SET NULL');
        }

        // 2. siapa yang menghapus orderan dan karena apa
        if (! $this->punyaKolom('invoice', 'deleted_by')) {
            $this->db->query('ALTER TABLE ' . $this->t('invoice')
                . ' ADD COLUMN deleted_by INT UNSIGNED NULL AFTER deleted_at');
            $this->db->query('ALTER TABLE ' . $this->t('invoice')
                . ' ADD COLUMN alasan_batal VARCHAR(40) NULL AFTER deleted_by');
            // void rate dibaca per orang pada rentang tanggal tertentu
            $this->db->query('ALTER TABLE ' . $this->t('invoice')
                . ' ADD INDEX idx_invoice_deleted_by (deleted_by, deleted_at)');
            $this->fk('invoice', 'fk_invoice_deleted_by', 'deleted_by', 'SET NULL');
        }

        // 3. target dan bobot per indikator
        if (! $this->punyaTabel('kpi_target')) {
            $this->forge->addField([
                'id_target' => [
                    'type'           => 'INT',
                    'constraint'     => 10,
                    'unsigned'       => true,
                    'auto_increment' => true,
                ],
                // kunci stabil yang dipakai controller/model, bukan nama tampilan
                'indikator' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 40,
                ],
                'nama' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 80,
                ],
                'peran' => [
                    'type'    => 'ENUM',
                    'constraint' => ['admin', 'cs', 'keduanya'],
                    'default' => 'keduanya',
                ],
                // jam, menit, %, rupiah, jumlah
                'satuan' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 16,
                ],
                // arah penilaian: target lebih tinggi baik atau lebih rendah baik
                'arah' => [
                    'type'    => 'ENUM',
                    'constraint' => ['lebih_tinggi', 'lebih_rendah'],
                    'default' => 'lebih_rendah',
                ],
                'target' => [
                    'type'       => 'DECIMAL',
                    'constraint' => '12,2',
                    'null'       => true,
                ],
                'bobot' => [
                    'type'       => 'DECIMAL',
                    'constraint' => '5,2',
                    'default'    => 0,
                ],
                'periode' => [
                    'type'       => 'ENUM',
                    'constraint' => ['harian', 'mingguan', 'bulanan', 'tahunan'],
                    'default'    => 'bulanan',
                ],
                // penjelasan rumus untuk pembaca laporan
                'definisi' => [
                    'type' => 'TEXT',
                    'null' => true,
                ],
                // syarat baris data yang ikut dihitung / dibuang
                'aturan' => [
                    'type' => 'TEXT',
                    'null' => true,
                ],
                'aktif' => [
                    'type'    => 'TINYINT',
                    'constraint' => 1,
                    'default' => 1,
                ],
                'created_at' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
                'updated_at' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
                'updated_by' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            ]);
            $this->forge->addPrimaryKey('id_target');
            $this->forge->addUniqueKey('indikator');
            $this->forge->addKey('peran');
            $this->forge->createTable('kpi_target', true);
        }

        $this->isiTarget();
    }

    public function down()
    {
        foreach ([
            ['invoice', 'fk_invoice_deleted_by'],
            ['invoice_status', 'fk_invoice_status_user'],
        ] as [$tabel, $nama]) {
            if ($this->punyaConstraint($tabel, $nama)) {
                $this->db->query('ALTER TABLE ' . $this->t($tabel) . ' DROP FOREIGN KEY ' . $nama);
            }
        }

        if ($this->punyaKolom('invoice', 'deleted_by')) {
            if ($this->punyaIndex('invoice', 'idx_invoice_deleted_by')) {
                $this->db->query('ALTER TABLE ' . $this->t('invoice') . ' DROP INDEX idx_invoice_deleted_by');
            }
            $this->db->query('ALTER TABLE ' . $this->t('invoice') . ' DROP COLUMN alasan_batal');
            $this->db->query('ALTER TABLE ' . $this->t('invoice') . ' DROP COLUMN deleted_by');
        }

        if ($this->punyaKolom('invoice_status', 'user_id')) {
            if ($this->punyaIndex('invoice_status', 'idx_invoice_status_user')) {
                $this->db->query('ALTER TABLE ' . $this->t('invoice_status') . ' DROP INDEX idx_invoice_status_user');
            }
            $this->db->query('ALTER TABLE ' . $this->t('invoice_status') . ' DROP COLUMN user_id');
        }

        $this->forge->dropTable('kpi_target', true);
    }

    /**
     * Definisi indikator KPI.
     *
     * target NULL + bobot 0 dengan sengaja: angka itu hak manajemen, bukan asumsi
     * programmer. Halaman konfigurasi KPI yang mengisinya.
     */
    private function daftarIndikator(): array
    {
        return [
            [
                'indikator' => 'sla_fulfillment',
                'nama'      => 'SLA Fulfillment Admin',
                'peran'     => 'admin',
                'satuan'    => 'jam',
                'arah'      => 'lebih_rendah',
                'definisi'  => 'Rata-rata durasi dari pembayaran pertama yang sah sampai nota masuk tahap pengepakan atau pengiriman.',
                'aturan'    => 'Hanya nota lunas yang punya waktu bayar dan waktu fulfillment. Durasi negatif (tanggal terisi manual di luar urutan) dibuang dan dihitung sebagai data tidak memenuhi syarat.',
            ],
            [
                'indikator' => 'return_error_rate',
                'nama'      => 'Return Error Rate',
                'peran'     => 'admin',
                'satuan'    => '%',
                'arah'      => 'lebih_rendah',
                'definisi'  => 'Persentase nota unik yang diretur karena kesalahan input (salah varian/ukuran/SKU) dari nota yang ditangani admin.',
                'aturan'    => 'Membutuhkan pencatatan retur beralasan. Indikator tampil kosong sampai pencatatan itu berjalan, tidak memakai proksi.',
            ],
            [
                'indikator' => 'kecepatan_master_data',
                'nama'      => 'Kecepatan Input Master Data',
                'peran'     => 'admin',
                'satuan'    => 'menit',
                'arah'      => 'lebih_rendah',
                'definisi'  => 'Rata-rata waktu dari aktivitas input dimulai sampai SKU baru tersimpan atau stok batch diperbarui.',
                'aturan'    => 'Waktu mulai aktivitas belum direkam untuk data lama, jadi hanya aktivitas setelah pencatatan berjalan yang dihitung.',
            ],
            [
                'indikator' => 'error_void_rate',
                'nama'      => 'Error/Void Rate',
                'peran'     => 'admin',
                'satuan'    => '%',
                'arah'      => 'lebih_rendah',
                'definisi'  => 'Persentase transaksi unik yang dibatalkan/diVOID karena kesalahan operasional dari transaksi yang diproses admin yang bersangkutan.',
                'aturan'    => 'Pembilang dari order yang deleted_at terisi dengan alasan batal bukan pembatalan sah. Satu nota hanya dihitung satu kali berapa pun jumlah event-nya.',
            ],
            [
                'indikator' => 'first_response_time',
                'nama'      => 'First Response Time CS',
                'peran'     => 'cs',
                'satuan'    => 'menit',
                'arah'      => 'lebih_rendah',
                'definisi'  => 'Rata-rata selisih pesan masuk pertama pelanggan dengan balasan pertama manusia dari CS.',
                'aturan'    => 'Pesan otomatis/sistem dan pesan keluar berstatus gagal bukan respons. Percakapan dihitung sekali per dimulainya percakapan.',
            ],
            [
                'indikator' => 'konversi_lead',
                'nama'      => 'Lead-to-Order Conversion Rate',
                'peran'     => 'cs',
                'satuan'    => '%',
                'arah'      => 'lebih_tinggi',
                'definisi'  => 'Persentase lead unik yang menghasilkan nota valid dalam periode atribusi.',
                'aturan'    => 'Lead = percakapan baru yang belum pernah jadi pelanggan ber-order. Order tanpa percakapan tidak menambah penyebut.',
            ],
            [
                'indikator' => 'resolution_time',
                'nama'      => 'Resolution Time',
                'peran'     => 'cs',
                'satuan'    => 'jam',
                'arah'      => 'lebih_rendah',
                'definisi'  => 'Rata-rata durasi penyelesaian komplain/tindak lanjut pelanggan sejak dibuat.',
                'aturan'    => 'Tindak lanjut yang masih menunggu tidak dianggap selesai. reopened memakai pembuatan ulang status sebagai waktu penyelesaian berikutnya.',
            ],
            [
                'indikator' => 'gmv_cs',
                'nama'      => 'Kontribusi GMV CS',
                'peran'     => 'cs',
                'satuan'    => 'rupiah',
                'arah'      => 'lebih_tinggi',
                'definisi'  => 'Total nilai nota valid yang diatribusikan ke CS pada periode laporan.',
                'aturan'    => 'Nota valid = status pembayaran lunas atau lunas lebih (5/6), tidak dibatalkan, dan tidak dihapus. Nilai ikut ongkir dan potongan biaya tambahan. Nota batal/refund keluar perhitungan.',
            ],
        ];
    }

    private function isiTarget(): void
    {
        $ada = $this->db->table('kpi_target')->countAllResults();

        if ($ada > 0) {
            return;
        }

        $baris = [];
        $waktu = time();

        foreach ($this->daftarIndikator() as $indikator) {
            $baris[] = $indikator + [
                'target'     => null,
                'bobot'      => 0,
                'periode'    => 'bulanan',
                'aktif'      => 1,
                'created_at' => $waktu,
                'updated_at' => $waktu,
                'updated_by' => null,
            ];
        }

        $this->db->table('kpi_target')->insertBatch($baris);
    }

    private function t(string $nama): string
    {
        return $this->db->prefixTable($nama);
    }

    private function fk(string $tabel, string $nama, string $kolom, string $saatHapus): void
    {
        if (! $this->punyaConstraint($tabel, $nama)) {
            $this->db->query('ALTER TABLE ' . $this->t($tabel) . " ADD CONSTRAINT {$nama}"
                . " FOREIGN KEY ({$kolom}) REFERENCES " . $this->t('user') . ' (id)'
                . " ON DELETE {$saatHapus} ON UPDATE RESTRICT");
        }
    }

    private function punyaKolom(string $tabel, string $kolom): bool
    {
        return $this->db->query('SELECT 1 FROM information_schema.columns
                WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ? LIMIT 1',
            [$this->t($tabel), $kolom])->getNumRows() > 0;
    }

    private function punyaTabel(string $tabel): bool
    {
        return $this->db->tableExists($this->t($tabel));
    }

    private function punyaIndex(string $tabel, string $index): bool
    {
        return $this->db->query('SELECT 1 FROM information_schema.statistics
                WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ? LIMIT 1',
            [$this->t($tabel), $index])->getNumRows() > 0;
    }

    private function punyaConstraint(string $tabel, string $nama): bool
    {
        return $this->db->query('SELECT 1 FROM information_schema.table_constraints
                WHERE table_schema = DATABASE() AND table_name = ? AND constraint_name = ? LIMIT 1',
            [$this->t($tabel), $nama])->getNumRows() > 0;
    }
}
