<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Daftar target KPI tinggal GMV.
 *
 * Halaman Target dan Bobot KPI dipakai untuk memasang angka yang jadi tolok ukur
 * penilaian. Tiga baris di dalamnya sudah tidak punya pasangan di halaman KPI:
 * first_response_time dan konversi_lead dihitung dari tabel Live Chat WA yang
 * fitur dan halamannya sudah dihapus, sedangkan resolution_time masih dihitung
 * tapi tidak lagi dipasang target (kartiknya menampilkan "tanpa target").
 *
 * Sekaligus melepas bobot dari konfigurasi: bobot dipakai untuk menimbang beberapa
 * indikator sekaligus, dan dengan satu indikator yang ditargetkan, penimbangan
 * tidak berarti apa-apa. Kolomnya sendiri dijatuhkan migration berikutnya
 * (LepasKolomBobotKpi), setelah tidak ada lagi yang membacanya.
 *
 * Yang dihapus hanya baris definisi target. Catatan kerja yang jadi bahan hitungan
 * (invoice, crm_followup) tidak tersentuh, jadi sejarah angkanya tetap utuh.
 */
class HapusTargetKpiSelainGmv extends Migration
{
    /** @var list<string> indikator yang ditarik dari daftar target */
    private array $dibuang = [
        'first_response_time',
        'konversi_lead',
        'resolution_time',
    ];

    public function up()
    {
        $this->db->table('kpi_target')
            ->whereIn('indikator', $this->dibuang)
            ->delete();
    }

    public function down()
    {
        foreach ($this->baris() as $indikator) {
            // rollback di atas instalasi baru tidak boleh gagal karena indikator
            // yang barisnya masih ada
            $ada = $this->db->table('kpi_target')
                ->where('indikator', $indikator['indikator'])->countAllResults();

            if ($ada > 0) {
                continue;
            }

            $this->db->table('kpi_target')->insert($indikator);
        }
    }

    /**
     * Salinan baris persis seperti yang ada di database saat migration ini ditulis,
     * termasuk angka target yang sempat dipasang manajemen.
     *
     * @return list<array<string, mixed>>
     */
    private function baris(): array
    {
        return [
            [
                'indikator'  => 'first_response_time',
                'nama'       => 'First Response Time CS',
                'peran'      => 'cs',
                'satuan'     => 'menit',
                'arah'       => 'lebih_rendah',
                'target'     => 10.00,
                'target_tim' => null,
                'bobot'      => 0,
                'periode'    => 'bulanan',
                'definisi'   => 'Rata-rata selisih pesan masuk pertama pelanggan dengan balasan pertama manusia dari CS.',
                'aturan'     => 'Pesan otomatis/sistem dan pesan keluar berstatus gagal bukan respons. Percakapan dihitung sekali per dimulainya percakapan.',
                'aktif'      => 1,
                'created_at' => 1791532848,
                'updated_at' => 1793189940,
                'updated_by' => 2,
            ],
            [
                'indikator'  => 'konversi_lead',
                'nama'       => 'Lead-to-Order Conversion Rate',
                'peran'      => 'cs',
                'satuan'     => '%',
                'arah'       => 'lebih_tinggi',
                'target'     => null,
                'target_tim' => null,
                'bobot'      => 0,
                'periode'    => 'bulanan',
                'definisi'   => 'Persentase lead unik yang menghasilkan nota valid dalam periode atribusi.',
                'aturan'     => 'Lead = percakapan baru yang belum pernah jadi pelanggan ber-order. Order tanpa percakapan tidak menambah penyebut.',
                'aktif'      => 1,
                'created_at' => 1791532848,
                'updated_at' => 1793189940,
                'updated_by' => 2,
            ],
            [
                'indikator'  => 'resolution_time',
                'nama'       => 'Resolution Time',
                'peran'      => 'cs',
                'satuan'     => 'jam',
                'arah'       => 'lebih_rendah',
                'target'     => 24.00,
                'target_tim' => null,
                'bobot'      => 0,
                'periode'    => 'bulanan',
                'definisi'   => 'Rata-rata durasi penyelesaian komplain/tindak lanjut pelanggan sejak dibuat.',
                'aturan'     => 'Tindak lanjut yang masih menunggu tidak dianggap selesai. reopened memakai pembuatan ulang status sebagai waktu penyelesaian berikutnya.',
                'aktif'      => 1,
                'created_at' => 1791532848,
                'updated_at' => 1793189940,
                'updated_by' => 2,
            ],
        ];
    }
}
