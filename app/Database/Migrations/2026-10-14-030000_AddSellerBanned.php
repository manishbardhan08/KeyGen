<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Lets an admin ban / unban a seller (or any non-owner account).
 *
 * A banned account cannot sign in to the panel, cannot generate keys, and its
 * API credentials stop working (new /api/v1/auth calls are rejected and live
 * sessions stop validating). Unbanning restores access.
 */
class AddSellerBanned extends Migration
{
    public function up()
    {
        if (! $this->db->fieldExists('is_banned', 'users')) {
            $this->forge->addColumn('users', [
                'is_banned' => [
                    'type'       => 'TINYINT',
                    'constraint' => 1,
                    'default'    => 0,
                    'after'      => 'role',
                ],
            ]);
        }
    }

    public function down()
    {
        if ($this->db->fieldExists('is_banned', 'users')) {
            $this->forge->dropColumn('users', 'is_banned');
        }
    }
}
