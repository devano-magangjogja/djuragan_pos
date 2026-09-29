<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Fase 1 normalisasi: index sekunder + foreign key.
 * Tidak ada baris data yang diubah atau dihapus.
 */
class AddIndexDanForeignKey extends Migration
{
    public function up()
    {
        $this->lebarkanAsalOrder();

        foreach ($this->daftarIndex() as $baris) {
            [$tabel, $nama, $kolom, $unik] = $baris;

            if ($this->punyaIndex($tabel, $nama)) {
                continue;
            }

            $jenis = $unik ? 'UNIQUE INDEX' : 'INDEX';
            $this->db->query('CREATE ' . $jenis . ' ' . $nama . ' ON ' . $this->table($tabel) . ' (' . $kolom . ')');
        }

        foreach ($this->daftarForeignKey() as $baris) {
            [$nama, $anak, $kolomAnak, $induk, $kolomInduk] = $baris;

            if ($this->punyaConstraint($anak, $nama)) {
                continue;
            }

            $this->db->query('ALTER TABLE ' . $this->table($anak) . ' ADD CONSTRAINT ' . $nama
                . ' FOREIGN KEY (' . $kolomAnak . ') REFERENCES ' . $this->table($induk)
                . ' (' . $kolomInduk . ') ON DELETE RESTRICT ON UPDATE RESTRICT');
        }
    }

    public function down()
    {
        foreach (array_reverse($this->daftarForeignKey()) as $baris) {
            [$nama, $anak] = $baris;

            if ($this->punyaConstraint($anak, $nama)) {
                $this->db->query('ALTER TABLE ' . $this->table($anak) . ' DROP FOREIGN KEY ' . $nama);
            }
        }

        foreach (array_reverse($this->daftarIndex()) as $baris) {
            [$tabel, $nama] = $baris;

            if ($this->punyaIndex($tabel, $nama)) {
                $this->db->query('DROP INDEX ' . $nama . ' ON ' . $this->table($tabel));
            }
        }

        $this->kecilkanAsalOrder();
    }

    private function daftarIndex(): array
    {
        return [
            ['invoice', 'idx_invoice_seri', '`seri`', false],
            ['invoice', 'idx_invoice_juragan_tanggal', '`juragan_id`, `tanggal_pesan`', false],
            ['invoice', 'idx_invoice_user', '`user_id`', false],
            ['invoice', 'idx_invoice_pemesan', '`pemesan_id`', false],
            ['invoice', 'idx_invoice_kirimke', '`kirimKepada_id`', false],
            ['invoice', 'idx_invoice_status', '`status_pesanan`, `status_pengiriman`', false],
            ['invoice', 'idx_invoice_bayar', '`status_pembayaran`', false],
            ['invoice', 'idx_invoice_tanggal', '`tanggal_pesan`', false],
            ['invoice', 'idx_invoice_deleted', '`deleted_at`', false],
            ['dibeli', 'idx_dibeli_invoice', '`invoice_id`', false],
            ['dibeli', 'idx_dibeli_kode', '`kode`', false],
            ['label_invoice', 'idx_label_invoice', '`invoice_id`', false],
            ['label_invoice', 'idx_label_source', '`source_id`', false],
            ['biaya', 'idx_biaya_invoice', '`invoice_id`', false],
            ['pembayaran', 'idx_bayar_invoice', '`invoice_id`', false],
            ['pembayaran', 'idx_bayar_tanggal', '`tanggal_pembayaran`', false],
            ['pembayaran', 'idx_bayar_status', '`status`', false],
            ['pengiriman', 'idx_kirim_invoice', '`invoice_id`', false],
            ['pengiriman', 'idx_kirim_resi', '`resi`', false],
            ['pengiriman', 'idx_kirim_tanggal', '`tanggal_kirim`', false],
            ['invoice_status', 'idx_status_invoice', '`invoice_id`', false],
            ['invoice_status', 'idx_status_status', '`status`', false],
            ['notifikasi', 'idx_notif_juragan_for', '`juragan_id`, `for`', false],
            ['notifikasi', 'idx_notif_for_read', '`for`, `read_at`', false],
            ['notifikasi', 'idx_notif_invoice', '`invoice_id`', false],
            ['notifikasi', 'idx_notif_created', '`created_at`', false],
            ['relasi', 'idx_relasi_juragan', '`table`, `juragan_id`, `val_id`', false],
            ['relasi', 'idx_relasi_val', '`table`, `val_id`', false],
            ['pelanggan', 'idx_pelanggan_nama', '`nama_pelanggan`', false],
            ['pelanggan', 'idx_pelanggan_provinsi', '`provinsi`', false],
            ['pelanggan', 'idx_pelanggan_kabupaten', '`kabupaten`', false],
            ['pelanggan', 'idx_pelanggan_kecamatan', '`kecamatan`', false],
            ['pelanggan', 'idx_pelanggan_deleted', '`deleted_at`', false],
            ['user', 'uk_user_username', '`username`', true],
            ['user', 'idx_user_level_status', '`level`, `status`', false],
            ['juragan', 'uk_juragan_slug', '`juragan`', true],
            ['cities', 'idx_cities_province', '`province_id`', false],
            ['subdistricts', 'idx_subdistricts_city', '`city_id`', false],
        ];
    }

    private function daftarForeignKey(): array
    {
        return [
            ['fk_dibeli_invoice', 'dibeli', '`invoice_id`', 'invoice', '`id_invoice`'],
            ['fk_label_invoice', 'label_invoice', '`invoice_id`', 'invoice', '`id_invoice`'],
            ['fk_biaya_invoice', 'biaya', '`invoice_id`', 'invoice', '`id_invoice`'],
            ['fk_pembayaran_invoice', 'pembayaran', '`invoice_id`', 'invoice', '`id_invoice`'],
            ['fk_pengiriman_invoice', 'pengiriman', '`invoice_id`', 'invoice', '`id_invoice`'],
            ['fk_status_invoice', 'invoice_status', '`invoice_id`', 'invoice', '`id_invoice`'],
            ['fk_label_source', 'label_invoice', '`source_id`', 'asal_order', '`id`'],
            ['fk_invoice_juragan', 'invoice', '`juragan_id`', 'juragan', '`id_juragan`'],
            ['fk_invoice_pemesan', 'invoice', '`pemesan_id`', 'pelanggan', '`id_pelanggan`'],
            ['fk_invoice_kirimke', 'invoice', '`kirimKepada_id`', 'pelanggan', '`id_pelanggan`'],
            ['fk_invoice_user', 'invoice', '`user_id`', 'user', '`id`'],
            ['fk_pelanggan_provinsi', 'pelanggan', '`provinsi`', 'provinces', '`province_id`'],
            ['fk_pelanggan_kabupaten', 'pelanggan', '`kabupaten`', 'cities', '`city_id`'],
            ['fk_pelanggan_kecamatan', 'pelanggan', '`kecamatan`', 'subdistricts', '`subdistrict_id`'],
            ['fk_cities_province', 'cities', '`province_id`', 'provinces', '`province_id`'],
            ['fk_subdistricts_city', 'subdistricts', '`city_id`', 'cities', '`city_id`'],
        ];
    }

    private function table(string $tabel): string
    {
        return $this->db->escapeIdentifiers($this->db->prefixTable($tabel));
    }

    private function punyaIndex(string $tabel, string $nama): bool
    {
        $q = $this->db->query(
            'SELECT 1 FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ? LIMIT 1',
            [$this->db->prefixTable($tabel), $nama]
        );

        return $q->getNumRows() > 0;
    }

    private function punyaConstraint(string $tabel, string $nama): bool
    {
        $q = $this->db->query(
            'SELECT 1 FROM information_schema.table_constraints WHERE table_schema = DATABASE() AND table_name = ? AND constraint_name = ? LIMIT 1',
            [$this->db->prefixTable($tabel), $nama]
        );

        return $q->getNumRows() > 0;
    }

    private function lebarkanAsalOrder(): void
    {
        $q = $this->db->query(
            'SELECT column_type AS tipe FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?',
            [$this->db->prefixTable('asal_order'), 'id']
        )->getRow();

        if ($q !== null && strtolower((string) $q->tipe) !== 'int unsigned') {
            $this->db->query('ALTER TABLE ' . $this->table('asal_order') . ' MODIFY `id` INT UNSIGNED NOT NULL AUTO_INCREMENT');
        }
    }

    private function kecilkanAsalOrder(): void
    {
        $row  = $this->db->query('SELECT MAX(`id`) m FROM ' . $this->table('asal_order'))->getRow();
        $maks = $row === null ? 0 : (int) $row->m;

        if ($maks <= 65535) {
            $this->db->query('ALTER TABLE ' . $this->table('asal_order') . ' MODIFY `id` SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT');
        }
    }
}
