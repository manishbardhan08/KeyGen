<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Lets an admin define a level for each seller they create.
 *
 *   users.level - the license level assigned to a seller (0 = none)
 *
 * When a seller's key is generated without a level from its plan, the seller's
 * assigned level is stamped on the key. Mirrors how an admin's own keys fall
 * back to the admin's default level.
 */
class AddLevelToUsers extends Migration
{
    public function up()
    {
        if (! $this->db->fieldExists('level', 'users')) {
            $this->forge->addColumn('users', [
                'level' => [
                    'type'       => 'SMALLINT',
                    'unsigned'   => true,
                    'default'    => 0,
                    'after'      => 'product',
                ],
            ]);
        }
    }

    public function down()
    {
        if ($this->db->fieldExists('level', 'users')) {
            $this->forge->dropColumn('users', 'level');
        }
    }
}
