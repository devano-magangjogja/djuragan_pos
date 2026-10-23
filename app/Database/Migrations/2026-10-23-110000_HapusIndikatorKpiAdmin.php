<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Buang indikator KPI khusus role Admin.
 *
 * KPI dipakai untuk mengukur produktivitas Customer Service. Empat indikator
 * yang terdaftar dengan peran 'admin' (SLA Fulfillment Admin, Return Error Rate,
 * Kecepatan Input Master Data, Error/Void Rate) dulu ikut dinilai padahal
 * pelaksananya bukan CS; tiga di antaranya bahkan belum punya jalur pencatatan
 * sama sekali sehingga selalu tampil kosong. Kosong yang tidak pernah terisi itu
 * membuat halaman Ringkasan sulit dibaca, jadi indikator-indikator itu ditarik
 * dari daftar target dan halaman KPI.
 *
 * Yang dihapus hanya baris definisi targetnya. Catatan kerja yang jadi bahan
 * hitungan (invoice, invoice_status, pembayaran) tidak disentuh, jadi kalau
 * nanti indikator itu dihidupkan lagi sejarahnya masih utuh.
 */
class HapusIndikatorKpiAdmin extends Migration
{
    /** @var list<string> kunci indikator admin yang ditarik dari penilaian */
    private array $dibuang = [
        'sla_fulfillment',
        'return_error_rate',
        'kecepatan_master_data',
        'error_void_rate',
    ];

    public function up()
    {
        // tabelnya pasti sudah ada: kpi_target dibuat migration PencatatanKpi
        // yang nomornya lebih dulu, dan nama tabel ditulis tanpa prefix karena
        // query builder yang menempelkannya
        $this->db->table('kpi_target')
            ->whereIn('indikator', $this->dibuang)
            ->where('peran', 'admin')
            ->delete();
    }

    public function down()
    {
        $waktu = time();

        foreach ($this->definisi() as $indikator) {
            // tidak menimpa baris yang sudah ada: rollback di atas instalasi baru
            // tidak boleh gagal karena kunci indikator yang unik
            $ada = $this->db->table('kpi_target')
                ->where('indikator', $indikator['indikator'])->countAllResults();

            if ($ada > 0) {
                continue;
            }

            $this->db->table('kpi_target')->insert($indikator + [
                'target'     => null,
                'bobot'      => 0,
                'periode'    => 'bulanan',
                'aktif'      => 1,
                'created_at' => $waktu,
                'updated_at' => $waktu,
                'updated_by' => null,
            ]);
        }
    }

    /** Salinan definisi lama, sama seperti yang ditanam migration PencatatanKpi. */
    private function definisi(): array
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
        ];
    }
}
