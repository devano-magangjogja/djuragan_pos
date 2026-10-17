<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Satu baris = satu pilihan di dropdown Pencatatan COD (keperluan, status,
 * nama CS, atau sumber informasi).
 *
 * Pilihan baru ditambah setiap kali ada label yang belum pernah dipakai, jadi
 * isian lama tetap menunjuk baris yang sama. Yang menghapus pilihan hanya
 * superadmin, dan hanya yang belum dipakai catatan (lihat
 * PencatatanCodModel::dipakai()); pilihan sumber boleh hilang karena foreign
 * key-nya SET NULL.
 */
class PencatatanPilihanModel extends Model
{
    protected $table            = 'pencatatan_pilihan';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $allowedFields    = ['jenis', 'label', 'kunci'];
    protected $useTimestamps    = true;
    protected $createdField     = 'created_at';
    // tabel master pilihan tidak punya kolom updated_at; kosong berarti nilainya
    // tidak pernah ditulis, bukan dilewati begitu saja
    protected $updatedField     = '';
    protected $dateFormat       = 'int';

    /** Daftar label untuk satu kategori, urut kemunculan pertamanya. */
    public function label(string $jenis): array
    {
        return array_column($this->terpilih($jenis), 'label');
    }

    /**
     * Pilihan satu kategori beserta id-nya, untuk dropdown yang perlu tahu
     * baris mana yang dihapus.
     *
     * @return list<array{id: int, label: string}>
     */
    public function terpilih(string $jenis): array
    {
        $baris = $this->select('id, label')->where('jenis', $jenis)->orderBy('id', 'ASC')->findAll();

        return array_map(
            static fn (array $s): array => ['id' => (int) $s['id'], 'label' => (string) $s['label']],
            $baris
        );
    }

    /**
     * Kembalikan id pilihan untuk label ini, membuat barunya bila belum ada.
     *
     * Cocokkan lewat kunci yang sudah dirapatkan dan dikecilkan, jadi 'sakina'
     * dan 'Sakina' tidak menghasilkan dua opsi yang terlihat sama; label yang
     * tersimpan tetap seperti yang diketik pengguna pertama kali.
     */
    public function jamin(string $jenis, string $label): ?int
    {
        $kunci = self::kunci($label);

        if ($kunci === '') {
            return null;
        }

        $ada = $this->select('id')->where('jenis', $jenis)->where('kunci', $kunci)->orderBy('id', 'ASC')->first();

        if ($ada !== null) {
            return (int) $ada['id'];
        }

        return (int) $this->insert([
            'jenis' => $jenis,
            'label' => trim($label),
            'kunci' => $kunci,
        ]);
    }

    public static function kunci(string $label): string
    {
        return trim((string) preg_replace('/\s+/', ' ', mb_strtolower(trim($label))));
    }
}
