<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\PencatatanCodModel;
use App\Models\PencatatanPilihanModel;

/**
 * Pencatatan COD: catatan kunjungan customer yang tidak berhenti jadi orderan.
 *
 * Halaman ini khusus admin dan superadmin, seperti seluruh menu Transaksi
 * lainnya, jadi datanya tidak perlu dipecah per toko.
 */
class Cod extends BaseController
{
    /** Kartu yang dimuat sekaligus; sisanya lewat halaman di bawah daftar. */
    private const PER_HALAMAN = 12;

    public function index()
    {
        $catatan = new PencatatanCodModel();
        $pilihan = new PencatatanPilihanModel();

        $cari      = trim((string) $this->request->getGet('cari'));
        $status    = (int) $this->request->getGet('status');
        $cs        = (int) $this->request->getGet('cs');
        $keperluan = (int) $this->request->getGet('keperluan');
        $dari      = trim((string) $this->request->getGet('dari'));
        $sampai    = trim((string) $this->request->getGet('sampai'));
        $periode   = trim((string) $this->request->getGet('periode'));

        $hari_ini  = date('Y-m-d');

        // Olah periode cepat jika dipilih
        if ($periode === 'hari_ini') {
            $dari   = $hari_ini;
            $sampai = $hari_ini;
        } elseif ($periode === 'kemarin') {
            $kemarin = date('Y-m-d', strtotime('-1 day'));
            $dari    = $kemarin;
            $sampai  = $kemarin;
        } elseif ($periode === '7_hari') {
            $dari    = date('Y-m-d', strtotime('-6 days'));
            $sampai  = $hari_ini;
        } elseif ($periode === 'bulan_ini') {
            $dari    = date('Y-m-01');
            $sampai  = $hari_ini;
        }

        // Batasi tanggal tidak melebihi hari ini
        if ($dari !== '' && strtotime($dari) > strtotime($hari_ini)) {
            $dari = $hari_ini;
        }
        if ($sampai !== '' && strtotime($sampai) > strtotime($hari_ini)) {
            $sampai = $hari_ini;
        }

        $filter = array_filter([
            'cari'      => $cari !== '' ? $cari : null,
            'status'    => $status > 0 ? $status : null,
            'cs'        => $cs > 0 ? $cs : null,
            'keperluan' => $keperluan > 0 ? $keperluan : null,
            'dari'      => $dari !== '' ? $dari : null,
            'sampai'    => $sampai !== '' ? $sampai : null,
        ]);

        $adaFilter = ! empty($filter);

        $total   = $catatan->hitung($filter);
        $halaman = max(1, (int) ceil($total / self::PER_HALAMAN));
        $page    = min(max(1, (int) ($this->request->getGet('page') ?: 1)), $halaman);

        // gagal simpan mengembalikan pengguna ke daftar yang sama; modal mana
        // yang harus terbuka lagi dibaca dari isian lama di view
        $boleh_hapus = $this->bolehHapus();

        $filterLain = $filter;
        unset($filterLain['status']);
        $status_counts    = $catatan->hitungPerStatus($filterLain);
        $total_semua      = $catatan->totalSemua($filterLain);
        $daftar_cs        = $pilihan->terpilih('cs');
        $daftar_keperluan = $pilihan->terpilih('keperluan');
        $daftar_status    = $pilihan->terpilih('status');

        $ringkasan_filter = [];
        if ($cari !== '') {
            $ringkasan_filter[] = 'Cari: "' . $cari . '"';
        }
        if ($cs > 0) {
            foreach ($daftar_cs as $c) {
                if ((int) $c['id'] === $cs) {
                    $ringkasan_filter[] = 'CS: ' . $c['label'];
                    break;
                }
            }
        }
        if ($keperluan > 0) {
            foreach ($daftar_keperluan as $k) {
                if ((int) $k['id'] === $keperluan) {
                    $ringkasan_filter[] = 'Keperluan: ' . $k['label'];
                    break;
                }
            }
        }
        if ($dari !== '' || $sampai !== '') {
            if ($dari !== '' && $sampai !== '' && $dari === $sampai) {
                $ringkasan_filter[] = 'Tanggal: ' . $dari;
            } elseif ($dari !== '' && $sampai !== '') {
                $ringkasan_filter[] = 'Tanggal: ' . $dari . ' s/d ' . $sampai;
            } elseif ($dari !== '') {
                $ringkasan_filter[] = 'Dari: ' . $dari;
            } else {
                $ringkasan_filter[] = 'Sampai: ' . $sampai;
            }
        }

        $data = [
            'title'            => 'Pencatatan COD',
            'catatan'          => $catatan->daftar(($page - 1) * self::PER_HALAMAN, self::PER_HALAMAN, $filter),
            'total'            => $total,
            'total_semua'      => $total_semua,
            'status_counts'    => $status_counts,
            'page'             => $page,
            'halaman'          => $halaman,
            'per_halaman'      => self::PER_HALAMAN,
            'pilihan'          => [
                'keperluan' => $daftar_keperluan,
                'status'    => $daftar_status,
                'cs'        => $daftar_cs,
            ],
            'boleh_hapus'      => $boleh_hapus,
            // menambah dan menghapus status transaksi khusus Superadmin
            'boleh_status_baru' => $this->bolehStatusBaru(),
            'dipakai'          => $boleh_hapus ? $catatan->terpakai() : [],
            'hari_ini'         => $hari_ini,
            'f'                => [
                'cari'      => $cari,
                'status'    => $status,
                'cs'        => $cs,
                'keperluan' => $keperluan,
                'dari'      => $dari,
                'sampai'    => $sampai,
                'periode'   => $periode,
            ],
            'ada_filter'       => $adaFilter,
            'ada_filter_modal' => ! empty($ringkasan_filter),
            'ringkasan_filter' => implode(', ', $ringkasan_filter),
            'errors'           => (array) session('errors'),
            'sukses'           => session('sukses'),
            'gagal'            => session('gagal'),
        ];

        return view('admin/cod/index', $data);
    }

    /** Tambah satu catatan. */
    public function simpan()
    {
        $validation = \Config\Services::validation();
        $validation->setRuleGroup('addCod');

        if (! $validation->withRequest($this->request)->run()) {
            return redirect()->to('/admin/cod')->withInput()->with('errors', $validation->getErrors());
        }

        $tanggal = trim((string) $this->request->getPost('tanggal'));
        if (strtotime($tanggal) > strtotime(date('Y-m-d'))) {
            return redirect()->to('/admin/cod')->withInput()->with('errors', ['tanggal' => 'Tanggal tidak boleh melebihi hari ini.']);
        }

        $isian = $this->isian();

        if ($isian['status_id'] === null) {
            return redirect()->to('/admin/cod')->withInput()->with('errors', ['status' => 'Status transaksi tidak dikenali. Hanya Superadmin yang bisa menambah status baru.']);
        }

        (new PencatatanCodModel())->insert($isian);

        return redirect()->to('/admin/cod')->with('sukses', 'Pencatatan COD berhasil disimpan.');
    }

    /** Perbarui satu catatan yang sudah ada. */
    public function sunting()
    {
        $validation = \Config\Services::validation();
        $validation->setRuleGroup('updateCod');

        if (! $validation->withRequest($this->request)->run()) {
            return redirect()->to('/admin/cod')->withInput()->with('errors', $validation->getErrors());
        }

        $tanggal = trim((string) $this->request->getPost('tanggal'));
        if (strtotime($tanggal) > strtotime(date('Y-m-d'))) {
            return redirect()->to('/admin/cod')->withInput()->with('errors', ['tanggal' => 'Tanggal tidak boleh melebihi hari ini.']);
        }

        $model = new PencatatanCodModel();
        $id    = (int) $this->request->getPost('id_cod');

        if ($model->find($id) === null) {
            return redirect()->to('/admin/cod')->with('gagal', 'Pencatatan tidak ditemukan.');
        }

        $isian = $this->isian();

        if ($isian['status_id'] === null) {
            return redirect()->to('/admin/cod')->withInput()->with('errors', ['status' => 'Status transaksi tidak dikenali. Hanya Superadmin yang bisa menambah status baru.']);
        }

        $model->update($id, $isian);

        return redirect()->to('/admin/cod')->with('sukses', 'Pencatatan COD berhasil diperbarui.');
    }

    /**
     * Hapus lunak satu catatan. Barisnya tetap ada di tabel, jadi salah klik
     * masih bisa diperiksa balik.
     */
    public function hapus()
    {
        $model = new PencatatanCodModel();
        $id    = (int) $this->request->getPost('id_cod');

        if ($model->find($id) === null) {
            return redirect()->to('/admin/cod')->with('gagal', 'Pencatatan tidak ditemukan.');
        }

        $model->delete($id);

        return redirect()->to('/admin/cod')->with('sukses', 'Pencatatan COD berhasil dihapus.');
    }

    /**
     * Hapus satu pilihan master. Admin dan superadmin boleh.
     *
     * Pilihan yang masih dipakai catatan yang TAMPIL ditolak lebih dulu: foreign
     * key kolom pilihannya RESTRICT, jadi tanpa pemeriksaan ini yang muncul di
     * layar pesan galat MySQL, bukan kalimat yang bisa dibaca. Catatan yang sudah
     * dihapus lunak tidak ikut menahan — barisnya dilepas lebih dulu (lepaskan())
     * supaya pilihan yang catatannya sudah dibuang benar-benar bisa hilang.
     *
     * Dari popus pilihan halaman ini dipanggil dengan fetch, jadi jawabannya JSON
     * dan pilihannya hilang di tempat tanpa reload; bentuk redirect tetap ada
     * untuk permintaan biasa.
     */
    public function hapusPilihan()
    {
        $ajax    = $this->request->isAJAX();
        $id      = (int) $this->request->getPost('id');
        $pilihan = new PencatatanPilihanModel();
        $baris   = $id > 0 ? $pilihan->find($id) : null;

        if (! $this->bolehHapus()) {
            return $this->jawaban($ajax, false, $id, 'Hanya admin yang bisa menghapus pilihan.');
        }

        if ($baris === null) {
            return $this->jawaban($ajax, false, $id, 'Pilihan tidak ditemukan.');
        }

        // daftar status dijaga: Admin boleh menghapus pilihan lain, tetapi tidak
        // status transaksi
        if ($baris['jenis'] === 'status' && ! $this->bolehStatusBaru()) {
            return $this->jawaban($ajax, false, $id, 'Hanya Superadmin yang bisa menghapus status transaksi.');
        }

        $catatan = new PencatatanCodModel();
        $dipakai = $catatan->dipakai($id);

        // sumber informasi boleh dilepas meski masih dipakai: foreign key-nya
        // SET NULL, jadi catatannya tetap ada dan hanya baris sumbernya yang kosong
        if ($dipakai['tampil'] > 0 && $baris['jenis'] !== 'sumber') {
            return $this->jawaban($ajax, false, $id,
                'Pilihan "' . $baris['label'] . '" masih dipakai ' . $dipakai['tampil'] . ' catatan, jadi tidak bisa dihapus.');
        }

        $db = \Config\Database::connect();
        $db->transStart();

        $catatan->lepaskan($id);
        $pilihan->delete($id);

        $db->transComplete();

        if ($db->transStatus() === false) {
            return $this->jawaban($ajax, false, $id, 'Pilihan "' . $baris['label'] . '" gagal dihapus.');
        }

        return $this->jawaban($ajax, true, $id,
            'Pilihan "' . $baris['label'] . '" dihapus.'
            . ($dipakai['hapus'] > 0 ? ' ' . $dipakai['hapus'] . ' catatan yang sudah dihapus tidak lagi menunjuknya.' : '')
            . ($dipakai['tampil'] > 0 ? ' ' . $dipakai['tampil'] . ' catatan lama kini tidak punya sumber lagi.' : ''));
    }

    /**
     * Satu titik jawab untuk hapus pilihan: JSON untuk fetch dari popus,
     * redirect + flash untuk kiriman form biasa.
     */
    private function jawaban(bool $ajax, bool $beres, int $id, string $pesan)
    {
        if ($ajax) {
            return $this->response->setJSON(['ok' => $beres, 'id' => $id, 'pesan' => $pesan]);
        }

        return redirect()->to('/admin/cod')->with($beres ? 'sukses' : 'gagal', $pesan);
    }

    // menghapus pilihan master boleh di kedua level yang membuka halaman ini,
    // jadi diperiksa di server, bukan di tombolnya saja
    private function bolehHapus(): bool
    {
        return in_array((string) session()->get('level'), ['admin', 'superadmin'], true);
    }

    /**
     * Status transaksi dipakai bersama oleh seluruh tim dan jadi dasar tab
     * penyaring, jadi isinya hanya boleh ditambah/dibuang oleh Superadmin.
     */
    private function bolehStatusBaru(): bool
    {
        return (string) session()->get('level') === 'superadmin';
    }

    /**
     * Kemas kiriman form menjadi baris tabel.
     *
     * Pilihan yang diketik baru ikut dibuat di tabel master lebih dulu, jadi
     * saran untuk catatan berikutnya sudah tersedia begitu halaman dimuat ulang.
     * Khusus status: label asing hanya dijadikan baris baru kalau yang mengirim
     * Superadmin; selain itu nilainya jadi null dan ditolak pemanggil.
     */
    private function isian(): array
    {
        $pilihan = new PencatatanPilihanModel();
        $status  = (string) $this->request->getPost('status');

        return [
            'tanggal'       => date('Y-m-d', (int) strtotime((string) $this->request->getPost('tanggal'))),
            'jam'           => $this->jam(),
            'nama_customer' => trim((string) $this->request->getPost('nama_customer')),
            'no_hp'         => trim((string) $this->request->getPost('no_hp')),
            'keperluan_id'  => $pilihan->jamin('keperluan', (string) $this->request->getPost('keperluan')),
            'status_id'     => $this->statusId($pilihan, $status),
            'catatan'       => trim((string) $this->request->getPost('catatan')),
            'cs_id'         => $pilihan->jamin('cs', (string) $this->request->getPost('cs')),
            'sumber_id'     => $pilihan->jamin('sumber', (string) $this->request->getPost('sumber')),
            'user_id'       => (int) session()->get('id'),
        ];
    }

    /**
     * Status yang sudah ada selalu diterima; status baru hanya kalau pengirimnya
     * Superadmin. Selain itu null, supaya pemanggil bisa menolak dengan pesan
     * yang jelas alih-alih menabrak kolom NOT NULL di basis data.
     */
    private function statusId(PencatatanPilihanModel $pilihan, string $label): ?int
    {
        $ada = $pilihan->idUntuk('status', $label);

        if ($ada !== null) {
            return $ada;
        }

        return $this->bolehStatusBaru() ? $pilihan->jamin('status', $label) : null;
    }

    /**
     * Jam datang dalam bentuk H:i, atau null kalau tidak diisi.
     *
     * Kolomnya TIME, jadi nilai asing seperti '25:00' tidak boleh lolos meski
     * peramban sudah membatasi pemilinya.
     */
    private function jam(): ?string
    {
        $jam = trim((string) $this->request->getPost('jam'));
        $dicoba = strtotime($jam);

        if ($jam === '' || $dicoba === false) {
            return null;
        }

        return date('H:i:s', $dicoba);
    }
}
