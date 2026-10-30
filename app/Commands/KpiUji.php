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
     * rate. Ketiga indikator itu sudah ditarik dari KPI karena mengukur kerja
     * admin, jadi skenarionya ikut dihapus; penomorannya dibiarkan mengikuti nama
     * method-nya supaya riwayat uji lamanya masih terbaca.
     */
    private function daftar(): array
    {
        return [
            [6, 'CS menerima pesan lalu membalas', 's6'],
            [7, 'pesan otomatis bukan respons manusia', 's7'],
            [8, 'webhook sama diterima berulang kali', 's8'],
            [9, 'pesan keluar gagal dikirim', 's9'],
            [10, 'lead menjadi order sesuai jendela atribusi', 's10'],
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

    private function s6(): string
    {
        $sampel = $this->model->detail($this->s(), 'first_response_time', 10, 0);

        if ($sampel === []) {
            return 'LEWATI|belum ada percakapan yang dibalas staf';
        }

        foreach ($sampel as $b) {
            $manual = round(((int) $b['waktu_balas'] - (int) $b['waktu_masuk']) / 60, 1);

            if (! $this->sama($manual, (float) $b['frt_menit'])) {
                return 'GAGAL|sesi ' . $b['percakapan_id'] . ': model ' . $b['frt_menit'] . ' vs waktu mentah ' . $manual;
            }

            $Balas = $this->db->query('SELECT COUNT(*) j FROM ' . $this->table('crm_chat_pesan')
                . " WHERE percakapan_id = ? AND arah = 'keluar' AND user_id IS NOT NULL"
                . " AND status <> 'gagal' AND created_at = ?",
                [$b['percakapan_id'], $b['waktu_balas']])->getRowArray();

            if ((int) $Balas['j'] === 0) {
                return 'GAGAL|sesi ' . $b['percakapan_id'] . ': waktu balas tidak terbukti sebagai balasan manusia';
            }

            $lebihCepat = $this->db->query('SELECT COUNT(*) j FROM ' . $this->table('crm_chat_pesan')
                . " WHERE percakapan_id = ? AND arah = 'keluar' AND user_id IS NOT NULL"
                . " AND status <> 'gagal' AND created_at > ? AND created_at < ?",
                [$b['percakapan_id'], $b['waktu_masuk'], $b['waktu_balas']])->getRowArray();

            if ((int) $lebihCepat['j'] > 0) {
                return 'GAGAL|sesi ' . $b['percakapan_id'] . ': masih ada balasan manusia sebelum yang dihitung';
            }
        }

        return 'OK|' . count($sampel) . ' sesi terjawab: menit cocok dengan waktu mentah, balasan yang dihitung'
            . ' memang balasan manusia pertama pada sesi itu';
    }

    private function s7(): string
    {
        return $this->ubahBalasan('user_id', null,
            'balasan tanpa pegawai (keluar otomatis) bukan respons manusia');
    }

    private function s8(): string
    {
        return 'LEWATI|idempotensi webhook diuji pada jalur terima-kirim pesan, bukan pada model KPI';
    }

    private function s9(): string
    {
        return $this->ubahBalasan('status', 'gagal',
            'balasan berstatus gagal tidak dihitung sebagai respons manusia');
    }

    private function s10(): string
    {
        $lead = $this->leadBelumOrder();

        if ($lead === null) {
            return 'LEWATI|tidak ada lead tanpa order untuk uji jendela atribusi';
        }

        if ((int) $this->terkonversi($lead) === 1) {
            return 'LEWATI|lead ' . $lead['pelanggan_id'] . ' sudah terkonversi, tidak bisa dipakai uji jendela';
        }

        $this->db->transStart();

        $this->sisipNota($lead, $lead['waktu_mulai'] + DAY);
        $dalam = $this->terkonversi($lead);

        $this->geserNota($lead, $lead['waktu_mulai'] + DAY, $lead['waktu_mulai'] + 30 * DAY);
        $luar = $this->terkonversi($lead);

        $this->db->transRollback();

        if ($dalam !== 1 || $luar !== 0) {
            return 'GAGAL|lead ' . $lead['pelanggan_id'] . ': order hari ke-1=' . $dalam
                . ', order hari ke-30=' . $luar;
        }

        return 'OK|lead ' . $lead['pelanggan_id'] . ': order dalam 14 hari terhitung, order hari ke-30 tidak';
    }

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
        $pcs    = $this->pcsNota((int) $nota['id_invoice']);

        $this->db->transStart();
        $this->db->query('UPDATE ' . $this->table('invoice')
            . " SET status_pesanan = '3' WHERE id_invoice = ?", [$nota['id_invoice']]);

        $sesudah = (float) $this->model->kartu($s)['gmv_cs']['nilai'];
        $daftar  = $this->cari('gmv_cs', $nota);

        $this->db->transRollback();

        if ($daftar !== null) {
            return 'GAGAL|nota ' . $nota['id_invoice'] . ' yang dibatalkan masih muncul di daftar GMV';
        }

        return $this->sama($pcs, $awal - $sesudah)
            ? 'OK|nota ' . $nota['id_invoice'] . ' dibatalkan: GMV turun ' . $this->pcs($awal - $sesudah)
            : 'GAGAL|GMV turun ' . $this->pcs($awal - $sesudah) . ', pcs katalog nota ' . $this->pcs($pcs);
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
            return 'GAGAL|GMV dua rentang tanggal (' . $this->pcs($kiri + $kanan)
                . ') tidak sama dengan rentang utuh (' . $this->pcs($utuh) . ')';
        }

        $pil     = $this->model->pegawaiPilihan('semua');
        $id      = array_key_first($pil);

        if ($id === null) {
            return 'LEWATI|tidak ada pegawai aktif untuk menguji saringan orang';
        }

        $seorang = $this->s(['user_id' => (int) $id]);
        $gmvOrang = (float) $this->model->kartu($seorang)['gmv_cs']['nilai'];

        if ($gmvOrang > $utuh + self::SUDAH) {
            return 'GAGAL|GMV pegawai ' . $pil[$id] . ' (' . $this->pcs($gmvOrang) . ') melebihi total ('
                . $this->pcs($utuh) . ')';
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

        // angka manual ditulis ulang dari kolom aslinya, tanpa memakai model:
        // pcs katalog = order_dibeli.qty baris yang produk_id-nya terisi, nota 5/6
        $manual = $this->db->query('SELECT COUNT(*) nota,'
            . " COALESCE(SUM(CASE WHEN o.status_pembayaran IN ('5','6')"
            . '   THEN COALESCE(bk.unit, 0) ELSE 0 END), 0) unit'
            . ' FROM ' . $in . ' o'
            . ' JOIN ' . $un . " u ON u.id = o.user_id AND u.status = 'active' AND u.deleted_at IS NULL"
            // sama seperti LEVEL_PERAN['semua']: yang dinilai hanya kerja CS
            . " AND u.level IN ('cs')"
            . ' LEFT JOIN (SELECT invoice_id, SUM(qty) unit FROM ' . $this->table('dibeli')
            . ' WHERE produk_id IS NOT NULL GROUP BY invoice_id) bk'
            . ' ON bk.invoice_id = o.id_invoice'
            . " WHERE o.deleted_at IS NULL AND o.status_pesanan <> '3'"
            . " AND o.tanggal_pesan BETWEEN '" . $s['awal'] . "' AND '" . $s['akhir'] . "'")->getRowArray();

        $selisihGmv  = round((float) $manual['unit'] - (float) $kartu['gmv_cs']['nilai'], 2);
        $selisihNota = (int) $manual['nota'] - (int) $kartu['order_diproses']['nilai'];

        if ($selisihGmv !== 0.0 || $selisihNota !== 0) {
            return 'GAGAL|GMV manual ' . $this->pcs((float) $manual['unit']) . ' vs model '
                . $this->pcs((float) $kartu['gmv_cs']['nilai']) . ' (selisih ' . $this->pcs($selisihGmv)
                . '), nota ' . $manual['nota'] . ' vs ' . $kartu['order_diproses']['nilai'];
        }

        return 'OK|GMV ' . $this->pcs((float) $kartu['gmv_cs']['nilai']) . ' dan ' . $manual['nota']
            . ' nota cocok dengan query manual';
    }

    // ------------------------------------------------------------------
    // pembantu skenario
    // ------------------------------------------------------------------

    /** Sampel FRT diambil lalu salah satu syarat balasannya dilonggarkan. */
    private function ubahBalasan(string $kolom, ?string $nilai, string $penjelasan): string
    {
        $s    = $this->s();
        $sesi = $this->model->detail($s, 'first_response_time', 1, 0);

        if ($sesi === []) {
            return 'LEWATI|belum ada percakapan yang dibalas staf';
        }

        $sesi      = $sesi[0];
        $awal      = (int) $this->model->kartu($s)['first_response_time']['contoh'];
        $waktuLama = (int) $sesi['waktu_balas'];

        $set   = $kolom . ($nilai === null ? ' = NULL' : " = '" . $nilai . "'");
        $where = 'percakapan_id = ' . (int) $sesi['percakapan_id']
            . " AND arah = 'keluar' AND created_at = " . $waktuLama;

        $ini = 'percakapan ' . $sesi['percakapan_id'] . ' pada ' . date('j M H:i', $waktuLama);

        $this->db->transStart();
        $this->db->query('UPDATE ' . $this->table('crm_chat_pesan') . ' SET ' . $set . ' WHERE ' . $where);

        $sesudah = (int) $this->model->kartu($s)['first_response_time']['contoh'];
        // sesi yang sama sesudah perubahan: tetap ada bila masih ada balasan manusia
        // berikutnya pada sesi itu, hilang bila balasan yang diubah satu-satunya
        $baru = $this->cariSesi((int) $sesi['percakapan_id'], $s);

        $this->db->transRollback();

        if ($baru === null) {
            return $sesudah === $awal - 1
                ? 'OK|' . $penjelasan . ' (' . $ini . ' tidak lagi dianggap terjawab, sampel '
                    . $awal . ' -> ' . $sesudah . ')'
                : 'GAGAL|' . $penjelasan . ' (' . $ini . ' tidak lagi terjawab tapi sampel tetap '
                    . $sesudah . ')';
        }

        if ((int) $baru['waktu_balas'] === $waktuLama) {
            return 'GAGAL|' . $penjelasan . ' (' . $ini . ' masih dihitung sebagai respons manusia, sampel '
                . $awal . ' -> ' . $sesudah . ')';
        }

        return 'OK|' . $penjelasan . ' (' . $ini . ' ditiadakan, respons bergeser ke balasan manusia '
            . 'berikutnya ' . date('j M H:i', (int) $baru['waktu_balas']) . ', sampel '
            . $awal . ' -> ' . $sesudah . ')';
    }

    /** Baris first_response_time satu percakapan, dibaca dari daftar detail. */
    private function cariSesi(int $percakapan, array $s): ?array
    {
        foreach ($this->slice('first_response_time', $s) as $baris) {
            if ((int) $baris['percakapan_id'] === $percakapan) {
                return $baris;
            }
        }

        return null;
    }

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

    /** Pcs produk katalog satu nota: baris item yang punya master produk. */
    private function pcsNota(int $id): float
    {
        $row = $this->db->query('SELECT COALESCE(SUM(qty), 0) unit FROM ' . $this->table('dibeli')
            . ' WHERE invoice_id = ? AND produk_id IS NOT NULL', [$id])->getRowArray();

        return (float) ($row['unit'] ?? 0);
    }

    /** Sesi chat yang balasan manusianya pertama, beserta baris balasannya. */
    private function leadBelumOrder(): ?array
    {
        $s = $this->s();

        return $this->db->query('SELECT cp.pelanggan_id, MIN(m.created_at) waktu_mulai'
            . ' FROM ' . $this->table('crm_chat_percakapan') . ' cp'
            . ' JOIN ' . $this->table('crm_chat_pesan') . " m ON m.percakapan_id = cp.id_percakapan AND m.arah = 'masuk'"
            . ' WHERE cp.pelanggan_id IS NOT NULL'
            . ' AND NOT EXISTS (SELECT 1 FROM ' . $this->table('invoice') . ' o0'
            . ' WHERE o0.pemesan_id = cp.pelanggan_id AND o0.deleted_at IS NULL AND o0.created_at < m.created_at)'
            . ' GROUP BY cp.id_percakapan, cp.pelanggan_id'
            . ' ORDER BY waktu_mulai DESC LIMIT 1 OFFSET 3')->getRowArray();
    }

    private function terkonversi(array $lead): int
    {
        $baris = array_values(array_filter(
            $this->slice('konversi_lead', $this->s([
                'awal'  => date('Y-m-d', $lead['waktu_mulai']),
                'akhir' => date('Y-m-d', $lead['waktu_mulai']),
            ])),
            static fn ($b) => (int) $b['pelanggan_id'] === (int) $lead['pelanggan_id']
                && (int) $b['waktu_mulai'] === (int) $lead['waktu_mulai']
        ));

        return $baris === [] ? 0 : (int) $baris[0]['terkonversi'];
    }

    /** Nota salinan untuk lead, dipakai menguji jendela atribusi. */
    private function sisipNota(array $lead, int $waktu): void
    {
        $sumber = $this->db->query("SELECT * FROM " . $this->table('invoice')
            . " WHERE deleted_at IS NULL AND status_pembayaran IN ('5','6') ORDER BY id_invoice DESC LIMIT 1")->getRowArray();

        unset($sumber['id_invoice'], $sumber['deleted_at'], $sumber['deleted_by'], $sumber['alasan_batal']);

        $sumber['pemesan_id']    = (int) $lead['pelanggan_id'];
        $sumber['kirimKepada_id'] = (int) $lead['pelanggan_id'];
        $sumber['created_at']    = $waktu;
        $sumber['update_at']     = $waktu;

        $this->db->query('INSERT INTO ' . $this->table('invoice') . ' (' . implode(', ', array_keys($sumber))
            . ') VALUES (' . rtrim(str_repeat('?, ', count($sumber)), ', ') . ')', array_values($sumber));
    }

    private function geserNota(array $lead, int $dari, int $ke): void
    {
        $this->db->query('UPDATE ' . $this->table('invoice') . ' SET created_at = ?'
            . ' WHERE pemesan_id = ? AND created_at = ?', [$ke, (int) $lead['pelanggan_id'], $dari]);
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

    /** GMV dibaca dalam pcs, jadi pesan ujinya ikut satuan yang dipakai halaman. */
    private function pcs(float $n): string
    {
        return number_format($n, 0, ',', '.') . ' pcs';
    }
}
