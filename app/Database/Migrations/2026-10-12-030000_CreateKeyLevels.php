<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Admin-defined license levels.
 *
 * Each admin can define as many levels as they want for their own software
 * (e.g. 1 = Basic, 2 = Pro, 3 = Enterprise). A generated key carries the level
 * the admin (or, for sellers, the admin's plan) assigned, and the licensing API
 * returns it so the client software can unlock the right feature set.
 */
class CreateKeyLevels extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'           => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'admin_id'     => ['type' => 'INT', 'unsigned' => true],
            'level_number' => ['type' => 'SMALLINT', 'unsigned' => true],
            'name'         => ['type' => 'VARCHAR', 'constraint' => 60],
            'created_at'   => ['type' => 'DATETIME', 'null' => true],
            'updated_at'   => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('admin_id');
        $this->forge->addUniqueKey(['admin_id', 'level_number']);
        $this->forge->createTable('key_levels', true);
    }

    public function down()
    {
        $this->forge->dropTable('key_levels', true);
    }
}