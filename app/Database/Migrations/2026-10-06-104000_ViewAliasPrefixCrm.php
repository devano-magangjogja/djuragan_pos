<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class ViewAliasPrefixCrm extends Migration
{
    public function up()
    {
        $prefix = $this->db->getPrefix();
        if ($prefix) {
            $this->db->query("CREATE OR REPLACE VIEW `{$prefix}crm_pelanggan` AS SELECT * FROM `crm_pelanggan`");
            $this->db->query("CREATE OR REPLACE VIEW `{$prefix}crm_pelanggan_duplikat` AS SELECT * FROM `crm_pelanggan_duplikat`");
        }
    }

    public function down()
    {
        $prefix = $this->db->getPrefix();
        if ($prefix) {
            $this->db->query("DROP VIEW IF EXISTS `{$prefix}crm_pelanggan`");
            $this->db->query("DROP VIEW IF EXISTS `{$prefix}crm_pelanggan_duplikat`");
        }
    }
}
