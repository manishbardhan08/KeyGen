<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Attach a level to generated keys and let admins gate seller plans by level.
 *
 *   keys.level            – the license level this key grants (0 = none/legacy)
 *   key_plans.level_number – which level a seller buys through this plan (0 = any)
 *
 * Level numbers are admin-defined (see `key_levels`), so we store plain ints and
 * resolve names at read time.
 */
class AddLevelToKeysAndPlans extends Migration
{
    public function up()
    {
        if (! $this->db->fieldExists('level', 'keys')) {
            $this->forge->addColumn('keys', [
                'level' => [
                    'type'       => 'SMALLINT',
                    'unsigned'   => true,
                    'default'    => 0,
                    'after'      => 'duration_days',
                ],
            ]);
            $this->db->query('CREATE INDEX `idx_keys_level` ON `keys` (`level`)');
        }

        if (! $this->db->fieldExists('level_number', 'key_plans')) {
            $this->forge->addColumn('key_plans', [
                'level_number' => [
                    'type'     => 'SMALLINT',
                    'unsigned' => true,
                    'default'  => 0,
                    'after'    => 'duration_days',
                ],
            ]);
        }
    }

    public function down()
    {
        if ($this->db->fieldExists('level', 'keys')) {
            $this->forge->dropColumn('keys', 'level');
        }
        if ($this->db->fieldExists('level_number', 'key_plans')) {
            $this->forge->dropColumn('key_plans', 'level_number');
        }
    }
}