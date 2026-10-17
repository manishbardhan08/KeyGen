<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Removes the "default level" concept entirely.
 *
 * Previously one level per admin could be flagged `is_default`, and a key
 * generated without an explicit level was silently stamped with it. That was
 * confusing (deleting the default auto-promoted another, keys changed level
 * unexpectedly). Now: no level is ever "default", and a key made without a
 * level simply has no level (0).
 */
class DropDefaultFromKeyLevels extends Migration
{
    public function up()
    {
        if ($this->db->fieldExists('is_default', 'key_levels')) {
            $this->forge->dropColumn('key_levels', 'is_default');
        }
    }

    public function down()
    {
        if (! $this->db->fieldExists('is_default', 'key_levels')) {
            $this->forge->addColumn('key_levels', [
                'is_default' => [
                    'type'       => 'TINYINT',
                    'constraint' => 1,
                    'default'    => 0,
                ],
            ]);
        }
    }
}
