<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Master komponen ukuran (Lingkar Dada, Panjang Badan, ...) dan jenis produknya.
 *
 * Satu jenis produk punya daftar komponennya sendiri; komponen dengan jenis_id
 * NULL berlaku untuk semua produk. Form maupun kartu detail selalu mengambil
 * daftar dari sini supaya nama ukuran dan satuannya tidak diketik ulang
 * — pola yang sama dipakai order_ukuran untuk ukuran standar S/M/L.
 */
class UkuranKomponenModel extends Model
{
    protected $table         = 'ukuran_komponen';
    protected $primaryKey    = 'id_komponen';
    protected $returnType    = 'array';
    protected $allowedFields = ['jenis_id', 'nama', 'satuan', 'urutan', 'wajib', 'aktif'];
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $dateFormat    = 'int';

    /**
     * Jenis produk urut tampil, [id_jenis => nama]. Dipakai dropdown di form.
     */
    public function jenis(): array
    {
        $tabel = $this->db->prefixTable('jenis_produk');

        return array_column(
            $this->db->query('SELECT id_jenis, nama FROM `' . $tabel . '` ORDER BY urutan, nama')->getResultArray(),
            'nama',
            'id_jenis'
        );
    }

    /**
     * Komponen aktif untuk satu jenis, plus komponen umum (jenis_id NULL).
     *
     * @return array<int, array{id_komponen:int, nama:string, satuan:string, wajib:int}>
     */
    public function template(?int $jenis_id): array
    {
        $tabel  = $this->db->prefixTable('ukuran_komponen');
        $klausul = $jenis_id === null ? 'jenis_id IS NULL' : '(jenis_id IS NULL OR jenis_id = ' . (int) $jenis_id . ')';

        return $this->db->query('SELECT id_komponen, nama, satuan, wajib FROM `' . $tabel
            . '` WHERE aktif = 1 AND ' . $klausul . ' ORDER BY urutan, nama')->getResultArray();
    }

    /**
     * Semua template sekali ambil, untuk form: [id_jenis => komponen[], 0 => komponen umum].
     */
    public function semuaTemplate(): array
    {
        $tabel  = $this->db->prefixTable('ukuran_komponen');
        $peta   = [0 => $this->template(null)];
        $baris  = $this->db->query('SELECT jenis_id, id_komponen, nama, satuan, wajib FROM `' . $tabel
            . '` WHERE aktif = 1 AND jenis_id IS NOT NULL ORDER BY jenis_id, urutan, nama')->getResultArray();

        foreach ($baris as $b) {
            $peta[(int) $b['jenis_id']][] = [
                'id_komponen' => (int) $b['id_komponen'],
                'nama'        => $b['nama'],
                'satuan'      => $b['satuan'],
                'wajib'       => (int) $b['wajib'],
            ];
        }

        return $peta;
    }

    /**
     * Daftar komponen untuk satu baris form: komponen umum + template jenisnya,
     * ditambah komponen yang sudah terisi walau jenisnya sudah ganti atau sudah
     * dinonaktifkan. Hasil ukur lama dengan begitu tetap kelihatan dan bisa
     * disunting, bukan hilang diam-diam waktu orderannya disunting.
     *
     * @param int[] $id_komponen komponen yang sudah punya nilai
     *
     * @return array<int, array{id_komponen:int, nama:string, satuan:string}>
     */
    public function untukBaris(?int $jenis_id, array $id_komponen = []): array
    {
        $tabel  = $this->db->prefixTable('ukuran_komponen');
        $pilihan = ['jenis_id IS NULL'];

        if ($jenis_id !== null) {
            $pilihan[] = 'jenis_id = ' . (int) $jenis_id;
        }

        if ($id_komponen !== []) {
            $pilihan[] = 'id_komponen IN (' . implode(',', array_map('intval', $id_komponen)) . ')';
        }

        $baris = $this->db->query('SELECT id_komponen, nama, satuan FROM `' . $tabel . '` WHERE aktif = 1 AND ('
            . implode(' OR ', $pilihan) . ') ORDER BY urutan, nama')->getResultArray();

        return array_map(
            static function (array $b) {
                return [
                    'id_komponen' => (int) $b['id_komponen'],
                    'nama'        => $b['nama'],
                    'satuan'      => $b['satuan'],
                ];
            },
            $baris
        );
    }

    /**
     * Label untuk sekumpulan nilai ukuran yang tersimpan: [id_komponen => baris].
     * Lewat peta, bukan join, supaya hasil ukur lama tetap punya label walau
     * komponennya sudah dinonaktifkan (komponen_id tidak bisa menghapus nilai —
     * FK-nya RESTRICT).
     *
     * @param array $id_komponen
     * @return array<int, array{id_komponen:int, nama:string, satuan:string}>
     */
    public function label(array $id_komponen): array
    {
        $id_komponen = array_values(array_unique(array_map('intval', $id_komponen)));

        if ($id_komponen === []) {
            return [];
        }

        $tabel = $this->db->prefixTable('ukuran_komponen');
        $baris = $this->db->query('SELECT id_komponen, nama, satuan FROM `' . $tabel
            . '` WHERE id_komponen IN (' . implode(',', $id_komponen) . ')')->getResultArray();

        return array_column($baris, null, 'id_komponen');
    }

    /**
     * Semua id komponen aktif; dipakai untuk menyaring kiriman form supaya
     * angka tidak bisa ditulis ke baris yang tidak dikenal.
     *
     * @return array<int, int>
     */
    public function idSah(): array
    {
        $tabel = $this->db->prefixTable('ukuran_komponen');

        return array_map('intval', array_column(
            $this->db->query('SELECT id_komponen FROM `' . $tabel . '` WHERE aktif = 1')->getResultArray(),
            'id_komponen'
        ));
    }
}
