<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Perhitungan KPI Admin dan Customer Service.
 *
 * Semua agregasi dikerjakan MySQL; yang kembali ke controller hanya baris per
 * pegawai (puluhan baris) atau baris detail yang sudah dipaging, bukan transaksi
 * utuh, supaya halaman tetap ringan walau nota sudah puluhan ribu.
 *
 * Aturan yang dipakai bersama, dan alasannya:
 *
 * - Nota ikut dinilai bila tidak dihapus, tidak dibatalkan, dan termasuk toko
 *   yang boleh dibuka akun yang login. Periode dibaca dari tanggal_pesan, bukan
 *   waktu terakhir diubah, supaya nota tidak pindah periode hanya karena disunting.
 * - Nilai transaksi = barang (order_dibeli) + biaya tambahan (order_biaya: ongkir
 *   positif, diskon/revisi negatif). Sama dengan rumus view kpi_* dan keputusan
 *   GMV "lunas + ongkir & diskon ikut".
 * - Pengurangan dua kolom INT UNSIGNED SELALU dibungkus CAST(... AS SIGNED); tanpa
 *   itu MySQL error "value is out of range".
 * - Durasi negatif dibuang dari rata-rata dan dilaporkan sebagai jumlah yang tidak
 *   memenuhi syarat. Tanggal produksi di masa lalu kadang diisi tidak urut;
 *   memaksanya jadi nol atau membuang tanda minus menipu pembaca laporan.
 * - invoice_status.user_id dan invoice.deleted_by baru ada sejak 9 Oktober 2026.
 *   Metrik per orang dari kolom itu tampil sebagai "belum tercatat", bukan nol.
 */
class KpiModel extends Model
{
    /** Level akun yang dinilai untuk tiap pilihan peran. */
    private const LEVEL_PERAN = [
        'admin' => ['admin', 'superadmin'],
        'cs'    => ['cs'],
        'semua' => ['admin', 'superadmin', 'cs'],
    ];

    /** Indikator yang punya jalur angka dan bisa dibuka detailnya. */
    public const INDIKATOR = [
        'gmv_cs',
        'order_diproses',
        'sla_fulfillment',
        'error_void_rate',
        'first_response_time',
        'konversi_lead',
        'resolution_time',
    ];

    /** Penanda baris tanpa pelaku: history memang tidak pernah menyimpannya. */
    private const TANPA_PELAKU = 'belum tercatat';

    /** Jam tidak aktif sebelum satu percakapan dihitung sebagai sesi baru. */
    public const SESI_CHAT_JAM = 24;

    /** Hari batas lead dianggap berubah menjadi order. */
    public const ATRIBUSI_HARI = 14;

    protected $table         = 'invoice';
    protected $primaryKey    = 'id_invoice';
    protected $returnType    = 'array';
    protected $useSoftDeletes = false;
    protected $useTimestamps = false;
    protected $allowedFields = [];

    /** Hafalan catatan aktivitas per pegawai, kunci = kombinasi saringan. */
    private array $aktivitas = [];

    // ------------------------------------------------------------------
    // kartu ringkasan
    // ------------------------------------------------------------------

    /**
     * Isi sepuluh kartu di halaman KPI.
     *
     * Tiap kartu membawa contoh (banyaknya sampel di balik angka) dan, kalau
     * angkanya belum bisa dihitung, alasan singkat yang bisa dibaca orang.
     * Skor nol untuk "belum ada data" sengaja tidak pernah diberikan.
     *
     * @param array $s saringan dari Kpi::saring()
     */
    public function kartu(array $s): array
    {
        $nota   = $this->notePerPegawai($s);
        $sla    = $this->slaRingkas($s);
        $void   = $this->voidPerPegawai($s);
        $frt    = $this->frtRingkas($s);
        $lead   = $this->konversiPerPegawai($s);
        $resolusi = $this->resolusiPerPegawai($s);

        $order   = (int) array_sum(array_column($nota, 'jumlah_order'));
        $gmv     = (float) array_sum(array_map(static fn ($n) => $n['gmv'], $nota));
        $diproses = (int) array_sum(array_column($nota, 'diproses'));
        $salah   = (int) array_sum(array_column($void, 'salah'));
        $tanpa   = (int) array_sum(array_column($void, 'tanpa_alasan'));
        $tercatat = (int) array_sum(array_column($void, 'total'));
        $lead_i  = (int) array_sum(array_column($lead, 'lead'));
        $lead_k  = (int) array_sum(array_column($lead, 'terkonversi'));
        $res_c   = (int) array_sum(array_column($resolusi, 'contoh'));

        return [
            'pegawai_dinilai' => [
                'label'         => 'Karyawan dinilai',
                'nilai'         => count($nota),
                'satuan'        => 'orang',
                'contoh'        => $order,
                'contoh_label'  => 'nota',
            ],
            'order_diproses' => [
                'label'         => 'Pesanan diproses',
                'nilai'         => $order,
                'satuan'        => 'nota',
                'contoh'        => count($nota),
                'contoh_label'  => 'karyawan',
            ],
            'sla_fulfillment' => [
                'label'         => 'SLA fulfillment Admin',
                'nilai'         => $this->bulat($sla['rata']),
                'median'        => $this->bulat($sla['median']),
                'satuan'        => 'jam',
                'contoh'        => $sla['contoh'],
                'contoh_label'  => 'nota',
                'buang'         => $sla['buang'],
                'tersedia'      => $sla['contoh'] > 0,
                'alasan'        => $sla['contoh'] > 0 ? null
                    : 'Belum ada nota lunas yang tercatat waktu bayar dan waktu siap kirimnya.',
            ],
            'return_error_rate' => [
                'label'    => 'Return error rate',
                'nilai'    => null,
                'satuan'   => '%',
                'contoh'   => 0,
                'tersedia' => false,
                'alasan'   => 'Belum ada catatan retur, jadi angkanya belum bisa dihitung.',
            ],
            'kecepatan_master_data' => [
                'label'    => 'Kecepatan input master data',
                'nilai'    => null,
                'satuan'   => 'menit',
                'contoh'   => 0,
                'tersedia' => false,
                'alasan'   => 'Belum ada catatan waktu input barang, jadi angkanya belum bisa dihitung.',
            ],
            'error_void_rate' => [
                'label'         => 'Error/void rate',
                'nilai'         => $tercatat > 0 && $diproses > 0 ? round($salah / $diproses * 100, 1) : null,
                'satuan'        => '%',
                'contoh'        => $diproses,
                'contoh_label'  => 'nota',
                'buang'         => $tanpa,
                'tersedia'      => $tercatat > 0,
                'alasan'        => $tercatat > 0 ? null
                    : 'Belum ada nota dibatalkan yang tercatat siapa pembuatnya.',
            ],
            'first_response_time' => [
                'label'         => 'First response time CS',
                'nilai'         => $this->bulat($frt['rata']),
                'median'        => $this->bulat($frt['median']),
                'satuan'        => 'menit',
                'contoh'        => $frt['contoh'],
                'contoh_label'  => 'percakapan',
                'buang'         => $frt['belum'],
                'tersedia'      => $frt['contoh'] > 0,
                'alasan'        => $frt['contoh'] > 0 ? null
                    : 'Belum ada pesan masuk yang dibalas staf pada periode ini.',
            ],
            'konversi_lead' => [
                'label'    => 'Konversi lead ke order',
                'nilai'    => $lead_i > 0 ? round($lead_k / $lead_i * 100, 1) : null,
                'satuan'   => '%',
                'contoh'   => $lead_i,
                // lead yang belum jadi order bukan sampel buangan, jadi ditulis sebagai
                // rincian: membilangnya 0 dari 2 berarti angkanya memang 0%, bukan kosong
                'rincian'  => $lead_i . ' chat baru, ' . $lead_k . ' jadi order',
                'tersedia' => $lead_i > 0,
                'alasan'   => $lead_i > 0 ? null
                    : 'Belum ada chat baru dari pelanggan yang belum pernah order.',
            ],
            'resolution_time' => [
                'label'         => 'Waktu penyelesaian tindak lanjut',
                'nilai'         => $resolusi === [] ? null : round(array_sum(array_map(
                    static fn ($r) => (float) $r['rata'] * (int) $r['contoh'], $resolusi)) / max(1, $res_c), 1),
                'satuan'        => 'jam',
                'contoh'        => $res_c,
                'contoh_label'  => 'tindak lanjut',
                'tersedia'      => $res_c > 0,
                'alasan'        => $res_c > 0 ? null
                    : 'Belum ada komplain atau tindak lanjut yang selesai pada periode ini.',
            ],
            'gmv_cs' => [
                'label'         => 'GMV yang diatribusikan ke CS',
                'nilai'         => $gmv,
                'satuan'        => 'rupiah',
                'contoh'        => $order,
                'contoh_label'  => 'nota',
            ],
        ];
    }

    // ------------------------------------------------------------------
    // rekap per pegawai
    // ------------------------------------------------------------------

    /**
     * Rekap KPI per pegawai, sudah diurut dan dipaging.
     *
     * Pegawainya puluhan, jadi tiga agregasi kecil digabung di PHP lalu dipotong
     * per halaman. Transaksinya tetap tidak pernah diambil semua.
     *
     * @return array{baris: list<array>, jumlah: int}
     */
    public function rekap(array $s, int $limit, int $offset): array
    {
        $nota  = $this->notePerPegawai($s);
        $sla   = $this->slaPerPegawai($s);
        $void  = $this->voidPerPegawai($s);
        $frt   = $this->frtPerPegawai($s);
        $lead  = $this->konversiPerPegawai($s);
        $resol = $this->resolusiPerPegawai($s);

        foreach ($nota as &$pegawai) {
            $id = (int) $pegawai['user_id'];

            $pegawai['sla_jam']     = $this->bulat($sla[$id]['rata'] ?? null);
            $pegawai['sla_contoh']  = $sla[$id]['contoh'] ?? 0;
            $pegawai['void_salah']  = $void[$id]['salah'] ?? 0;
            $pegawai['void_persen'] = ($void[$id]['salah'] ?? 0) > 0 && (int) $pegawai['diproses'] > 0
                ? round($void[$id]['salah'] / (int) $pegawai['diproses'] * 100, 1)
                : null;
            $pegawai['frt_menit']  = $this->bulat($frt[$id]['rata'] ?? null);
            $pegawai['frt_contoh'] = $frt[$id]['contoh'] ?? 0;
            $pegawai['lead']       = $lead[$id]['lead'] ?? 0;
            $pegawai['lead_jadi']  = $lead[$id]['terkonversi'] ?? 0;
            $pegawai['resolusi']   = $this->bulat($resol[$id]['rata'] ?? null);
        }
        unset($pegawai);

        // pegawai yang tidak menulis nota tapi pernah membalas chat atau menutup
        // tindak lanjut tetap dinilai, jangan hilang dari daftar
        $sudah = array_map('intval', array_column($nota, 'user_id'));
        foreach (array_unique(array_merge(array_keys($frt), array_keys($resol))) as $id) {
            $id = (int) $id;

            if (in_array($id, $sudah, true)) {
                continue;
            }

            $nota[] = [
                'user_id' => $id, 'pegawai' => $this->namaPegawai($id), 'level' => 'cs',
                'jumlah_order' => 0, 'pelanggan' => 0, 'gmv' => 0, 'dibayar' => 0, 'lunas' => 0,
                'diproses' => 0, 'sla_jam' => null, 'sla_contoh' => 0, 'void_salah' => 0,
                'void_persen' => null, 'frt_menit' => $this->bulat($frt[$id]['rata'] ?? null),
                'frt_contoh' => $frt[$id]['contoh'] ?? 0,
                'lead' => $lead[$id]['lead'] ?? 0, 'lead_jadi' => $lead[$id]['terkonversi'] ?? 0,
                'resolusi' => $this->bulat($resol[$id]['rata'] ?? null),
            ];
        }

        usort($nota, static fn ($a, $b) => (float) $b['gmv'] <=> (float) $a['gmv']);

        return ['baris' => array_slice($nota, $offset, $limit), 'jumlah' => count($nota)];
    }

    /**
     * Deretan waktu untuk grafik tren.
     *
     * @return list<array{baki: string, order: int, gmv: float, lunas: int, sla_jam: float|null, frt_menit: float|null, void_salah: int}>
     */
    public function tren(array $s): array
    {
        // keranjang waktu ditulis ulang di GROUP BY, bukan nama aliasnya: MySQL 8
        // dengan only_full_group_by menolak ekspresi tanggal yang tidak muncul di
        // GROUP BY walau aliasnya sudah dipakai
        $bakiNota  = $this->baki('o.tanggal_pesan', $s['periode']);
        $bakiSla   = $this->baki('m.tanggal_pesan', $s['periode']);
        $bakiSesi  = $this->baki('FROM_UNIXTIME(f.waktu_masuk)', $s['periode']);
        $bakiVoid  = $this->baki('FROM_UNIXTIME(o.deleted_at)', $s['periode']);

        $nota = [];
        foreach ($this->db->query('SELECT ' . $bakiNota . ' baki, COUNT(*) orderan,'
            . $this->nilaiGmv() . ','
            . " SUM(o.status_pembayaran IN ('5','6')) lunas"
            . ' FROM ' . $this->t('invoice') . ' o'
            . $this->joinUser() . $this->joinNilai() . $this->whereNota($s)
            . ' GROUP BY ' . $bakiNota . ' ORDER BY baki', $this->params($s))->getResultArray() as $baris) {
            $nota[$baris['baki']] = $baris;
        }

        $sla = [];
        foreach ($this->db->query('SELECT ' . $bakiSla . ' baki,'
            . ' COUNT(*) orderan, AVG(m.durasi_jam) rata'
            . ' FROM (' . $this->slaDasar($s) . ') m'
            . ' WHERE ' . $this->kondisiSla()
            . ' GROUP BY ' . $bakiSla, $this->params($s))->getResultArray() as $baris) {
            $sla[$baris['baki']] = $baris;
        }

        $frt = [];
        foreach ($this->db->query('SELECT ' . $bakiSesi . ' baki, AVG(f.frt_menit) rata'
            . ' FROM (' . $this->frtDasar($s) . ') f'
            . ' WHERE f.waktu_balas IS NOT NULL'
            . ' GROUP BY ' . $bakiSesi, $this->paramsFrt($s))->getResultArray() as $baris) {
            $frt[$baris['baki']] = $baris;
        }

        $void = [];
        foreach ($this->db->query('SELECT ' . $bakiVoid . ' baki,'
            . ' COUNT(*) jumlah FROM ' . $this->t('invoice') . ' o'
            . ' WHERE o.deleted_at IS NOT NULL AND o.deleted_by IS NOT NULL'
            . ' AND o.deleted_at BETWEEN :awal_unix: AND :akhir_unix:'
            . ' AND o.juragan_id IN (' . $this->daftarId($s['juragan_ids']) . ')'
            . ' AND o.alasan_batal IN (' . $this->daftarKutip($this->slugKesalahan()) . ')'
            . $this->klausulPelaku($s, 'o.deleted_by')
            . ' GROUP BY ' . $bakiVoid, $this->paramsUnix($s))->getResultArray() as $baris) {
            $void[$baris['baki']] = $baris;
        }

        $deretan = [];
        foreach (array_unique(array_merge(array_keys($nota), array_keys($sla), array_keys($frt), array_keys($void))) as $baki) {
            $deretan[] = [
                'baki'       => $baki,
                'order'      => (int) ($nota[$baki]['orderan'] ?? 0),
                'gmv'        => round((float) ($nota[$baki]['gmv'] ?? 0)),
                'lunas'      => (int) ($nota[$baki]['lunas'] ?? 0),
                'sla_jam'    => isset($sla[$baki]['rata']) ? round((float) $sla[$baki]['rata'], 1) : null,
                'frt_menit'  => isset($frt[$baki]['rata']) ? round((float) $frt[$baki]['rata'], 1) : null,
                'void_salah' => (int) ($void[$baki]['jumlah'] ?? 0),
            ];
        }

        usort($deretan, static fn ($a, $b) => strcmp($a['baki'], $b['baki']));

        return $deretan;
    }

    /**
     * Perbandingan antarpegawai untuk satu indikator, untuk grafik batang.
     *
     * @return list<array{pegawai: string, nilai: float|null, contoh: int}>
     */
    public function pembanding(array $s, string $indikator): array
    {
        $peta = match ($indikator) {
            'sla_fulfillment' => fn () => $this->slaPerPegawai($s),
            'error_void_rate' => fn () => $this->voidPerPegawai($s),
            'first_response_time' => fn () => $this->frtPerPegawai($s),
            'konversi_lead' => fn () => $this->konversiPerPegawai($s),
            'resolution_time' => fn () => $this->resolusiPerPegawai($s),
            default => fn () => $this->notePerPegawai($s),
        };

        $kolom = match ($indikator) {
            'sla_fulfillment'     => 'rata',
            'first_response_time' => 'rata',
            'resolution_time'     => 'rata',
            'error_void_rate'     => 'salah',
            'konversi_lead'       => 'terkonversi',
            'order_diproses'      => 'jumlah_order',
            default               => 'gmv',
        };

        $contoh = match ($indikator) {
            'error_void_rate' => 'total',
            'konversi_lead'   => 'lead',
            'gmv_cs', 'order_diproses' => 'jumlah_order',
            default           => 'contoh',
        };

        $hasil = [];
        foreach ($peta() as $id => $baris) {
            $nama   = match (true) {
                isset($baris['pegawai']) => $baris['pegawai'],
                default                 => $this->namaPegawai((int) $id),
            };
            $nilai  = $baris[$kolom] ?? null;
            $hasil[] = [
                'pegawai' => $nama,
                'nilai'   => $nilai === null ? null : round((float) $nilai, 1),
                'contoh'  => (int) ($baris[$contoh] ?? 0),
            ];
        }

        usort($hasil, static fn ($a, $b) => (float) $b['nilai'] <=> (float) $a['nilai']);

        return $hasil;
    }

    // ------------------------------------------------------------------
    // detail satu indikator
    // ------------------------------------------------------------------

    /**
     * Baris pembentuk satu angka indikator, untuk audit.
     *
     * Isi pesan WhatsApp dan nama pelanggan tidak dikeluarkan di sini; yang
     * dibutuhkan hanya identitas singkat dan waktunya.
     *
     * @return list<array<string, mixed>>
     */
    public function detail(array $s, string $indikator, int $limit, int $offset): array
    {
        [$sql, $bind] = $this->sqlDetail($s, $indikator);

        return $this->db->query($sql . ' LIMIT ' . $limit . ' OFFSET ' . $offset, $bind)->getResultArray();
    }

    public function detailJumlah(array $s, string $indikator): int
    {
        [$sql, $bind] = $this->sqlDetail($s, $indikator);

        $ganti = 'SELECT COUNT(*) jumlah FROM (' . $sql . ') x';

        return (int) $this->db->query($ganti, $bind)->getRowArray()['jumlah'];
    }

    private function sqlDetail(array $s, string $indikator): array
    {
        switch ($indikator) {
            case 'sla_fulfillment':
                return ['SELECT m.id_invoice, m.juragan_id, m.tanggal_pesan, m.waktu_bayar, m.waktu_fu, m.durasi_jam,'
                    . ' COALESCE(u.name, ' . $this->kutip(self::TANPA_PELAKU) . ') AS pelaku'
                    . ' FROM (' . $this->slaDasar($s) . ') m'
                    . ' LEFT JOIN ' . $this->t('user') . ' u ON u.id = m.pelaksana'
                    . ' WHERE ' . $this->kondisiSla() . ' ORDER BY m.durasi_jam DESC',
                    $this->params($s)];

            case 'error_void_rate':
                return ['SELECT o.id_invoice, o.seri, o.tanggal_pesan, o.deleted_at, o.alasan_batal,'
                    . ' u.name AS pelaku, o.user_id AS pembuat'
                    . ' FROM ' . $this->t('invoice') . ' o'
                    . ' LEFT JOIN ' . $this->t('user') . ' u ON u.id = o.deleted_by'
                    . ' WHERE o.deleted_at IS NOT NULL AND o.deleted_by IS NOT NULL'
                    . ' AND o.deleted_at BETWEEN :awal_unix: AND :akhir_unix:'
                    . ' AND o.juragan_id IN (' . $this->daftarId($s['juragan_ids']) . ')'
                    . ' AND o.alasan_batal IN (' . $this->daftarKutip($this->slugKesalahan()) . ')'
                    . $this->klausulPelaku($s, 'o.deleted_by')
                    . ' ORDER BY o.deleted_at DESC',
                    $this->paramsUnix($s)];

            case 'first_response_time':
                return ['SELECT f.percakapan_id, f.nomor, f.waktu_masuk, f.waktu_balas, f.frt_menit,'
                    . ' COALESCE(u.name, ' . $this->kutip(self::TANPA_PELAKU) . ') AS pelaku'
                    . ' FROM (' . $this->frtDasar($s) . ') f'
                    . ' LEFT JOIN ' . $this->t('user') . ' u ON u.id = f.pelaku'
                    . ' WHERE f.waktu_balas IS NOT NULL'
                    . ' ORDER BY f.frt_menit DESC',
                    $this->paramsFrt($s)];

            case 'konversi_lead':
                return ['SELECT l.pelanggan_id, l.waktu_mulai, l.terkonversi,'
                    . ' COALESCE(u.name, ' . $this->kutip(self::TANPA_PELAKU) . ') AS pelaku'
                    . ' FROM (' . $this->konversiDasar($s) . ') l'
                    . ' LEFT JOIN ' . $this->t('user') . ' u ON u.id = l.pelaku'
                    . ' ORDER BY l.waktu_mulai DESC',
                    $this->params($s)];

            case 'resolution_time':
                return ['SELECT f.id_followup, f.judul, f.kategori, f.created_at, f.selesai_at,'
                    . ' CAST(f.selesai_at - f.created_at AS SIGNED) / 3600 durasi_jam,'
                    . ' COALESCE(u.name, ' . $this->kutip(self::TANPA_PELAKU) . ') AS pelaku'
                    . ' FROM ' . $this->t('crm_followup') . ' f'
                    . ' LEFT JOIN ' . $this->t('user') . ' u ON u.id = f.selesai_oleh'
                    . " WHERE f.status = 'selesai' AND f.selesai_at IS NOT NULL"
                    . ' AND f.created_at BETWEEN :awal_unix: AND :akhir_unix:'
                    . $this->klausulPelaku($s, 'f.selesai_oleh')
                    . ' ORDER BY durasi_jam DESC',
                    $this->paramsUnix($s)];

            default: // gmv_cs dan order_diproses memakai daftar nota yang sama
                return ['SELECT o.id_invoice, o.seri, o.tanggal_pesan, o.status_pesanan, o.status_pembayaran,'
                    . ' COALESCE(b.qty, 0) qty,'
                    . ' COALESCE(b.barang, 0) + COALESCE(bc.biaya, 0) gmv,'
                    . ' COALESCE(pm.dibayar, 0) dibayar, u.name AS pembuat'
                    . ' FROM ' . $this->t('invoice') . ' o'
                    . $this->joinUser() . $this->joinNilai() . $this->whereNota($s)
                    // GMV hanya dihitung dari nota lunas, jadi daftarnya juga begitu
                    . ($indikator === 'gmv_cs' ? " AND o.status_pembayaran IN ('5','6')" : '')
                    . ($indikator === 'gmv_cs' ? ' ORDER BY gmv DESC' : ' ORDER BY o.tanggal_pesan DESC'),
                    $this->params($s)];
        }
    }

    // ------------------------------------------------------------------
    // target dan bobot
    // ------------------------------------------------------------------

    /**
     * Daftar pegawai untuk dropdown saringan peran.
     *
     * Yang dikirim ke halaman hanya id-nya; controller memeriksa id itu ada di
     * daftar ini sebelum dipakai, jadi nilai dari URL tidak pernah langsung masuk
     * ke query.
     *
     * @return array<int, string>
     */
    public function pegawaiPilihan(string $peran): array
    {
        $pilihan = [];

        foreach ($this->db->query('SELECT id, name, level FROM ' . $this->t('user')
            . " WHERE status = 'active' AND deleted_at IS NULL"
            . ' AND level IN (' . $this->daftarKutip($this->levelPeran($peran)) . ')'
            . ' ORDER BY name ASC')->getResultArray() as $u) {
            $pilihan[(int) $u['id']] = $u['name'] . ' (' . $u['level'] . ')';
        }

        return $pilihan;
    }

    /** @return list<array<string, mixed>> */
    public function targetSemua(): array
    {
        return $this->db->table('kpi_target')->orderBy('peran', 'ASC')->orderBy('nama', 'ASC')->get()->getResultArray();
    }

    public function targetSatu(string $indikator): ?array
    {
        return $this->db->table('kpi_target')->where('indikator', $indikator)->get()->getRowArray();
    }

    /**
     * Simpan target sebuah indikator.
     *
     * Hanya kolom konfigurasi yang ditulis; nama, definisi, dan aturan perhitungan
     * sengaja tidak bisa diubah dari halaman ini supaya rumusnya tidak berubah diam-diam.
     */
    public function simpanTarget(string $indikator, array $data, ?int $user): bool
    {
        return $this->db->table('kpi_target')
            ->where('indikator', $indikator)
            ->update([
                'target'     => $data['target'],
                'bobot'      => $data['bobot'],
                'aktif'      => $data['aktif'],
                'updated_at' => time(),
                'updated_by' => $user,
            ]);
    }

    // ------------------------------------------------------------------
    // agregasi
    // ------------------------------------------------------------------

    /**
     * Nilai per pegawai dari nota yang ditulisnya.
     *
     * @return array<int, array<string, mixed>> indexed by user_id
     */
    private function notePerPegawai(array $s): array
    {
        $bind = $this->params($s);

        $valid = $this->db->query('SELECT u.id user_id, u.name pegawai, u.level,'
            . ' COUNT(*) jumlah_order, COUNT(DISTINCT o.pemesan_id) pelanggan,'
            . ' COALESCE(SUM(b.qty), 0) qty,'
            . ' COALESCE(SUM(b.barang), 0) nilai_barang, COALESCE(SUM(bc.biaya), 0) biaya_tambahan,'
            // GMV = nota lunas saja; jumlah_order dan dibayar tetap semua nota valid
            . $this->nilaiGmv() . ','
            . ' COALESCE(SUM(pm.dibayar), 0) dibayar,'
            . " SUM(o.status_pembayaran IN ('5','6')) lunas"
            . ' FROM ' . $this->t('invoice') . ' o'
            . $this->joinUser() . $this->joinNilai() . $this->whereNota($s)
            . ' GROUP BY u.id, u.name, u.level', $bind)->getResultArray();

        // penyebut void rate: semua nota yang pernah dipegang orang ini, termasuk
        // yang kemudian dihapus, supaya menghapus tidak menghapus dirinya sendiri
        $diproses = [];
        foreach ($this->db->query('SELECT o.user_id id, COUNT(*) jumlah FROM ' . $this->t('invoice') . ' o'
            . ' JOIN ' . $this->t('user') . " u ON u.id = o.user_id AND u.status = 'active' AND u.deleted_at IS NULL"
            . ' WHERE o.tanggal_pesan BETWEEN :awal: AND :akhir:'
            . ' AND o.juragan_id IN (' . $this->daftarId($s['juragan_ids']) . ')'
            . ' AND u.level IN (' . $this->daftarKutip($this->levelPeran($s['peran'])) . ')'
            . ' GROUP BY o.user_id', $bind)->getResultArray() as $baris) {
            $diproses[(int) $baris['id']] = (int) $baris['jumlah'];
        }

        $hasil = [];
        foreach ($valid as $baris) {
            $id                = (int) $baris['user_id'];
            $baris['diproses'] = $diproses[$id] ?? (int) $baris['jumlah_order'];
            $baris['gmv']      = round((float) $baris['gmv']);
            $baris['dibayar']  = round((float) $baris['dibayar']);
            $hasil[$id]        = $baris;
        }

        return $hasil;
    }

    /**
     * Durasi bayar -> packing/kirim per nota, hanya untuk nota lunas.
     *
     * Penanggung jawabnya pelaksana tahap pengepakan (invoice_status.user_id).
     * Nota yang hanya punya tanggal kirim tidak punya pelaku tercatat, karena
     * order_pengiriman tidak menyimpan siapa yang mengirim.
     */
    private function slaDasar(array $s): string
    {
        // Dua-duanya dibungkus CAST: tanpa itu selisih kolom INT UNSIGNED yang
        // bernilai negatif (tanggal kirim diisi sebelum tanggal bayar) langsung
        // error "BIGINT UNSIGNED value is out of range", bukan mengembalikan minus.
        $bati = '9999999999';
        $selesai = 'CAST(LEAST(IFNULL(st.tanggal_packing, ' . $bati . '), IFNULL(kg.tanggal_kirim, ' . $bati . ')) AS SIGNED)';

        return 'SELECT o.id_invoice, o.juragan_id, o.user_id AS pembuat, o.tanggal_pesan,'
            . ' pb.tanggal_bayar AS waktu_bayar,'
            . ' st.user_id AS pelaksana,'
            . ' LEAST(IFNULL(st.tanggal_packing, ' . $bati . '), IFNULL(kg.tanggal_kirim, ' . $bati . ')) AS waktu_fu,'
            . ' (' . $selesai . ' - CAST(pb.tanggal_bayar AS SIGNED)) / 3600 AS durasi_jam'
            . ' FROM ' . $this->t('invoice') . ' o'
            . ' JOIN (SELECT invoice_id, MIN(tanggal_pembayaran) tanggal_bayar FROM ' . $this->t('pembayaran')
            . " WHERE status = '3' GROUP BY invoice_id) pb ON pb.invoice_id = o.id_invoice"
            . ' LEFT JOIN (SELECT invoice_id, tanggal_masuk AS tanggal_packing, user_id,'
            . ' ROW_NUMBER() OVER (PARTITION BY invoice_id ORDER BY tanggal_masuk) urut'
            . ' FROM ' . $this->t('invoice_status') . " WHERE status = '7') st"
            . ' ON st.invoice_id = o.id_invoice AND st.urut = 1'
            . ' LEFT JOIN (SELECT invoice_id, MIN(tanggal_kirim) tanggal_kirim FROM ' . $this->t('pengiriman')
            . ' GROUP BY invoice_id) kg ON kg.invoice_id = o.id_invoice'
            . ' WHERE o.deleted_at IS NULL AND o.status_pembayaran IN (\'5\',\'6\')'
            . " AND o.status_pesanan <> '3'"
            . ' AND o.tanggal_pesan BETWEEN :awal: AND :akhir:'
            . ' AND o.juragan_id IN (' . $this->daftarId($s['juragan_ids']) . ')'
            . $this->klausulPelaku($s, 'st.user_id');
    }

    /** Baris yang ikut dihitung: punya waktu fulfillment dan durasinya tidak negatif. */
    private function kondisiSla(string $alias = 'm'): string
    {
        return $alias . '.waktu_fu < 9999999999 AND ' . $alias . '.durasi_jam >= 0';
    }

    /** @return array{contoh: int, rata: float|null, median: float|null, buang: int} */
    private function slaRingkas(array $s): array
    {
        $dasar = $this->slaDasar($s);

        $ringkas = $this->db->query('SELECT COUNT(*) contoh, AVG(m.durasi_jam) rata,'
            . ' SUM(CASE WHEN m.waktu_fu < 9999999999 AND m.durasi_jam < 0 THEN 1 ELSE 0 END) buang'
            . ' FROM (' . $dasar . ') m WHERE ' . $this->kondisiSla(), $this->params($s))->getRowArray();

        $median = $this->median($this->kondisiSla(), $dasar, $this->params($s), 'durasi_jam', 'm');

        return [
            'contoh' => (int) $ringkas['contoh'],
            'rata'   => $ringkas['rata'],
            'median' => $median,
            'buang'  => (int) $ringkas['buang'],
        ];
    }

    /** @return array<int, array{contoh: int, rata: float|null}> indexed by user_id */
    private function slaPerPegawai(array $s): array
    {
        $dasar = $this->slaDasar($s);

        return $this->indexPegawai($this->db->query('SELECT m.pelaksana user_id, u.name pegawai,'
            . ' COUNT(*) contoh, AVG(m.durasi_jam) rata'
            . ' FROM (' . $dasar . ') m'
            . ' JOIN ' . $this->t('user') . " u ON u.id = m.pelaksana AND u.status = 'active' AND u.deleted_at IS NULL"
            . ' WHERE ' . $this->kondisiSla()
            . ' AND m.pelaksana IS NOT NULL'
            . ' AND u.level IN (' . $this->daftarKutip($this->levelPeran($s['peran'])) . ')'
            . ' GROUP BY m.pelaksana, u.name', $this->params($s))->getResultArray());
    }

    /**
     * First response time per sesi percakapan.
     *
     * Sesi baru dimulai bila pesan masuk berikutnya berjarak lebih dari
     * SESI_CHAT_JAM, jadi satu percakapan panjang tidak dihitung satu kali saja.
     * Balasan otomatis (crm_pesan_log) dan pesan keluar yang gagal tidak dihitung
     * sebagai respons manusia.
     */
    private function frtDasar(array $s): string
    {
        $gap    = self::SESI_CHAT_JAM * 3600;
        $paling = '9999999999';

        // waktu balas pertama oleh manusia dalam sesi yang sama; dipakai dua kali
        // (untuk kolom waktu_balas dan untuk durasinya) sehingga ditulis sekali di sini
        $balas = '(SELECT MIN(p.created_at) FROM ' . $this->t('crm_chat_pesan') . ' p'
            . " WHERE p.percakapan_id = ses.percakapan_id AND p.arah = 'keluar'"
            . ' AND p.user_id IS NOT NULL AND p.status <> \'gagal\''
            . ' AND p.created_at >= ses.waktu_masuk'
            . ' AND p.created_at < IFNULL(ses.sesi_berikutnya, ' . $paling . '))';

        return 'SELECT ses.percakapan_id, ses.pelanggan_id, ses.waktu_masuk, ses.pelaku,'
            . ' RIGHT(CONCAT(\'\', ses.nomor), 4) AS nomor,'
            . ' ' . $balas . ' waktu_balas,'
            . ' (CAST(' . $balas . ' AS SIGNED) - CAST(ses.waktu_masuk AS SIGNED)) / 60 frt_menit'
            . ' FROM (SELECT y.percakapan_id, y.nomor_wa nomor, y.pelanggan_id, y.waktu_masuk, y.pelaku,'
            . ' LEAD(y.waktu_masuk) OVER (PARTITION BY y.percakapan_id ORDER BY y.waktu_masuk) sesi_berikutnya'
            . ' FROM (SELECT m.percakapan_id, cp.nomor_wa, cp.pelanggan_id, m.created_at waktu_masuk,'
            . ' (SELECT p.user_id FROM ' . $this->t('crm_chat_pesan') . ' p'
            . " WHERE p.percakapan_id = cp.id_percakapan AND p.arah = 'keluar'"
            . ' AND p.user_id IS NOT NULL AND p.status <> \'gagal\''
            . ' ORDER BY p.created_at LIMIT 1) pelaku,'
            . ' LAG(m.created_at) OVER (PARTITION BY m.percakapan_id ORDER BY m.created_at) sebelumnya'
            . ' FROM ' . $this->t('crm_chat_pesan') . ' m'
            . ' JOIN ' . $this->t('crm_chat_percakapan') . ' cp ON cp.id_percakapan = m.percakapan_id'
            . " WHERE m.arah = 'masuk'"
            . ' AND DATE(FROM_UNIXTIME(m.created_at)) BETWEEN :awal: AND :akhir:'
            . ') y WHERE (y.sebelumnya IS NULL'
            . ' OR CAST(y.waktu_masuk AS SIGNED) - CAST(y.sebelumnya AS SIGNED) > ' . $gap . ')) ses'
            . ' WHERE DATE(FROM_UNIXTIME(ses.waktu_masuk)) BETWEEN :awal: AND :akhir:'
            . $this->klausulPelaku($s, 'ses.pelaku');
    }

    /** @return array{contoh: int, rata: float|null, median: float|null, belum: int} */
    private function frtRingkas(array $s): array
    {
        $dasar = $this->frtDasar($s);

        $ringkas = $this->db->query('SELECT COUNT(*) sesi, SUM(f.waktu_balas IS NOT NULL) dijawab,'
            . ' AVG(f.frt_menit) rata, SUM(f.waktu_balas IS NULL) belum'
            . ' FROM (' . $dasar . ') f', $this->paramsFrt($s))->getRowArray();

        $median = $this->median('f.waktu_balas IS NOT NULL', $dasar, $this->paramsFrt($s), 'frt_menit', 'f');

        return [
            'contoh' => (int) $ringkas['dijawab'],
            'rata'   => $ringkas['rata'],
            'median' => $median,
            'belum'  => (int) $ringkas['belum'],
        ];
    }

    /** @return array<int, array{contoh: int, rata: float|null}> */
    private function frtPerPegawai(array $s): array
    {
        return $this->indexPegawai($this->db->query('SELECT f.pelaku user_id, u.name pegawai,'
            . ' COUNT(*) contoh, AVG(f.frt_menit) rata'
            . ' FROM (' . $this->frtDasar($s) . ') f'
            . ' JOIN ' . $this->t('user') . " u ON u.id = f.pelaku AND u.status = 'active' AND u.deleted_at IS NULL"
            . ' WHERE f.waktu_balas IS NOT NULL'
            . ' AND u.level IN (' . $this->daftarKutip($this->levelPeran($s['peran'])) . ')'
            . ' GROUP BY f.pelaku, u.name', $this->paramsFrt($s))->getResultArray());
    }

    /**
     * Lead = percakapan dengan pelanggan yang belum pernah order sebelumnya.
     *
     * Ditanggung jawabkan ke CS pertama yang membalas percakapan itu, dan satu
     * pelanggan hanya dihitung satu lead dalam periode (dipisah per CS supaya
     * dua orang yang ikut menangani tidak saling merebut perhitungan).
     *
     * Runtuhnya tiga tingkat: percakapan -> lead per pelanggan -> penanggung jawab.
     * Batas waktu konversi tidak bisa ditulis sebagai MIN(m.created_at) di dalam
     * EXISTS (MySQL menolak aggregate di WHERE), jadi waktu mulai lead dibawa dulu
     * ke subquery tingkat tengah, lalu dibandingkan dengan nota di sana.
     */
    private function konversiDasar(array $s): string
    {
        $jendela = self::ATRIBUSI_HARI * 86400;

        return 'SELECT l.pelanggan_id, MIN(l.waktu_mulai) waktu_mulai, MIN(l.pelaku) pelaku,'
            . ' MAX(l.terkonversi) terkonversi'
            . ' FROM (SELECT c.pelanggan_id, c.waktu_mulai, c.pelaku,'
            . ' EXISTS(SELECT 1 FROM ' . $this->t('invoice') . ' o'
            . ' WHERE o.pemesan_id = c.pelanggan_id AND o.deleted_at IS NULL'
            . " AND o.status_pesanan <> '3' AND o.status_pembayaran IN ('5','6')"
            . ' AND o.created_at BETWEEN c.waktu_mulai AND c.waktu_mulai + ' . $jendela . ') terkonversi'
            . ' FROM (SELECT cp.pelanggan_id, MIN(m.created_at) waktu_mulai,'
            . ' (SELECT p.user_id FROM ' . $this->t('crm_chat_pesan') . ' p'
            . " WHERE p.percakapan_id = cp.id_percakapan AND p.arah = 'keluar'"
            . ' AND p.user_id IS NOT NULL AND p.status <> \'gagal\''
            . ' ORDER BY p.created_at LIMIT 1) pelaku'
            . ' FROM ' . $this->t('crm_chat_percakapan') . ' cp'
            . ' JOIN ' . $this->t('crm_chat_pesan') . " m ON m.percakapan_id = cp.id_percakapan AND m.arah = 'masuk'"
            . ' WHERE cp.pelanggan_id IS NOT NULL'
            . ' AND NOT EXISTS(SELECT 1 FROM ' . $this->t('invoice') . ' o0'
            . ' WHERE o0.pemesan_id = cp.pelanggan_id AND o0.deleted_at IS NULL'
            . ' AND o0.created_at < m.created_at)'
            . ' GROUP BY cp.id_percakapan, cp.pelanggan_id'
            . ' HAVING DATE(FROM_UNIXTIME(waktu_mulai)) BETWEEN :awal: AND :akhir:'
            . ') c WHERE 1 = 1' . $this->klausulPelaku($s, 'c.pelaku')
            . ') l GROUP BY l.pelanggan_id';
    }

    /** @return array<int, array{lead: int, terkonversi: int}> indexed by user_id (0 = belum ada penanggung jawab) */
    private function konversiPerPegawai(array $s): array
    {
        $hasil = [];
        // "lead" kata kunci di MySQL 8 (window function), jadi aliasnya jumlah_lead
        foreach ($this->db->query('SELECT l.pelaku user_id, COUNT(*) jumlah_lead, SUM(l.terkonversi) terkonversi'
            . ' FROM (' . $this->konversiDasar($s) . ') l GROUP BY l.pelaku', $this->params($s))->getResultArray() as $baris) {
            $id                = (int) ($baris['user_id'] ?? 0);
            $hasil[$id]        = [
                'pegawai'      => $id === 0 ? 'belum ada penanggung jawab' : $this->namaPegawai($id),
                'lead'         => (int) $baris['jumlah_lead'],
                'terkonversi'  => (int) $baris['terkonversi'],
            ];
        }

        return $hasil;
    }

    /** @return array<int, array{contoh: int, rata: float|null}> */
    private function resolusiPerPegawai(array $s): array
    {
        return $this->indexPegawai($this->db->query('SELECT f.selesai_oleh user_id, u.name pegawai,'
            . ' COUNT(*) contoh, AVG(CAST(f.selesai_at - f.created_at AS SIGNED) / 3600) rata'
            . ' FROM ' . $this->t('crm_followup') . ' f'
            . ' JOIN ' . $this->t('user') . " u ON u.id = f.selesai_oleh AND u.status = 'active' AND u.deleted_at IS NULL"
            . " WHERE f.status = 'selesai' AND f.selesai_at IS NOT NULL"
            . ' AND f.created_at BETWEEN :awal_unix: AND :akhir_unix:'
            . ' AND u.level IN (' . $this->daftarKutip($this->levelPeran($s['peran'])) . ')'
            . $this->klausulPelaku($s, 'f.selesai_oleh')
            . ' GROUP BY f.selesai_oleh, u.name', $this->paramsUnix($s))->getResultArray());
    }

    /**
     * Nota yang dihapus per pelaku.
     *
     * alasan_batal NULL berarti nota dihapus dari jalur yang belum meminta alasan
     * (misalnya halaman CS) dan tidak dihitung sebagai kesalahan siapa pun.
     *
     * @return array<int, array{total: int, salah: int, sah: int, tanpa_alasan: int}>
     */
    private function voidPerPegawai(array $s): array
    {
        $salah = $this->daftarKutip($this->slugKesalahan());
        $sah   = $this->daftarKutip($this->slugSah());

        $hasil = [];
        foreach ($this->db->query('SELECT o.deleted_by user_id, u.name pegawai, COUNT(*) total,'
            . ' SUM(o.alasan_batal IN (' . $salah . ')) salah,'
            . ' SUM(o.alasan_batal IN (' . $sah . ')) sah,'
            . ' SUM(o.alasan_batal IS NULL) tanpa_alasan'
            . ' FROM ' . $this->t('invoice') . ' o'
            . ' JOIN ' . $this->t('user') . " u ON u.id = o.deleted_by AND u.status = 'active' AND u.deleted_at IS NULL"
            . ' WHERE o.deleted_at IS NOT NULL AND o.deleted_by IS NOT NULL'
            . ' AND o.deleted_at BETWEEN :awal_unix: AND :akhir_unix:'
            . ' AND o.juragan_id IN (' . $this->daftarId($s['juragan_ids']) . ')'
            . $this->klausulPelaku($s, 'o.deleted_by')
            . ' GROUP BY o.deleted_by, u.name', $this->paramsUnix($s))->getResultArray() as $baris) {
            $hasil[(int) $baris['user_id']] = [
                'pegawai'      => $baris['pegawai'],
                'total'        => (int) $baris['total'],
                'salah'        => (int) $baris['salah'],
                'sah'          => (int) $baris['sah'],
                'tanpa_alasan' => (int) $baris['tanpa_alasan'],
            ];
        }

        return $hasil;
    }

    private function slugKesalahan(): array
    {
        helper('fungsi');

        return array_keys(array_filter(alasan_batal(), static fn ($a) => $a['jenis'] === 'kesalahan'));
    }

    private function slugSah(): array
    {
        helper('fungsi');

        return array_keys(array_filter(alasan_batal(), static fn ($a) => $a['jenis'] === 'sah'));
    }

    // ------------------------------------------------------------------
    // peringkat poin aktivitas
    // ------------------------------------------------------------------

    /**
     * Aturan poin: satu baris untuk setiap aktivitas yang meninggalkan jejak
     * beserta pelakunya di database.
     *
     * 'poin' negatif dipakai untuk kerja yang merugikan. 'catatan' muncul di
     * halaman ranking untuk aturan yang angkanya hari ini masih nol karena
     * pencatatannya baru dimulai atau tabelnya belum dipakai.
     */
    public const ATURAN_POIN = [
        'nota_dibuat'           => ['nama' => 'Nota dibuat', 'sumber' => 'order_invoice.user_id', 'satuan' => 'nota', 'poin' => 2],
        'nota_lunas'            => ['nama' => 'Nota lunas (closing)', 'sumber' => 'order_invoice.status_pembayaran 5/6', 'satuan' => 'nota', 'poin' => 3],
        'nilai_lunas'           => ['nama' => 'Nilai nota lunas', 'sumber' => 'order_dibeli + order_biaya nota lunas', 'satuan' => 'per Rp 1 juta', 'poin' => 1],
        'tahap_produksi'        => ['nama' => 'Tahap produksi dikerjakan', 'sumber' => 'order_invoice_status.user_id', 'satuan' => 'catatan tahap', 'poin' => 1, 'catatan' => 'kolom pelaksana baru ada sejak 9 Oktober 2026 dan belum pernah terisi'],
        'foto_progres'          => ['nama' => 'Foto progres diunggah', 'sumber' => 'order_invoice_foto.user_id', 'satuan' => 'foto', 'poin' => 2, 'catatan' => 'foto yang notanya sudah dihapus permanen tidak bisa dipastikan tokonya, jadi tidak ikut dinilai'],
        'balasan_chat'          => ['nama' => 'Pesan WhatsApp dibalas', 'sumber' => 'order_crm_chat_pesan arah keluar, bukan gagal', 'satuan' => 'pesan', 'poin' => 3],
        'sesi_dijawab'          => ['nama' => 'Sesi chat dijawab pertama', 'sumber' => 'sesi first_response_time', 'satuan' => 'sesi', 'poin' => 5],
        'lead_jadi_order'       => ['nama' => 'Lead menjadi order', 'sumber' => 'konversi lead dalam 14 hari', 'satuan' => 'pelanggan', 'poin' => 10],
        'tindak_lanjut_selesai' => ['nama' => 'Tindak lanjut diselesaikan', 'sumber' => 'order_crm_followup.selesai_oleh', 'satuan' => 'tiket', 'poin' => 6],
        'aktivitas_crm'         => ['nama' => 'Aktivitas pelanggan dicatat', 'sumber' => 'order_crm_aktivitas.user_id', 'satuan' => 'catatan', 'poin' => 1, 'catatan' => 'catatan CRM tidak terikat satu toko'],
        'pesan_manual'          => ['nama' => 'Pesan manual terkirim', 'sumber' => 'order_crm_pesan_log manual, status terkirim', 'satuan' => 'pesan', 'poin' => 1],
        'broadcast_dibuat'      => ['nama' => 'Broadcast dibuat', 'sumber' => 'order_crm_broadcast.created_by', 'satuan' => 'broadcast', 'poin' => 5, 'catatan' => 'tabel broadcast masih kosong'],
        'void_salah'            => ['nama' => 'Nota dibatalkan karena kesalahan', 'sumber' => 'order_invoice.deleted_by + alasan_batal', 'satuan' => 'nota', 'poin' => -5, 'catatan' => 'pembatalan beralasan sah dan data lama tanpa alasan tidak dikurangi poin'],
    ];

    /**
     * Kerja yang tidak masuk poin karena tabelnya tidak menyimpan pelaku.
     *
     * Daftarnya ikut ditampilkan di halaman ranking supaya pembaca tahu angka
     * ini bukan ukuran lengkap dari semua yang dikerjakan seseorang.
     */
    public const TIDAK_TERNILAI = [
        'order_pembayaran'           => 'mencatat pembayaran',
        'order_pengiriman'           => 'mengirim barang',
        'order_produk'               => 'mengubah master data barang',
        'order_user'                 => 'membuat dan mengubah akun',
        'order_crm_chat_pesan masuk' => 'menerima pesan dari pelanggan (datang dari webhook)',
    ];

    /**
     * Peringkat karyawan dari poin aktivitas pada periode terpilih.
     *
     * Hitungannya memakai query per pegawai yang sama dengan kartu KPI, jadi
     * poin tidak mungkin bertentangan dengan angka di halaman Ringkasan.
     *
     * @return list<array<string, mixed>>
     */
    public function peringkat(array $s): array
    {
        $catatan = $this->catatanAktivitas($s);
        $orang   = $this->pegawaiDetil($s['peran']);

        if (! empty($s['user_id'])) {
            $orang = array_intersect_key($orang, [(int) $s['user_id'] => 1]);
        }

        $hasil = [];
        foreach ($orang as $id => $u) {
            $jumlah = [];
            $poin   = [];
            $total  = 0.0;

            foreach (self::ATURAN_POIN as $slug => $aturan) {
                $n          = (int) ($catatan[$id][$slug] ?? 0);
                $jumlah[$slug] = $n;
                $poin[$slug]   = round($n * (float) $aturan['poin'], 1);
                $total        += $poin[$slug];
            }

            $hasil[] = [
                'user_id'  => $id,
                'pegawai'  => $u['nama'],
                'level'    => $u['level'],
                'jumlah'   => $jumlah,
                'poin'     => $poin,
                'total'    => round($total, 1),
                'gmv'      => (float) ($catatan[$id]['gmv'] ?? 0),
                'tercatat' => array_sum(array_map(
                    static fn ($slug) => (int) ($jumlah[$slug] ?? 0),
                    array_keys(self::ATURAN_POIN)
                )),
            ];
        }

        usort($hasil, static fn ($a, $b) => [$b['total'], $a['pegawai']] <=> [$a['total'], $b['pegawai']]);

        // poin yang sama mendapat peringkat yang sama, peringkat berikutnya ikut melompat
        $nomor  = 0;
        $poinLalu = null;
        foreach ($hasil as $i => $baris) {
            if ($baris['total'] !== $poinLalu) {
                $nomor    = $i + 1;
                $poinLalu = $baris['total'];
            }
            $hasil[$i]['peringkat'] = $nomor;
        }

        return $hasil;
    }

    /**
     * Aturan poin beserta berapa banyak aktivitasnya tercatat pada periode ini.
     *
     * Dipakai tabel "aturan poin" supaya aktivitas yang belum bernilai apa-apa
     * terlihat sebagai 0 tercatat, bukan sebagai aturan yang hilang.
     *
     * @return list<array<string, mixed>>
     */
    public function aturanPoin(array $s): array
    {
        $catatan = $this->catatanAktivitas($s);

        // ada jejak yang tidak bisa dipakai untuk menilai orang: balasan yang akun
        // pelakunya sudah tidak ada, dan lead yang belum pernah dibalas siapa pun
        $konv  = $this->konversiPerPegawai($s);
        $pesan = (int) $this->db->query('SELECT COUNT(1) j FROM ' . $this->t('crm_chat_pesan')
            . " WHERE arah = 'keluar' AND status <> 'gagal'"
            . ' AND created_at BETWEEN :awal_unix: AND :akhir_unix:', $this->paramsUnix($s))->getRowArray()['j'];

        $belum = [
            'balasan_chat'      => max(0, $pesan
                - array_sum(array_column($catatan, 'balasan_chat'))),
            'sesi_dijawab'      => max(0, $this->frtRingkas($s)['contoh']
                - array_sum(array_column($catatan, 'sesi_dijawab'))),
            'lead_jadi_order'   => (int) ($konv[0]['terkonversi'] ?? 0),
        ];

        $hasil = [];
        foreach (self::ATURAN_POIN as $slug => $aturan) {
            $tercatat = 0;
            foreach ($catatan as $baris) {
                $tercatat += (int) ($baris[$slug] ?? 0);
            }

            $hasil[] = $aturan + [
                'slug'         => $slug,
                'tercatat'     => $tercatat,
                'belum_ternilai' => (int) ($belum[$slug] ?? 0),
            ];
        }

        return $hasil;
    }

    /**
     * Jumlah tiap aktivitas per pegawai, digabung dari query nota dan jejak CRM.
     *
     * Halaman ranking memakai hasil ini dua kali (tabel peringkat dan tabel
     * aturan), jadi dihafal per kombinasi saringan supaya query berat tidak
     * dijalankan dua kali dalam satu permintaan.
     *
     * @return array<int, array<string, int|float>>
     */
    private function catatanAktivitas(array $s): array
    {
        $kunci = $s['awal'] . '|' . $s['akhir'] . '|' . $s['peran'] . '|'
            . ($s['user_id'] ?? '') . '|' . implode(',', $s['juragan_ids']);

        if (! isset($this->aktivitas[$kunci])) {
            $this->aktivitas[$kunci] = $this->hitungAktivitas($s);
        }

        return $this->aktivitas[$kunci];
    }

    /** @return array<int, array<string, int|float>> */
    private function hitungAktivitas(array $s): array
    {
        $catatan = [];

        foreach ($this->notePerPegawai($s) as $id => $b) {
            $catatan[$id] = [
                'nota_dibuat' => (int) $b['jumlah_order'],
                'nota_lunas'  => (int) $b['lunas'],
                // poin nilai diberikan per Rp 1 juta penuh, recehannya tidak dihitung
                'nilai_lunas' => (int) floor(max(0.0, (float) $b['gmv']) / 1000000),
                'gmv'         => (float) $b['gmv'],
            ];
        }

        $sumber = [
            'sesi_dijawab'          => fn (array $b) => (int) $b['contoh'],
            'lead_jadi_order'       => fn (array $b) => (int) $b['terkonversi'],
            'tindak_lanjut_selesai' => fn (array $b) => (int) $b['contoh'],
            'void_salah'            => fn (array $b) => (int) $b['salah'],
        ];

        foreach ($sumber as $slug => $ambil) {
            $peta = match ($slug) {
                'sesi_dijawab'          => $this->frtPerPegawai($s),
                'lead_jadi_order'       => $this->konversiPerPegawai($s),
                'tindak_lanjut_selesai' => $this->resolusiPerPegawai($s),
                default                 => $this->voidPerPegawai($s),
            };

            foreach ($peta as $id => $baris) {
                if ($id === 0) {
                    continue;
                }
                $catatan[$id][$slug] = $ambil($baris);
            }
        }

        foreach ($this->jejakLain($s) as $slug => $perPegawai) {
            foreach ($perPegawai as $id => $jumlah) {
                $catatan[$id][$slug] = $jumlah;
            }
        }

        return $catatan;
    }

    /**
     * Aktivitas di luar nota: tahap produksi, foto, balasan chat, pesan manual,
     * catatan CRM, dan broadcast. Semuanya dihitung dari kolom pelakunya sendiri.
     *
     * Tahap produksi dan foto ikut menilai nota yang kemudian dibatalkan:
     * kerjanya memang terjadi. Catatan CRM dan broadcast tidak punya kaitan toko,
     * jadi saringan toko tidak berlaku di sana dan itu ditulis di halaman ranking.
     *
     * @return array<string, array<int, int>>
     */
    private function jejakLain(array $s): array
    {
        $bind  = $this->paramsUnix($s);
        $level = ' AND u.level IN (' . $this->daftarKutip($this->levelPeran($s['peran'])) . ')';
        $toko  = ' AND o.juragan_id IN (' . $this->daftarId($s['juragan_ids']) . ')';

        $query = [
            'tahap_produksi' => 'SELECT st.user_id user_id, COUNT(1) jumlah FROM ' . $this->t('invoice_status') . ' st'
                . $this->sambungkan('st') . ' JOIN ' . $this->t('invoice') . ' o ON o.id_invoice = st.invoice_id'
                . ' WHERE st.tanggal_masuk BETWEEN :awal_unix: AND :akhir_unix:' . $toko . $level
                . $this->klausulPelaku($s, 'st.user_id') . ' GROUP BY st.user_id',

            'foto_progres' => 'SELECT f.user_id user_id, COUNT(1) jumlah FROM ' . $this->t('invoice_foto') . ' f'
                . $this->sambungkan('f') . ' JOIN ' . $this->t('invoice') . ' o ON o.id_invoice = f.invoice_id'
                . ' WHERE f.created_at BETWEEN :awal_unix: AND :akhir_unix:' . $toko . $level
                . $this->klausulPelaku($s, 'f.user_id') . ' GROUP BY f.user_id',

            'balasan_chat' => 'SELECT p.user_id user_id, COUNT(1) jumlah FROM ' . $this->t('crm_chat_pesan') . ' p'
                . $this->sambungkan('p')
                . " WHERE p.arah = 'keluar' AND p.status <> 'gagal'"
                . ' AND p.created_at BETWEEN :awal_unix: AND :akhir_unix:' . $level
                . $this->klausulPelaku($s, 'p.user_id') . ' GROUP BY p.user_id',

            'pesan_manual' => 'SELECT g.user_id user_id, COUNT(1) jumlah FROM ' . $this->t('crm_pesan_log') . ' g'
                . $this->sambungkan('g')
                . " WHERE g.tipe_pesan = 'manual' AND g.status = 'terkirim'"
                . ' AND g.created_at BETWEEN :awal_unix: AND :akhir_unix:' . $level
                . $this->klausulPelaku($s, 'g.user_id') . ' GROUP BY g.user_id',

            'aktivitas_crm' => 'SELECT a.user_id user_id, COUNT(1) jumlah FROM ' . $this->t('crm_aktivitas') . ' a'
                . $this->sambungkan('a')
                . ' WHERE a.created_at BETWEEN :awal_unix: AND :akhir_unix:' . $level
                . $this->klausulPelaku($s, 'a.user_id') . ' GROUP BY a.user_id',

            'broadcast_dibuat' => 'SELECT b.created_by user_id, COUNT(1) jumlah FROM ' . $this->t('crm_broadcast') . ' b'
                . $this->sambungkan('b', 'created_by')
                . ' WHERE b.created_at BETWEEN :awal_unix: AND :akhir_unix:' . $level
                . $this->klausulPelaku($s, 'b.created_by') . ' GROUP BY b.created_by',
        ];

        $hasil = [];
        foreach ($query as $slug => $sql) {
            $isi = [];
            foreach ($this->db->query($sql, $bind)->getResultArray() as $baris) {
                $isi[(int) $baris['user_id']] = (int) $baris['jumlah'];
            }
            $hasil[$slug] = $isi;
        }

        return $hasil;
    }

    /** Join ke tabel akun aktif; kolom pelakunya bisa berbeda nama per tabel. */
    private function sambungkan(string $alias, string $kolom = 'user_id'): string
    {
        return ' JOIN ' . $this->t('user') . ' u ON u.id = ' . $alias . '.' . $kolom
            . " AND u.status = 'active' AND u.deleted_at IS NULL";
    }

    /** Akun yang dinilai, terpisah nama dan levelnya untuk tabel peringkat. */
    private function pegawaiDetil(string $peran): array
    {
        $isi = [];
        foreach ($this->db->query('SELECT id, name, level FROM ' . $this->t('user')
            . " WHERE status = 'active' AND deleted_at IS NULL"
            . ' AND level IN (' . $this->daftarKutip($this->levelPeran($peran)) . ')'
            . ' ORDER BY name ASC')->getResultArray() as $u) {
            $isi[(int) $u['id']] = ['nama' => $u['name'], 'level' => $u['level']];
        }

        return $isi;
    }

    // ------------------------------------------------------------------
    // pembantu
    // ------------------------------------------------------------------

    /**
     * Median tanpa fungsi MEDIAN (tidak ada di MySQL 8): hitung barisnya dulu,
     * lalu ambil satu atau dua baris tengah dengan LIMIT/OFFSET.
     *
     * $kondisi memakai $alias yang sama dengan subquerynya dan ditulis tanpa WHERE.
     */
    private function median(string $kondisi, string $dasar, array $bind, string $kolom, string $alias): ?float
    {
        $jumlah = (int) $this->db->query('SELECT COUNT(*) j FROM (' . $dasar . ') ' . $alias
            . ' WHERE ' . $kondisi, $bind)->getRowArray()['j'];

        if ($jumlah === 0) {
            return null;
        }

        $mulai = intdiv($jumlah - 1, 2);
        $ambil = $jumlah % 2 === 1 ? 1 : 2;

        $tengah = $this->db->query('SELECT ' . $alias . '.' . $kolom . ' nilai FROM (' . $dasar . ') ' . $alias
            . ' WHERE ' . $kondisi
            . ' ORDER BY ' . $alias . '.' . $kolom . ' ASC LIMIT ' . $ambil . ' OFFSET ' . $mulai, $bind)->getResultArray();

        return $tengah === [] ? null : array_sum(array_column($tengah, 'nilai')) / count($tengah);
    }

    /** Angka satu pegawai: tetap kosong kalau datangnya memang tidak ada. */
    private function bulat(mixed $nilai): ?float
    {
        return $nilai === null ? null : round((float) $nilai, 1);
    }

    /** @param list<array<string, mixed>> $baris @return array<int, array<string, mixed>> */
    private function indexPegawai(array $baris): array
    {
        $hasil = [];
        foreach ($baris as $b) {
            $hasil[(int) $b['user_id']] = $b;
        }

        return $hasil;
    }

    private function namaPegawai(int $id): string
    {
        return (string) ($this->db->table('user')->select('name')->where('id', $id)->get()->getRowArray()['name'] ?? '');
    }

    /** Periode dibaca dari kolom tanggal; $baki memilih bentuk keranjangnya. */
    private function baki(string $kolom, string $periode): string
    {
        return match ($periode) {
            'harian'   => 'DATE(' . $kolom . ')',
            'mingguan' => "DATE_FORMAT(" . $kolom . ", '%x-%v')",
            default    => "DATE_FORMAT(" . $kolom . ", '%Y-%m')",
        };
    }

    private function joinUser(): string
    {
        return ' JOIN ' . $this->t('user') . " u ON u.id = o.user_id AND u.status = 'active' AND u.deleted_at IS NULL";
    }

    /** Turunan nilai satu nota: barang, biaya tambahan (ongkir + potongan), pembayaran. */
    private function joinNilai(): string
    {
        return ' LEFT JOIN (SELECT invoice_id, SUM(qty * harga) AS barang, SUM(qty) AS qty'
            . ' FROM ' . $this->t('dibeli') . ' GROUP BY invoice_id) b ON b.invoice_id = o.id_invoice'
            . ' LEFT JOIN (SELECT invoice_id, SUM(nominal) AS biaya'
            . ' FROM ' . $this->t('biaya') . ' GROUP BY invoice_id) bc ON bc.invoice_id = o.id_invoice'
            . ' LEFT JOIN (SELECT invoice_id, SUM(total_pembayaran) AS dibayar'
            . ' FROM ' . $this->t('pembayaran') . ' GROUP BY invoice_id) pm ON pm.invoice_id = o.id_invoice';
    }

    /**
     * GMV dalam satu agregat nota: hanya nota lunas (status pembayaran 5/6) yang
     * dinilai, supaya angka karyawan, grafik, dan rekap memakai dasar yang sama
     * dengan definisi indikator. Nota yang masih berjalan ikut terlihat di
     * jumlah_order dan dibayar, bukan di GMV.
     */
    private function nilaiGmv(): string
    {
        return "COALESCE(SUM(CASE WHEN o.status_pembayaran IN ('5','6')"
            . ' THEN COALESCE(b.barang, 0) + COALESCE(bc.biaya, 0) ELSE 0 END), 0) gmv';
    }

    private function whereNota(array $s): string
    {
        return ' WHERE o.tanggal_pesan BETWEEN :awal: AND :akhir:'
            . ' AND o.deleted_at IS NULL AND o.status_pesanan <> \'3\''
            . ' AND o.juragan_id IN (' . $this->daftarId($s['juragan_ids']) . ')'
            . $this->klausulLevel($s['peran'])
            . $this->klausulPelaku($s, 'o.user_id');
    }

    private function klausulLevel(string $peran): string
    {
        return ' AND u.level IN (' . $this->daftarKutip($this->levelPeran($peran)) . ')';
    }

    private function levelPeran(string $peran): array
    {
        return self::LEVEL_PERAN[$peran] ?? self::LEVEL_PERAN['semua'];
    }

    /** Pembatas satu pegawai, dipakai dari halaman detail; id-nya sudah divali controller. */
    private function klausulPelaku(array $s, string $kolom): string
    {
        return empty($s['user_id']) ? '' : ' AND ' . $kolom . ' = :user_id:';
    }

    private function params(array $s): array
    {
        return array_filter([
            'awal'    => $s['awal'],
            'akhir'   => $s['akhir'],
            'user_id' => $s['user_id'] ?? null,
        ], static fn ($v) => $v !== null && $v !== '');
    }

    /** Versi periode untuk kolom unix (deleted_at, created_at tindak lanjut). */
    private function paramsUnix(array $s): array
    {
        return $this->params($s) + [
            'awal_unix'  => strtotime($s['awal'] . ' 00:00:00'),
            'akhir_unix' => strtotime($s['akhir'] . ' 23:59:59'),
        ];
    }

    private function paramsFrt(array $s): array
    {
        return $this->params($s);
    }

    private function daftarId(array $ids): string
    {
        $bersih = array_values(array_filter(array_map('intval', $ids)));

        return $bersih === [] ? '0' : implode(',', $bersih);
    }

    private function daftarKutip(array $nilai): string
    {
        return implode(',', array_map(
            fn ($n) => $this->kutip((string) $n),
            $nilai
        ));
    }

    /** Kutip teks konstanta di dalam SQL. Tidak pernah dipakai untuk input pengguna. */
    private function kutip(string $nilai): string
    {
        return "'" . str_replace("'", '', $nilai) . "'";
    }

    private function t(string $nama): string
    {
        return $this->db->prefixTable($nama);
    }
}
