<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Jendela kerja CS per tanggal.
 *
 * Isinya sengaja cuma jam: mesin operan hanya perlu tahu kapan kerja seorang CS
 * dianggap selesai untuk hari itu. Absensi, lembur, dan penggajian tetap urusan
 * tempat lain, jadi tabel ini tidak meniru sistem HR.
 */
class ShiftModel extends Model
{
    /** Pola jam 24 jam yang diterima layar. */
    private const JAM = '/^([01]\d|2[0-3]):[0-5]\d$/';

    public function satu(int $user_id, string $tanggal): ?array
    {
        $baris = $this->pangkal()->where('s.user_id', $user_id)->where('s.tanggal', $tanggal)->get(1)->getRowArray();

        return $baris === null ? null : $this->rapikan($baris);
    }

    /** Daftar jendela kerja yang sudah diisi Admin pada satu tanggal. */
    public function padaTanggal(string $tanggal): array
    {
        $baris = $this->pangkal()->where('s.tanggal', $tanggal)
            ->orderBy('u.name', 'ASC')
            ->get()
            ->getResultArray();

        return array_map(fn (array $b): array => $this->rapikan($b), $baris);
    }

    /** Isi jam seorang CS pada satu tanggal; barisnya diperbarui kalau sudah ada. */
    public function simpan(int $user_id, string $tanggal, string $mulai, string $selesai, ?int $juragan_id, ?int $oleh): int
    {
        $waktu = time();
        $data  = [
            'juragan_id'  => $juragan_id,
            'mulai'       => substr(trim($mulai), 0, 5),
            'selesai'     => substr(trim($selesai), 0, 5),
            'updated_at'  => $waktu,
        ];

        $lama = $this->satu($user_id, $tanggal);

        if ($lama !== null) {
            $this->db->table('crm_shift')->where('id_shift', (int) $lama['id_shift'])->update($data);

            return (int) $lama['id_shift'];
        }

        $this->db->table('crm_shift')->insert($data + [
            'user_id'     => $user_id,
            'tanggal'     => $tanggal,
            'dibuat_oleh' => $oleh,
            'created_at'  => $waktu,
        ]);

        return (int) $this->db->insertID();
    }

    /**
     * Titik berakhirnya sebuah shift. selesai <= mulai dibaca sebagai shift malam,
     * jadi batasnya jatuh ke hari berikutnya.
     */
    public function akhir(array $shift): int
    {
        $base = strtotime((string) $shift['tanggal'] . ' ' . (string) $shift['selesai']);

        if ((string) $shift['selesai'] <= (string) $shift['mulai']) {
            $base = strtotime('+1 day', (int) $base);
        }

        return (int) $base;
    }

    /**
     * Semua jendela kerja yang mungkin sudah lewat sampai detik ini.
     *
     * Hanya tanggal hari ini dan kemarin yang dibaca: shift kemarin yang melewati
     * tengah malam baru berakhir pagi ini, dan yang lebih tua dari itu sudah
     * diproses pada waktunya. Daftarnya kecil, jadi batas waktunya dihitung di PHP
     * daripada menulis aritmetika jam di SQL.
     *
     * @return list<array<string, mixed>>
     */
    public function sudahBerakhir(?int $sampai = null): array
    {
        $sekarang = $sampai ?? time();
        $rentang  = [date('Y-m-d', $sekarang - 86400), date('Y-m-d', $sekarang)];
        $lewat    = [];

        foreach ($this->pangkal()->whereIn('s.tanggal', $rentang)->get()->getResultArray() as $s) {
            if ($this->akhir($s) <= $sekarang) {
                $lewat[] = $s;
            }
        }

        return $lewat;
    }

    /**
     * Hapus jendela kerja seseorang pada satu tanggal. Dipanggil saat Admin
     * mengosongkan kedua kolom jam — artinya orang itu tidak bertugas hari itu.
     */
    public function hapus(int $user_id, string $tanggal): int
    {
        $this->db->table('crm_shift')->where('user_id', $user_id)->where('tanggal', $tanggal)->delete();

        return (int) $this->db->affectedRows();
    }

    /** Jam harus HH:MM; selain itu dianggap salah input, bukan diperbaiki diam-diam. */
    public static function jamSah(mixed $nilai): bool
    {
        return (bool) preg_match(self::JAM, trim((string) $nilai));
    }

    public function tanggalSah(mixed $nilai): bool
    {
        $bersih = trim((string) $nilai);

        return (bool) preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $bersih, $m)
            && checkdate((int) $m[2], (int) $m[3], (int) $m[1]);
    }

    /**
     * MySQL mengembalikan kolom TIME sebagai HH:MM:SS, sedangkan input jam di
     * layar (dan perbandingan shift malam) butuh HH:MM.
     *
     * @param array<string, mixed> $baris
     * @return array<string, mixed>
     */
    private function rapikan(array $baris): array
    {
        foreach (['mulai', 'selesai'] as $k) {
            if (isset($baris[$k])) {
                $baris[$k] = substr((string) $baris[$k], 0, 5);
            }
        }

        return $baris;
    }

    private function pangkal()
    {
        return $this->db->table('crm_shift s')
            ->select('s.*, u.name AS nama_staf, u.level, j.nama_juragan')
            ->join('user u', 'u.id = s.user_id')
            ->join('juragan j', 'j.id_juragan = s.juragan_id', 'left');
    }
}
