<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * key_devices was created without `updated_at`, but KeyDeviceModel runs with
 * $useTimestamps = true, so every insert tried to write `updated_at` and
 * failed with "Unknown column 'updated_at'". Add the missing column.
 */
class AddUpdatedAtToKeyDevices extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('key_devices')
            && ! $this->db->fieldExists('updated_at', 'key_devices')) {
            $this->forge->addColumn('key_devices', [
                'updated_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
        }
    }

    public function down()
    {
        if ($this->db->tableExists('key_devices')
            && $this->db->fieldExists('updated_at', 'key_devices')) {
            $this->forge->dropColumn('key_devices', 'updated_at');
        }
    }
}
