<?php

define('FCPATH', __DIR__ . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR);
chdir(FCPATH);

require FCPATH . '../app/Config/Paths.php';
$paths = new Config\Paths();
require $paths->systemDirectory . '/Boot.php';
CodeIgniter\Boot::bootSpark($paths);

$db = Config\Database::connect();

echo "=== PERCAKAPAN ===\n";
$convs = $db->table('crm_chat_percakapan')->get()->getResultArray();
foreach ($convs as $c) {
    echo "ID: {$c['id_percakapan']} | No: {$c['nomor_wa']} | Nama: {$c['nama_kontak']} | Arah: {$c['arah_terakhir']} | Unread: {$c['unread_admin']} | Last: {$c['pesan_terakhir']}\n";
}

echo "\n=== 10 PESAN TERAKHIR ===\n";
$msgs = $db->table('crm_chat_pesan')->orderBy('id_pesan', 'DESC')->get(10)->getResultArray();
foreach ($msgs as $m) {
    echo "ID: {$m['id_pesan']} | Percakapan: {$m['percakapan_id']} | Arah: {$m['arah']} | No: {$m['nomor_wa']} | Pesan: {$m['isi_pesan']}\n";
}
