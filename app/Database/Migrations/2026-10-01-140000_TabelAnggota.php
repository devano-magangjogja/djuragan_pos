<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Anggota order rombongan.
 *
 * Hari ini satu order hanya tahu "berapa stel" lewat order_dibeli.qty; tidak ada
 * tempat untuk menyimpan nama orang per stel. Tabel ini menambah lapisan itu di
 * antara invoice dan item, tanpa mengubah invoice maupun item yang sudah ada.
 *
 * invoice_id lama tidak diubah. Order yang tidak punya baris di sini ya order
 * satuan, dan tampilannya tetap seperti sebelumnya.
 */
class TabelAnggota extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id_anggota' => [
                'type'           => 'INT',
                'constraint'     => 10,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'invoice_id' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
            ],
            'nama' => [
                'type'       => 'VARCHAR',
                'constraint' => 60,
            ],
            'nomor' => [
                'type'       => 'VARCHAR',
                'constraint' => 30,
                'null'       => true,
            ],
            'urutan' => [
                'type'       => 'SMALLINT',
                'constraint' => 5,
                'unsigned'   => true,
                'default'    => 0,
            ],
            'catatan' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],
            'created_at' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'updated_at' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id_anggota');
        $this->forge->addKey(['invoice_id', 'urutan']);
        $this->forge->createTable('anggota', true);

        if (! $this->punyaConstraint('anggota', 'fk_anggota_invoice')) {
            $this->db->query('ALTER TABLE ' . $this->t('anggota') . ' ADD CONSTRAINT fk_anggota_invoice'
                . ' FOREIGN KEY (invoice_id) REFERENCES ' . $this->t('invoice')
                . ' (id_invoice) ON DELETE CASCADE ON UPDATE RESTRICT');
        }
    }

    public function down()
    {
        if ($this->punyaConstraint('anggota', 'fk_anggota_invoice')) {
            $this->db->query('ALTER TABLE ' . $this->t('anggota') . ' DROP FOREIGN KEY fk_anggota_invoice');
        }

        $this->forge->dropTable('anggota', true);
    }

    private function t(string $nama): string
    {
        return $this->db->prefixTable($nama);
    }

    private function punyaConstraint(string $tabel, string $nama): bool
    {
        $q = $this->db->query('SELECT 1 FROM information_schema.table_constraints
                WHERE table_schema = DATABASE() AND table_name = ? AND constraint_name = ? LIMIT 1',
            [$this->t($tabel), $nama]);

        return $q->getNumRows() > 0;
    }
}
