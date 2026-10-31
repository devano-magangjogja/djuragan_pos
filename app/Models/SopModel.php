<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Pustaka SOP: aturan tertulis yang dibaca CS sebelum mengerjakan tugas.
 *
 * Isinya sengaja satu tabel saja (bukan per halaman) dan tidak pernah dihapus —
 * dokumen yang sudah dipakai orang tidak boleh hilang diam-diam, jadi cukup
 * dimatikan lewat `aktif`.
 */
class SopModel extends Model
{
    public const UNTUK = [
        'semua' => 'Semua peran',
        'cs'    => 'Khusus CS',
        'admin' => 'Khusus Admin',
    ];

    /** @return list<array<string, mixed>> */
    public function daftar(array $s = []): array
    {
        $b = $this->db->table('crm_sop s')
            ->select('s.*, u.name AS nama_diubah')
            ->join('user u', 'u.id = s.diubah_oleh', 'left');

        if (! empty($s['aktif'])) {
            $b->where('s.aktif', 1);
        }

        if (! empty($s['untuk'])) {
            $b->groupStart()->where('s.untuk', $s['untuk'])->orWhere('s.untuk', 'semua')->groupEnd();
        }

        if (! empty($s['kategori'])) {
            $b->where('s.kategori', $s['kategori']);
        }

        if (! empty($s['cari'])) {
            $c = (string) $s['cari'];
            $b->groupStart()->like('s.judul', $c, 'both')->orLike('s.isi', $c, 'both')->groupEnd();
        }

        return $b->orderBy('s.aktif', 'DESC')
            ->orderBy('s.urut', 'ASC')
            ->orderBy('s.id_sop', 'ASC')
            ->get()
            ->getResultArray();
    }

    public function jumlah(array $s = []): int
    {
        $b = $this->db->table('crm_sop');

        if (! empty($s['aktif'])) {
            $b->where('aktif', 1);
        }

        if (! empty($s['untuk'])) {
            $b->groupStart()->where('untuk', $s['untuk'])->orWhere('untuk', 'semua')->groupEnd();
        }

        return (int) $b->countAllResults();
    }

    public function satu(int $id_sop): ?array
    {
        $baris = $this->db->table('crm_sop s')
            ->select('s.*, u.name AS nama_diubah')
            ->join('user u', 'u.id = s.diubah_oleh', 'left')
            ->where('s.id_sop', $id_sop)
            ->get(1)->getRowArray();

        return $baris ?: null;
    }

    /** Daftar kategori yang sudah pernah dipakai, untuk isi dropdown. */
    public function kategori(): array
    {
        $nilai = array_column($this->db->table('crm_sop')->distinct()->select('kategori')->get()->getResultArray(), 'kategori');

        return array_values(array_unique(array_filter(array_map('strval', $nilai))));
    }

    /** @param array<string, mixed> $data */
    public function simpan(array $data, ?int $oleh, int $id_sop = 0): int
    {
        $waktu = time();

        $isi = [
            'judul'      => mb_substr(trim((string) $data['judul']), 0, 200),
            'isi'        => trim((string) ($data['isi'] ?? '')) === '' ? null : mb_substr((string) $data['isi'], 0, 20000),
            'kategori'   => mb_substr(trim((string) ($data['kategori'] ?? 'SOP')), 0, 40) ?: 'SOP',
            'untuk'      => array_key_exists((string) ($data['untuk'] ?? 'semua'), self::UNTUK) ? (string) $data['untuk'] : 'semua',
            'urut'       => max(0, min(9999, (int) ($data['urut'] ?? 0))),
            'aktif'      => ((int) ($data['aktif'] ?? 1)) === 1 ? 1 : 0,
            'diubah_oleh' => $oleh,
            'updated_at' => $waktu,
        ];

        if ($id_sop > 0) {
            $this->db->table('crm_sop')->where('id_sop', $id_sop)->update($isi);

            return $id_sop;
        }

        $this->db->table('crm_sop')->insert($isi + [
            'dibuat_oleh' => $oleh,
            'created_at'  => $waktu,
        ]);

        return (int) $this->db->insertID();
    }

    public function setAktif(int $id_sop, bool $aktif): bool
    {
        $this->db->table('crm_sop')->where('id_sop', $id_sop)->update([
            'aktif' => $aktif ? 1 : 0,
            'updated_at' => time(),
        ]);

        return true;
    }
}
