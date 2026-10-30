<?php

namespace App\Commands;

use App\Filters\Auth;
use App\Models\KpiModel;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Database;

/**
 * Skenario uji modul KPI (TAHAP 9 pada brief).
 *
 * Dijalankan terhadap database yang sedang aktif supaya yang diuji adalah angka
 * sungguhan, bukan data karangan. Skenario yang perlu menulis data membungkus
 * tulisannya dalam transaksi dan membatalkannya lagi, jadi tidak ada satu baris
 * pun yang tertinggal setelah uji.
 *
 * Pakai: php spark kpi:uji
 */
class KpiUji extends BaseCommand
{
    protected $group       = 'Uji';
    protected $name        = 'kpi:uji';
    protected $description = 'Menjalankan skenario uji modul KPI; semua tulisan ke database dibatalkan lagi.';

    private const SUDAH = 0.15;

    private BaseConnection $db;
    private KpiModel $model;

    /** @var list<int> id juragan, supaya uji tidak bergantung pada sesi login */
    private array $ids = [];

    private int $lulus = 0;
    private int $gagal = 0;
    private int $lewati = 0;

    public function run(array $params)
    {
        helper(['fungsi', 'form', 'url']);

        $this->db    = Database::connect();
        $this->model = new KpiModel();
        $this->ids   = array_map('intval', array_column(
            $this->db->query('SELECT id_juragan FROM ' . $this->table('juragan'))->getResultArray(),
            'id_juragan'
        ));

        // pelaku uji diambil dari akun CS kalau ada: KPI hanya menilai level cs
        // (LEVEL_PERAN), jadi tiket atau nota yang dibuat akun admin tidak akan
        // pernah terbaca oleh model dan skenarionya gagal tanpa sebab nyata
        $akun = $this->db->query('SELECT id, username, level FROM ' . $this->table('user')
            . " WHERE status = 'active' AND level IN ('cs','admin','superadmin')"
            . " ORDER BY FIELD(level, 'cs','admin','superadmin'), id LIMIT 1")->getRowArray();

        if (! empty($akun)) {
            session()->set('id', (int) $akun['id']);
            session()->set('level', $akun['level']);
            session()->set('name', $akun['username']);
            // filter Auth hanya menganggap ada sesi bila kunci ini terisi
            session()->set('logged', true);
        }

        CLI::write('Uji KPI — ' . count($this->ids) . ' toko, database ' . $this->db->database, 'yellow');

        foreach ($this->daftar() as [$nomor, $nama, $method]) {
            $this->nilai($nomor, $nama, $this->{$method}());
        }

        CLI::newLine();
        CLI::write('lulus ' . $this->lulus . '  |  gagal ' . $this->gagal . '  |  dilewati ' . $this->lewati,
            $this->gagal > 0 ? 'red' : 'green');

        return $this->gagal > 0 ? 1 : 0;
    }

    /**
     * @return list<array{int, string, string}>
     *
     * Nomor 1-5 dulu menguji SLA fulfillment, return error rate, dan error/void
     * rate. Ketiganya sudah ditarik dari KPI karena mengukur kerja admin. Nomor
     * 6-10 menguji first response time dan atribusi lead; keduanya dihitung dari
     * tabel Live Chat WA yang halaman beserta webhook-nya sudah dihapus.
     * Penomorannya dibiarkan mengikuti nama method-nya supaya riwayat uji lamanya
     * masih terbaca.
     */
    private function daftar(): array
    {
        return [
            [11, 'tiket komplain belum selesai', 's11'],
            [12, 'tiket komplain diselesaikan', 's12'],
            [13, 'pesanan dibatalkan keluar dari GMV', 's13'],
            [14, 'filter tanggal dan pegawai diterapkan', 's14'],
            [15, 'akses KPI pegawai lain tanpa izin', 's15'],
            [16, 'database tanpa data pada periode terpilih', 's16'],
            [17, 'angka query cocok dengan hitungan manual', 's17'],
        ];
    }

    // ------------------------------------------------------------------
    // skenario
    // ------------------------------------------------------------------

    private function s11(): string
    {
        return $this->ujiTiket('menunggu', null, 'tiket status menunggu tidak dianggap selesai');
    }

    private function s12(): string
    {
        return $this->ujiTiket('selesai', 5, 'tiket selesai dihitung durasinya')
            . ' | catatan: enum status crm_followup belum mengenal reopened, jadi tiket yang dibuka'
            . ' lagi dihitung sebagai tindak lanjut baru';
    }

    private function s13(): string
    {
        $nota = $this->notaLunasTerbaru();

        if ($nota === null) {
            return 'LEWATI|tidak ada nota lunas untuk diuji pembatalan';
        }

        $s      = $this->sempit($nota);
        $awal   = (float) $this->model->kartu($s)['gmv_cs']['nilai'];
        $nilai  = $this->nilaiNota((int) $nota['id_invoice']);

        $this->db->transStart();
        $this->db->query('UPDATE ' . $this->table('invoice')
            . " SET status_pesanan = '3' WHERE id_invoice = ?", [$nota['id_invoice']]);

        $sesudah = (float) $this->model->kartu($s)['gmv_cs']['nilai'];
        $daftar  = $this->cari('gmv_cs', $nota);

        $this->db->transRollback();

        if ($daftar !== null) {
            return 'GAGAL|nota ' . $nota['id_invoice'] . ' yang dibatalkan masih muncul di daftar GMV';
        }

        return $this->sama($nilai, $awal - $sesudah)
            ? 'OK|nota ' . $nota['id_invoice'] . ' dibatalkan: GMV turun ' . $this->rp($awal - $sesudah)
            : 'GAGAL|GMV turun ' . $this->rp($awal - $sesudah) . ', nilai nota ' . $this->rp($nilai);
    }

    private function s14(): string
    {
        $tengah = date('Y-m-d', strtotime('-3 month'));
        $kiri   = (float) $this->model->kartu($this->s(['akhir' => $tengah]))['gmv_cs']['nilai'];
        $kanan  = (float) $this->model->kartu($this->s([
            'awal'  => date('Y-m-d', strtotime($tengah . ' +1 day')),
            'akhir' => date('Y-m-d'),
        ]))['gmv_cs']['nilai'];
        $utuh   = (float) $this->model->kartu($this->s())['gmv_cs']['nilai'];

        if (! $this->sama($utuh, $kiri + $kanan)) {
            return 'GAGAL|GMV dua rentang tanggal (' . $this->rp($kiri + $kanan)
                . ') tidak sama dengan rentang utuh (' . $this->rp($utuh) . ')';
        }

        $pil     = $this->model->pegawaiPilihan('semua');
        $id      = array_key_first($pil);

        if ($id === null) {
            return 'LEWATI|tidak ada pegawai aktif untuk menguji saringan orang';
        }

        $seorang = $this->s(['user_id' => (int) $id]);
        $gmvOrang = (float) $this->model->kartu($seorang)['gmv_cs']['nilai'];

        if ($gmvOrang > $utuh + self::SUDAH) {
            return 'GAGAL|GMV pegawai ' . $pil[$id] . ' (' . $this->rp($gmvOrang) . ') melebihi total ('
                . $this->rp($utuh) . ')';
        }

        $asing = array_filter($this->model->detail($seorang, 'gmv_cs', 500, 0),
            fn ($b) => $b['pembuat'] !== $this->nama((int) $id));

        return $asing === []
            ? 'OK|rentang tanggal aditif dan saringan pegawai ' . $pil[(int) $id] . ' hanya memuat notanya'
            : 'GAGAL|daftar pegawai ' . $pil[(int) $id] . ' memuat ' . count($asing) . ' nota orang lain';
    }

    private function s15(): string
    {
        $filter = new Auth();
        $awalan = (string) session()->get('level');

        foreach (['cs' => false, 'admin' => true, 'superadmin' => true] as $level => $boleh) {
            session()->set('level', $level);
            $hasil = $filter->before(service('request'), ['admin', 'superadmin']);

            if ($hasil instanceof ResponseInterface && $boleh) {
                return 'GAGAL|akun ' . $level . ' dialihkan dari admin/kpi padahal berhak masuk';
            }

            if (! $hasil instanceof ResponseInterface && ! $boleh) {
                return 'GAGAL|akun ' . $level . ' bisa membuka admin/kpi tanpa izin';
            }
        }

        // saringan pegawai juga berlaku di tingkat query, bukan hanya di menu
        $pil = $this->model->pegawaiPilihan('cs');
        $id  = array_key_first($pil);

        session()->set('level', $awalan === '' ? 'superadmin' : $awalan);

        if ($id === null) {
            return 'OK|rute admin/kpi menolak akun cs (admin dan superadmin tetap boleh)';
        }

        $asing = array_filter($this->model->detail($this->s(['user_id' => (int) $id, 'peran' => 'cs']), 'gmv_cs', 500, 0),
            fn ($b) => $b['pembuat'] !== $this->nama((int) $id));

        return $asing === []
            ? 'OK|rute admin/kpi menolak akun cs; query ikut dibatasi satu pegawai'
            : 'GAGAL|query satu pegawai masih memuat ' . count($asing) . ' nota orang lain';
    }

    private function s16(): string
    {
        $s = $this->s(['awal' => '2005-01-01', 'akhir' => '2005-12-31']);

        $kartu = $this->model->kartu($s);
        $rekap = $this->model->rekap($s, 10, 0);
        $tren  = $this->model->tren($s);

        foreach (KpiModel::INDIKATOR as $i) {
            $this->model->detail($s, $i, 10, 0);
            $this->model->detailJumlah($s, $i);
            $this->model->pembanding($s, $i);
        }

        $berangka = array_filter($kartu,
            static fn ($k) => $k['nilai'] !== null && ! in_array($k['satuan'], ['rupiah', 'nota'], true)
                && (float) $k['nilai'] !== 0.0);

        return $berangka === [] && $rekap['baris'] === [] && $tren === []
            ? 'OK|periode 2005: tidak ada indikator yang memberi angka palsu, rekap kosong, tren kosong'
            : 'GAGAL|periode tanpa data masih memberi angka pada: ' . implode(', ', array_keys($berangka));
    }

    private function s17(): string
    {
        $s     = $this->s();
        $kartu = $this->model->kartu($s);

        $in = $this->table('invoice');
        $un = $this->table('user');

        $manual = $this->db->query('SELECT COUNT(*) nota,'
            . " COALESCE(SUM(CASE WHEN o.status_pembayaran IN ('5','6')"
            . '   THEN COALESCE(b.barang, 0) + COALESCE(bc.biaya, 0) ELSE 0 END), 0) gmv'
            . ' FROM ' . $in . ' o'
            . ' JOIN ' . $un . " u ON u.id = o.user_id AND u.status = 'active' AND u.deleted_at IS NULL"
            // sama seperti LEVEL_PERAN['semua']: yang dinilai hanya kerja CS
            . " AND u.level IN ('cs')"
            . ' LEFT JOIN (SELECT invoice_id, SUM(qty * harga) barang FROM ' . $this->table('dibeli') . ' GROUP BY invoice_id) b'
            . ' ON b.invoice_id = o.id_invoice'
            . ' LEFT JOIN (SELECT invoice_id, SUM(nominal) biaya FROM ' . $this->table('biaya') . ' GROUP BY invoice_id) bc'
            . ' ON bc.invoice_id = o.id_invoice'
            . " WHERE o.deleted_at IS NULL AND o.status_pesanan <> '3'"
            . " AND o.tanggal_pesan BETWEEN '" . $s['awal'] . "' AND '" . $s['akhir'] . "'")->getRowArray();

        $selisihGmv = round((float) $manual['gmv'] - (float) $kartu['gmv_cs']['nilai'], 2);
        $selisihNota = (int) $manual['nota'] - (int) $kartu['order_diproses']['nilai'];

        if ($selisihGmv !== 0.0 || $selisihNota !== 0) {
            return 'GAGAL|GMV manual ' . $this->rp((float) $manual['gmv']) . ' vs model '
                . $this->rp((float) $kartu['gmv_cs']['nilai']) . ' (selisih ' . $this->rp($selisihGmv)
                . '), nota ' . $manual['nota'] . ' vs ' . $kartu['order_diproses']['nilai'];
        }

        return 'OK|GMV ' . $this->rp((float) $kartu['gmv_cs']['nilai']) . ' dan ' . $manual['nota']
            . ' nota cocok dengan query manual';
    }

    // ------------------------------------------------------------------
    // pembantu skenario
    // ------------------------------------------------------------------

    /** Sisip tiket uji, baca kartunya, lalu batalkan. */
    private function ujiTiket(string $status, ?int $jam, string $penjelasan): string
    {
        $pelanggan = $this->db->query('SELECT id_pelanggan FROM ' . $this->table('pelanggan')
            . ' ORDER BY id_pelanggan DESC LIMIT 1')->getRowArray();

        if ($pelanggan === null) {
            return 'LEWATI|tidak ada pelanggan untuk menampung tiket uji';
        }

        $s = $this->s(['awal' => date('Y-m-d'), 'akhir' => date('Y-m-d')]);

        $this->db->transStart();

        $masuk = time() - 3600;
        $selesai = $jam === null ? null : $masuk + $jam * 3600;

        $pegawai = (int) session()->get('id');

        $this->db->query('INSERT INTO ' . $this->table('crm_followup')
            . ' (pelanggan_id, tipe, kategori, judul, status, user_id, selesai_oleh, selesai_at, created_at, updated_at)'
            . ' VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', [
                (int) $pelanggan['id_pelanggan'], 'customer', 'uji_kpi', 'Tiket uji KPI', $status,
                $pegawai, $pegawai, $selesai, $masuk, $masuk,
            ]);

        $id    = (int) $this->db->insertID();
        $kartu = $this->model->kartu($s)['resolution_time'];

        $this->db->transRollback();

        if ($status === 'menunggu') {
            return $kartu['contoh'] === 0
                ? 'OK|' . $penjelasan . ' (tiket ' . $id . ' tidak ikut terhitung)'
                : 'GAGAL|' . $penjelasan . ' (tiket ' . $id . ' dihitung ' . $kartu['contoh'] . ' kali)';
        }

        return $kartu['nilai'] !== null && $this->sama((float) $jam, (float) $kartu['nilai'])
            ? 'OK|' . $penjelasan . ' (tiket ' . $id . ': ' . $kartu['nilai'] . ' jam)'
            : 'GAGAL|' . $penjelasan . ' (tiket ' . $id . ': terbaca ' . var_export($kartu['nilai'], true) . ')';
    }

    // ------------------------------------------------------------------
    // akses data
    // ------------------------------------------------------------------

    /** @return array<string, mixed> */
    /** Nama asli pegawai; label dropdown memakai "nama (level)" sehingga tidak cocok langsung. */
    private function nama(int $id): string
    {
        return (string) $this->db->query('SELECT name FROM ' . $this->table('user')
            . ' WHERE id = ' . $id)->getRowArray()['name'];
    }

    private function s(array $ubah = []): array
    {
        return array_merge([
            'awal'        => '2020-01-01',
            'akhir'       => date('Y-m-d'),
            'periode'     => 'bulanan',
            'peran'       => 'semua',
            'user_id'     => null,
            'juragan_id'  => 0,
            'juragan_ids' => $this->ids,
        ], $ubah);
    }

    /** Saringan satu hari dan satu toko, supaya daftar detail bisa dibaca habis. */
    private function sempit(array $nota): array
    {
        return $this->s([
            'awal'        => $nota['tanggal_pesan'],
            'akhir'       => $nota['tanggal_pesan'],
            'juragan_ids' => [(int) $nota['juragan_id']],
        ]);
    }

    /** @return list<array<string, mixed>> */
    private function slice(string $indikator, array $s, int $maks = 3000): array
    {
        $isi = [];

        for ($offset = 0; $offset < $maks; $offset += 500) {
            $page = $this->model->detail($s, $indikator, 500, $offset);
            $isi  = array_merge($isi, $page);

            if (count($page) < 500) {
                break;
            }
        }

        return $isi;
    }

    private function cari(string $indikator, array $nota): ?array
    {
        foreach ($this->slice($indikator, $this->sempit($nota)) as $b) {
            if ((int) $b['id_invoice'] === (int) $nota['id_invoice']) {
                return $b;
            }
        }

        return null;
    }

    private function table(string $nama): string
    {
        return $this->db->prefixTable($nama);
    }

    private function notaLunasTerbaru(): ?array
    {
        return $this->db->query("SELECT o.id_invoice, o.juragan_id, o.tanggal_pesan, o.user_id"
            . ' FROM ' . $this->table('invoice') . ' o'
            . " WHERE o.deleted_at IS NULL AND o.status_pembayaran IN ('5','6') AND o.status_pesanan <> '3'"
            . ' ORDER BY o.id_invoice DESC LIMIT 1 OFFSET 15')->getRowArray();
    }

    private function nilaiNota(int $id): float
    {
        $row = $this->db->query('SELECT COALESCE((SELECT SUM(qty * harga) FROM ' . $this->table('dibeli')
            . ' WHERE invoice_id = ?), 0) + COALESCE((SELECT SUM(nominal) FROM ' . $this->table('biaya')
            . ' WHERE invoice_id = ?), 0) nilai', [$id, $id])->getRowArray();

        return (float) ($row['nilai'] ?? 0);
    }

    // ------------------------------------------------------------------
    // pelaporan
    // ------------------------------------------------------------------

    private function nilai(int $nomor, string $nama, string $hasil): void
    {
        [$kode, $pesan] = array_pad(explode('|', $hasil, 2), 2, '');

        if ($kode === 'OK') {
            $this->lulus++;
            CLI::write(str_pad(' ' . $nomor . '. ' . $nama, 56) . '  LULUS  ' . $pesan, 'green');
        } elseif ($kode === 'LEWATI') {
            $this->lewati++;
            CLI::write(str_pad(' ' . $nomor . '. ' . $nama, 56) . '  LEWATI  ' . $pesan, 'yellow');
        } else {
            $this->gagal++;
            CLI::write(str_pad(' ' . $nomor . '. ' . $nama, 56) . '  GAGAL  ' . $pesan, 'red');
        }
    }

    private function sama(float $a, float $b): bool
    {
        return abs($a - $b) <= self::SUDAH;
    }

    private function rp(float $n): string
    {
        return 'Rp ' . number_format($n, 0, ',', '.');
    }
}
