<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Per-admin key pricing tiers.
 *
 * Each admin defines the durations they sell and the balance price for each.
 * Sellers only see their creating admin's tiers and pay the fixed price.
 */
class CreateKeyPlans extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'          => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'admin_id'    => ['type' => 'INT', 'unsigned' => true],
            'duration_days' => ['type' => 'INT', 'unsigned' => true],
            'price'       => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'is_active'   => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at'  => ['type' => 'DATETIME', 'null' => true],
            'updated_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('admin_id');
        $this->forge->addUniqueKey(['admin_id', 'duration_days']);
        $this->forge->createTable('key_plans', true);
    }

    public function down()
    {
        $this->forge->dropTable('key_plans', true);
    }
}