<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Lets an admin mark ONE of their license levels as the default.
 *
 * When the admin generates a key without choosing a level (level 0), the key
 * is stamped with this default level number instead of "no level", so the
 * client software always receives an admin-defined level to unlock against.
 */
class AddDefaultToKeyLevels extends Migration
{
    public function up()
    {
        if (! $this->db->fieldExists('is_default', 'key_levels')) {
            $this->forge->addColumn('key_levels', [
                'is_default' => [
                    'type'       => 'TINYINT',
                    'constraint' => 1,
                    'default'    => 0,
                    'after'      => 'name',
                ],
            ]);
        }
    }

    public function down()
    {
        if ($this->db->fieldExists('is_default', 'key_levels')) {
            $this->forge->dropColumn('key_levels', 'is_default');
        }
    }
}
