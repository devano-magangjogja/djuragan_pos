<?php
chdir('D:/Magang/Coding/djuragan_pos');
require 'vendor/codeigniter4/framework/system/Test/bootstrap.php';
(new CodeIgniter\Config\DotEnv('D:/Magang/Coding/djuragan_pos'))->load();
$c = config('Config\Database'); $c->tests = (array) $c->default; $c->tests['hostname'] = '127.0.0.1';
$db = \Config\Database::connect('tests');
$r = $db->query('SELECT id_invoice, seri, keterangan FROM order_invoice ORDER BY id_invoice DESC LIMIT 5')->getResult();
foreach ($r as $x) {
  $n = (int) $db->query('SELECT COUNT(*) j FROM order_invoice_foto WHERE invoice_id = ' . (int) $x->id_invoice)->getRow()->j;
  echo $x->id_invoice, ' | ', $x->seri, ' | foto=', $n, ' | ', substr((string) $x->keterangan, 0, 40), "\n";
}
echo "total baris foto: ", $db->query('SELECT COUNT(*) j FROM order_invoice_foto')->getRow()->j, "\n";
