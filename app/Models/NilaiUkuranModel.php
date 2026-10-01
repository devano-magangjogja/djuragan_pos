<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Nilai ukuran tersimpan, per item orderan atau per anggota rombongan.
 *
 * Aturan main yang dijaga di sini:
 *  - komponen yang tidak dikenal di master dibuang, jadi form tidak bisa menulis
 *    angka ke baris karangan sendiri;
 *  - satu komponen cuma boleh punya satu nilai untuk satu sasaran (id_beli /
 *    anggota_id). Keunikannya tidak bisa ditegakkan indeks unik karena kolomnya
 *    boleh NULL, jadi dicek dulu seperti StokModel::kembar();
 *  - kolom ukuran yang dikosongkan saat menyunting memang dianggap dihapus,
 *    bukan dibiarkan menggantung.
 *
 * Order lama tidak pernah punya baris di tabel ini dan itu normal: semua
 * pembacanya menampilkan "-" kalau kosong.
 */
class NilaiUkuranModel extends Model
{
    protected $table         = 'nilai_ukuran';
    protected $primaryKey    = 'id_nilai';
    protected $returnType    = 'array';
    protected $allowedFields = ['invoice_id', 'id_beli', 'anggota_id', 'komponen_id', 'nilai', 'satuan', 'catatan'];
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $dateFormat    = 'int';

    /** Nilai di atas ini jelas bukan ukuran badan dalam cm. */
    private const MAKS = 999.99;

    /**
     * Simpan satu set ukuran untuk satu item (id_beli) atau satu anggota.
     *
     * @param array<int|string, mixed> $kiriman [id_komponen => teks dari form]
     * @param int[]                    $sah     id komponen yang dikenal master
     * @return array<string, string>  pesan error per komponen yang ditolak
     */
    public function sinkron(int $invoice_id, ?int $id_beli, ?int $anggota_id, array $kiriman, array $sah): array
    {
        $ditolak  = [];
        $dipakai  = [];

        foreach ($kiriman as $komponen_id => $mentah) {
            $komponen_id = (int) $komponen_id;

            if (! in_array($komponen_id, $sah, true)) {
                continue;
            }

            $nilai = $this->bersihkan((string) $mentah);

            if ($nilai === null) {
                continue;
            }

            if ($nilai === false) {
                $ditolak[$komponen_id] = 'Isi angka, misalnya 100 atau 99,5.';

                continue;
            }

            if ($nilai > self::MAKS) {
                $ditolak[$komponen_id] = 'Angka ukurannya kebesaran (maksimum ' . self::MAKS . ' cm).';

                continue;
            }

            $dipakai[$komponen_id] = $nilai;
        }

        $lama = $this->ambilSasaran($invoice_id, $id_beli, $anggota_id);

        foreach ($dipakai as $komponen_id => $nilai) {
            $satuan = $this->satuanKomponen($komponen_id);
            $ada    = $lama[$komponen_id] ?? null;

            if ($ada !== null) {
                if ((float) $ada['nilai'] !== $nilai) {
                    $this->update((int) $ada['id_nilai'], ['nilai' => $nilai, 'satuan' => $satuan]);
                }

                continue;
            }

            $this->insert([
                'invoice_id'  => $invoice_id,
                'id_beli'     => $id_beli,
                'anggota_id'  => $anggota_id,
                'komponen_id' => $komponen_id,
                'nilai'       => $nilai,
                'satuan'      => $satuan,
            ]);
        }

        // komponen yang dulunya terisi tapi sekarang dikosongkan
        $buang = array_diff(array_keys($lama), array_keys($dipakai));

        if ($buang !== []) {
            $tabel  = $this->db->prefixTable('nilai_ukuran');
            $klausul = $this->klausulSasaran($invoice_id, $id_beli, $anggota_id);

            $this->db->query('DELETE FROM `' . $tabel . '` WHERE ' . $klausul
                . ' AND komponen_id IN (' . implode(',', array_map('intval', $buang)) . ')');
        }

        return $ditolak;
    }

    /**
     * Semua nilai ukuran satu orderan, sudah lengkap dengan label komponennya.
     *
     * @return array<int, array{id_beli:?int, anggota_id:?int, nama:string, satuan:?string, nilai:?string, catatan:?string}>
     */
    public function untukInvoice(int $invoice_id): array
    {
        $nilai = $this->db->prefixTable('nilai_ukuran');
        $kompo = $this->db->prefixTable('ukuran_komponen');

        return $this->db->query('SELECT n.id_beli, n.anggota_id, n.komponen_id, n.nilai, n.satuan, n.catatan, k.nama, k.urutan'
            . ' FROM `' . $nilai . '` n JOIN `' . $kompo . '` k ON k.id_komponen = n.komponen_id'
            . ' WHERE n.invoice_id = ' . $invoice_id . ' ORDER BY k.urutan, k.nama')->getResultArray();
    }

    /**
     * Jenis produk yang paling mungkin untuk tiap sasaran (baris produk atau
     * anggota), ditebak dari komponen ukurannya yang tersimpan. Sasaran yang
     * belum diukur tidak punya jenis di sini; form hanya pakai tebakan ini
     * sebagai nilai awal dropdown.
     *
     * @param string $sasaran id_beli | anggota_id
     *
     * @return array<int, int> id sasaran => id_jenis
     */
    public function petaJenis(int $invoice_id, string $sasaran = 'id_beli'): array
    {
        $sasaran = $this->kolumnya($sasaran);
        $nilai   = $this->db->prefixTable('nilai_ukuran');
        $kompo   = $this->db->prefixTable('ukuran_komponen');

        $baris = $this->db->query('SELECT n.' . $sasaran . ' sasaran, k.jenis_id, COUNT(*) jml FROM `' . $nilai . '` n'
            . ' JOIN `' . $kompo . '` k ON k.id_komponen = n.komponen_id'
            . ' WHERE n.invoice_id = ' . $invoice_id . ' AND n.' . $sasaran . ' IS NOT NULL AND k.jenis_id IS NOT NULL'
            . ' GROUP BY sasaran, k.jenis_id ORDER BY sasaran, jml DESC')->getResultArray();

        $peta = [];

        foreach ($baris as $b) {
            $id = (int) $b['sasaran'];

            if (! isset($peta[$id])) {
                $peta[$id] = (int) $b['jenis_id'];
            }
        }

        return $peta;
    }

    /**
     * Ukuran siap tampil, dikelompokkan per baris orderan:
     * [id_beli => [[label, angka], ...]]. Diambil sekali query untuk banyak
     * invoice supaya daftar transaksi tidak menambah query per kartu.
     * Baris anggota rombongan (`id_beli` NULL) tidak ikut, itu milik form anggota.
     *
     * @param int[] $invoice_ids
     *
     * @return array<int, array<int, array{0:string, 1:string}>>
     */
    public function perBarang(array $invoice_ids): array
    {
        $id = array_values(array_filter(array_map('intval', $invoice_ids)));

        if ($id === []) {
            return [];
        }

        $nilai = $this->db->prefixTable('nilai_ukuran');
        $kompo = $this->db->prefixTable('ukuran_komponen');
        $hasil = [];

        foreach ($this->db->query('SELECT n.id_beli, n.nilai, n.satuan, k.nama FROM `' . $nilai . '` n'
            . ' JOIN `' . $kompo . '` k ON k.id_komponen = n.komponen_id'
            . ' WHERE n.invoice_id IN (' . implode(',', $id) . ') AND n.id_beli IS NOT NULL'
            . ' ORDER BY n.id_beli, k.urutan, k.nama')->getResultArray() as $b) {
            $hasil[(int) $b['id_beli']][] = [
                $b['nama'],
                $b['nilai'] === null ? 'belum diukur' : self::tampil((float) $b['nilai'], $b['satuan']),
            ];
        }

        return $hasil;
    }

    /**
     * Nilai mentah per sasaran untuk mengisi inputan form:
     * [id_sasaran => [id_komponen => "100.5"]]. Angka dipangkas dari nol di
     * belakang supaya kolom `input type=number` menampilkan 100, bukan 100.00.
     *
     * @param string $sasaran id_beli | anggota_id
     *
     * @return array<int, array<int, string>>
     */
    public function perItemForm(int $invoice_id, string $sasaran = 'id_beli'): array
    {
        $sasaran = $this->kolumnya($sasaran);
        $hasil   = [];

        foreach ($this->untukInvoice($invoice_id) as $b) {
            if ($b[$sasaran] === null || $b['nilai'] === null) {
                continue;
            }

            $hasil[(int) $b[$sasaran]][(int) $b['komponen_id']] = rtrim(rtrim((string) $b['nilai'], '0'), '.');
        }

        return $hasil;
    }

    /** Angka ukuran ditampil rapi: 100 cm bukan 100.00, 99,5 pakai koma. */
    public static function tampil(float $nilai, ?string $satuan = null): string
    {
        $angka = rtrim(rtrim(number_format($nilai, 2, '.', ''), '0'), '.');

        return str_replace('.', ',', $angka) . ($satuan ? ' ' . $satuan : '');
    }

    /**
     * Teks form jadi angka. NULL = dibiarkan kosong (bukan error), false =
     * bukan angka sama sekali.
     *
     * @return float|bool|null
     */
    private function bersihkan(string $mentah): float|bool|null
    {
        $mentah = trim($mentah);

        if ($mentah === '') {
            return null;
        }

        $angka = str_replace(',', '.', str_replace(' ', '', $mentah));

        if (! is_numeric($angka)) {
            return false;
        }

        $nilai = (float) $angka;

        return $nilai > 0 ? $nilai : false;
    }

    private function satuanKomponen(int $komponen_id): ?string
    {
        $kompo = $this->db->prefixTable('ukuran_komponen');
        $baris = $this->db->query('SELECT satuan FROM `' . $kompo . '` WHERE id_komponen = ' . $komponen_id . ' LIMIT 1')->getFirstRow();

        return $baris === null ? null : $baris->satuan;
    }

    /**
     * Baris yang sudah ada untuk satu sasaran. `id_beli` dan `anggota_id` bisa
     * NULL, jadi pemakaiannya lewat operator NULL-safe MySQL.
     *
     * @return array<int, array{id_nilai:int, komponen_id:int, nilai:?string}>
     */
    private function ambilSasaran(int $invoice_id, ?int $id_beli, ?int $anggota_id): array
    {
        $tabel = $this->db->prefixTable('nilai_ukuran');
        $baris = $this->db->query('SELECT id_nilai, komponen_id, nilai FROM `' . $tabel . '` WHERE '
            . $this->klausulSasaran($invoice_id, $id_beli, $anggota_id))->getResultArray();

        return array_column($baris, null, 'komponen_id');
    }

    /**
     * Nama kolom yang boleh dipakai sebagai sasaran; dipakai mentah di query
     * jadi hanya dua nama yang dikenal yang boleh lewat.
     */
    private function kolumnya(string $sasaran): string
    {
        if (! in_array($sasaran, ['id_beli', 'anggota_id'], true)) {
            throw new \InvalidArgumentException('Sasaran ukuran harus id_beli atau anggota_id.');
        }

        return $sasaran;
    }

    private function klausulSasaran(int $invoice_id, ?int $id_beli, ?int $anggota_id): string
    {
        return 'invoice_id = ' . $invoice_id
            . ' AND id_beli <=> ' . ($id_beli === null ? 'NULL' : (int) $id_beli)
            . ' AND anggota_id <=> ' . ($anggota_id === null ? 'NULL' : (int) $anggota_id);
    }
}
