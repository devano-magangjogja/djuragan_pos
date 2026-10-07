<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class ModulCrm extends Migration
{
    public function up()
    {
        // 1. Tabel crm_setting
        $this->forge->addField([
            'id_setting' => [
                'type'           => 'INT',
                'constraint'     => 10,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'setting_key' => [
                'type'       => 'VARCHAR',
                'constraint' => 64,
            ],
            'setting_value' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'created_at' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
            ],
            'updated_at' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
            ],
        ]);
        $this->forge->addPrimaryKey('id_setting');
        $this->forge->addUniqueKey('setting_key');
        $this->forge->createTable('crm_setting', true);

        // 2. Tabel crm_template
        $this->forge->addField([
            'id_template' => [
                'type'           => 'INT',
                'constraint'     => 10,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'judul' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
            ],
            'kode' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
            ],
            'kategori' => [
                'type'       => 'ENUM',
                'constraint' => ['tagihan', 'pengiriman', 'broadcast', 'umum'],
                'default'    => 'umum',
            ],
            'pesan' => [
                'type' => 'TEXT',
            ],
            'created_at' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
            ],
            'updated_at' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
            ],
        ]);
        $this->forge->addPrimaryKey('id_template');
        $this->forge->addUniqueKey('kode');
        $this->forge->createTable('crm_template', true);

        // 3. Tabel crm_pesan_log
        $this->forge->addField([
            'id_log' => [
                'type'           => 'INT',
                'constraint'     => 10,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'pelanggan_id' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
                'null'       => true,
            ],
            'invoice_id' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
                'null'       => true,
            ],
            'provider' => [
                'type'       => 'VARCHAR',
                'constraint' => 30,
                'default'    => 'kapso',
            ],
            'nomor_tujuan' => [
                'type'       => 'VARCHAR',
                'constraint' => 25,
            ],
            'tipe_pesan' => [
                'type'       => 'ENUM',
                'constraint' => ['tagihan', 'pengiriman', 'broadcast', 'manual'],
                'default'    => 'manual',
            ],
            'isi_pesan' => [
                'type' => 'TEXT',
            ],
            'status' => [
                'type'       => 'ENUM',
                'constraint' => ['terkirim', 'gagal', 'antrean'],
                'default'    => 'terkirim',
            ],
            'response_id' => [
                'type'       => 'VARCHAR',
                'constraint' => 120,
                'null'       => true,
            ],
            'error_message' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'user_id' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
                'null'       => true,
            ],
            'created_at' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
            ],
        ]);
        $this->forge->addPrimaryKey('id_log');
        $this->forge->addKey(['pelanggan_id', 'invoice_id']);
        $this->forge->addKey('created_at');
        $this->forge->createTable('crm_pesan_log', true);

        // 4. Tabel crm_broadcast
        $this->forge->addField([
            'id_broadcast' => [
                'type'           => 'INT',
                'constraint'     => 10,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'judul' => [
                'type'       => 'VARCHAR',
                'constraint' => 150,
            ],
            'segmen' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'default'    => 'semua',
            ],
            'juragan_id' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
                'null'       => true,
            ],
            'pesan' => [
                'type' => 'TEXT',
            ],
            'total_target' => [
                'type'       => 'INT',
                'constraint' => 10,
                'default'    => 0,
            ],
            'total_terkirim' => [
                'type'       => 'INT',
                'constraint' => 10,
                'default'    => 0,
            ],
            'total_gagal' => [
                'type'       => 'INT',
                'constraint' => 10,
                'default'    => 0,
            ],
            'status' => [
                'type'       => 'ENUM',
                'constraint' => ['draft', 'proses', 'selesai'],
                'default'    => 'selesai',
            ],
            'created_by' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
                'null'       => true,
            ],
            'created_at' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
            ],
            'updated_at' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
            ],
        ]);
        $this->forge->addPrimaryKey('id_broadcast');
        $this->forge->createTable('crm_broadcast', true);

        // Isi default data untuk settings & templates
        $this->isiDefaultData();
    }

    public function down()
    {
        $this->forge->dropTable('crm_broadcast', true);
        $this->forge->dropTable('crm_pesan_log', true);
        $this->forge->dropTable('crm_template', true);
        $this->forge->dropTable('crm_setting', true);
    }

    private function isiDefaultData(): void
    {
        $now = time();

        // Default settings. API key sengaja kosong: kredensial hanya boleh masuk
        // lewat Pengaturan CRM, jangan pernah di-seed ke source code.
        $settings = [
            ['setting_key' => 'wa_provider', 'setting_value' => 'kapso', 'created_at' => $now, 'updated_at' => $now],
            ['setting_key' => 'kapso_api_key', 'setting_value' => '', 'created_at' => $now, 'updated_at' => $now],
            ['setting_key' => 'kapso_phone_number_id', 'setting_value' => '597907523413541', 'created_at' => $now, 'updated_at' => $now],
            ['setting_key' => 'kapso_base_url', 'setting_value' => 'https://api.kapso.ai', 'created_at' => $now, 'updated_at' => $now],
            ['setting_key' => 'kapso_sandbox_mode', 'setting_value' => '1', 'created_at' => $now, 'updated_at' => $now],
            ['setting_key' => 'kapso_sandbox_test_number', 'setting_value' => '6285161384750', 'created_at' => $now, 'updated_at' => $now],
            ['setting_key' => 'fonnte_token', 'setting_value' => '', 'created_at' => $now, 'updated_at' => $now],
            ['setting_key' => 'fonnte_base_url', 'setting_value' => 'https://api.fonnte.com', 'created_at' => $now, 'updated_at' => $now],
        ];
        $this->db->table('crm_setting')->insertBatch($settings);

        // Default templates
        $templates = [
            [
                'judul'      => 'Pengingat Tagihan Belum Lunas',
                'kode'       => 'tagihan_belum_lunas',
                'kategori'   => 'tagihan',
                'pesan'      => "Halo kak *{nama}*,\n\nKami menginfokan mengenai pesanan invoice *{invoice}* dengan total *Rp {total}*.\nSaat ini masih terdapat sisa tagihan sebesar *Rp {sisa}*.\n\nPembayaran dapat ditransfer melalui rincian rekening di invoice berikut:\n{link_invoice}\n\nJika sudah melakukan pembayaran, mohon konfirmasi kembali ya kak. Terima kasih!",
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'judul'      => 'Notifikasi Resi Pengiriman',
                'kode'       => 'notifikasi_resi',
                'kategori'   => 'pengiriman',
                'pesan'      => "Halo kak *{nama}*,\n\nKabar baik! Pesanan invoice *{invoice}* telah dikirimkan menggunakan ekspedisi *{kurir}* dengan nomor resi:\n\n🚚 *{resi}*\n\nPaket sedang dalam perjalanan menuju alamat kakak. Terima kasih telah mempercayakan pesanan kepada kami!",
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'judul'      => 'Broadcast Promo & Koleksi Baru',
                'kode'       => 'broadcast_promo',
                'kategori'   => 'broadcast',
                'pesan'      => "Halo kak *{nama}*!\n\nAda promo spesial dan update produk terbaru untuk kakak hari ini. Nikmati penawaran eksklusif khusus untuk pelanggan setia kami.\n\nSilakan balas pesan ini untuk konsultasi pesanan atau katalog terbaru. Terima kasih!",
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'judul'      => 'Follow-up Pelanggan Pasif',
                'kode'       => 'followup_pasif',
                'kategori'   => 'umum',
                'pesan'      => "Halo kak *{nama}*,\n\nSudah lama tidak berjumpa! Semoga kabarnya sehat selalu. Kami punya penawaran spesial jika kakak ingin repeat order produk di toko kami.\n\nKira-kira ada rencana pesanan yang bisa kami bantu dalam waktu dekat? 😊",
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];
        $this->db->table('crm_template')->insertBatch($templates);
    }
}
