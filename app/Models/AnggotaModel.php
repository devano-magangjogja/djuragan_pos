<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Anggota rombongan satu orderan.
 *
 * `order_dibeli.qty` cuma bilang "berapa stel"; tabel ini yang tahu namanya.
 * Order satuan tidak pernah punya baris di sini dan itu normal.
 *
 * Aturan main yang dijaga di sini:
 *  - diff-based seperti `sinkron_dibeli()`: kunci numerik yang masih ada di-update
 *    (id_anggota bertahan, jadi ukuran yang sudah tercatat tidak putus), kunci
 *    buatan form jadi baris baru, baris yang hilang dari kiriman dihapus;
 *  - panjang teks dibatasi sesuai kolom supaya MySQL tidak memotong diam-diam;
 *  - baris yang benar-benar kosong diabaikan, bukan ditolak.
 *
 * Dipanggil di dalam transaksi; pemanggil yang memutuskan rollback.
 */
class AnggotaModel extends Model
{
    protected $table         = 'anggota';
    protected $primaryKey    = 'id_anggota';
    protected $returnType    = 'array';
    protected $allowedFields = ['invoice_id', 'nama', 'nomor', 'urutan', 'catatan'];
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $dateFormat    = 'int';

    /** Batas panjang per kolom, dipakai untuk validasi dan placeholder form. */
    private const MAKS = ['nama' => 60, 'nomor' => 30, 'catatan' => 255];

    /**
     * Daftar anggota satu orderan, urut tampil di form.
     *
     * @return array<int, array{id_anggota:int, nama:string, nomor:?string, catatan:?string}>
     */
    public function untukInvoice(int $invoice_id): array
    {
        return $this->where('invoice_id', $invoice_id)
            ->orderBy('urutan', 'ASC')
            ->orderBy('id_anggota', 'ASC')
            ->findAll();
    }

    /**
     * Anggota + ukuran siap tampil untuk banyak orderan sekaligus dalam SATU query,
     * dipakai kartu transaksi (admin dan CS). Order satuan tidak muncul di sini,
     * jadi pemanggil cukup menyembunyikan bloknya kalau hasilnya kosong.
     *
     * @param  array<int, int> $invoice_ids
     * @return array<int, array<int, array{nama:string, nomor:?string, catatan:?string, nilai: array<int, array{0:string, 1:string}>}>>
     */
    public function banyak(array $invoice_ids): array
    {
        $id = array_values(array_filter(array_map('intval', $invoice_ids)));

        if ($id === []) {
            return [];
        }

        $anggota = $this->db->prefixTable('anggota');
        $nilai   = $this->db->prefixTable('nilai_ukuran');
        $kompo   = $this->db->prefixTable('ukuran_komponen');

        $hasil  = [];
        $indeks = [];

        // LEFT JOIN: orang yang belum diukur tetap muncul dengan namanya
        foreach ($this->db->query('SELECT a.invoice_id, a.id_anggota, a.nama, a.nomor, a.catatan'
            . ', k.nama komponen, n.nilai, n.satuan FROM `' . $anggota . '` a'
            . ' LEFT JOIN `' . $nilai . '` n ON n.anggota_id = a.id_anggota'
            . ' LEFT JOIN `' . $kompo . '` k ON k.id_komponen = n.komponen_id'
            . ' WHERE a.invoice_id IN (' . implode(',', $id) . ')'
            . ' ORDER BY a.invoice_id, a.urutan, a.id_anggota, k.urutan, k.nama')->getResultArray() as $r) {
            $inv = (int) $r['invoice_id'];
            $ori = (int) $r['id_anggota'];

            if (! isset($indeks[$inv][$ori])) {
                $indeks[$inv][$ori] = count($hasil[$inv] ?? []);

                $hasil[$inv][] = [
                    'nama'    => $r['nama'],
                    'nomor'   => $r['nomor'],
                    'catatan' => $r['catatan'],
                    'nilai'   => [],
                ];
            }

            if ($r['komponen'] !== null) {
                $hasil[$inv][$indeks[$inv][$ori]]['nilai'][] = [
                    $r['komponen'],
                    $r['nilai'] === null ? 'belum diukur' : NilaiUkuranModel::tampil((float) $r['nilai'], $r['satuan']),
                ];
            }
        }

        return $hasil;
    }

    /**
     * Samakan isi order_anggota satu invoice dengan kiriman form.
     *
     * @param array<string|int, array> $baris key form => [nama, nomor, catatan]
     * @return array{peta: array<string|int, int>, galat: array<string, string>}
     */
    public function sinkron(int $invoice_id, array $baris): array
    {
        $punya = array_map('intval', array_column(
            $this->where('invoice_id', $invoice_id)->select('id_anggota')->get()->getResultArray(),
            'id_anggota'
        ));

        $peta  = [];
        $galat = [];
        $sisa  = $punya;
        $urut  = 0;

        foreach ($baris as $key => $isi) {
            if (! is_array($isi)) {
                continue;
            }

            $siap = [];

            foreach (['nama', 'nomor', 'catatan'] as $kolom) {
                $teks = trim((string) ($isi[$kolom] ?? ''));

                if (mb_strlen($teks) > self::MAKS[$kolom]) {
                    $galat['anggota[' . $key . '][' . $kolom . ']']
                        = self::label($kolom) . ': maksimal ' . self::MAKS[$kolom] . ' karakter.';
                }

                $siap[$kolom] = $teks === '' ? null : $teks;
            }

            if ($siap['nama'] === null) {
                // baris yang benar-benar kosong bukan anggota, tinggal buang;
                // baris yang cuma punya nomor/catatan ditolak supaya tidak ada
                // orang tanpa nama di daftar
                if ($siap['nomor'] !== null || $siap['catatan'] !== null) {
                    $galat['anggota[' . $key . '][nama]'] = 'Nama anggota belum diisi.';
                }

                continue;
            }

            $lama = is_numeric($key) && in_array((int) $key, $sisa, true) ? (int) $key : null;

            if ($lama === null) {
                $id = (int) $this->insert($siap + ['invoice_id' => $invoice_id, 'urutan' => $urut]);
            } else {
                $this->update($lama, $siap + ['urutan' => $urut]);
                unset($sisa[array_search($lama, $sisa, true)]);
                $id = $lama;
            }

            $peta[$key] = $id;
            $urut++;
        }

        foreach ($sisa as $lama) {
            // ukuran orangnya ikut hilang lewat fk_nilai_anggota CASCADE
            $this->delete($lama);
        }

        return ['peta' => $peta, 'galat' => $galat];
    }

    /** Nama kolom yang dibaca user, bukan nama kolom database. */
    private static function label(string $kolom): string
    {
        return ['nama' => 'Nama', 'nomor' => 'Nomor/tinggi badan', 'catatan' => 'Catatan'][$kolom] ?? $kolom;
    }
}
