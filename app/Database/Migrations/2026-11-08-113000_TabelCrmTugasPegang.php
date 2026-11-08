<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Penanggung jawab sebuah catatan cek email.
 *
 * `crm_tugas.dikerjakan_oleh` cuma menampung satu orang, sedangkan satu catatan
 * kotak masuk boleh menjadi tanggung jawab beberapa CS sekaligus di toko yang
 * sama. Tabel ini menjawab "catatan ini ditugaskan ke siapa saja" tanpa mengubah
 * arti `dikerjakan_oleh`, yang tetap berarti "siapa yang sedang mengerjakan".
 *
 * Sifatnya menambah, bukan mengganti: penerima catatan tetap semua orang yang
 * memegang alamat emailnya (crm_email_pegang), jadi tidak ada orang yang
 * tiba-tiba kehilangan tugas yang selama ini ia lihat.
 *
 * Tidak ada foreign key, sama seperti tabel tautan lain di aplikasi ini; yang
 * menulis barisnya hanya TugasModel::tunjuk(), dan tugas yang dihapus tidak
 * menyisakan apa pun karena kunci uniknya (tugas_id, user_id).
 */
class TabelCrmTugasPegang extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id_pegang' => [
                'type'           => 'INT',
                'constraint'     => 10,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'tugas_id' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
            ],
            'user_id' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
            ],
            'dibuat_oleh' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
                'null'       => true,
            ],
            'created_at' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
                'null'       => true,
            ],
        ]);
        $this->forge->addPrimaryKey('id_pegang');
        $this->forge->addUniqueKey(['tugas_id', 'user_id']);
        $this->forge->addKey('user_id');
        $this->forge->createTable('crm_tugas_pegang', true);
    }

    public function down()
    {
        $this->forge->dropTable('crm_tugas_pegang', true);
    }
}
