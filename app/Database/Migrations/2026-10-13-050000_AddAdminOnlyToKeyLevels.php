<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Lets an admin flag one of their license levels as "admin-only".
 *
 * An admin-only level can be stamped on keys the ADMIN generates directly, but
 * it must never be issued through a seller: sellers cannot buy it via a plan,
 * it cannot be assigned to a seller account, and the seller key-generation
 * endpoint rejects it. This keeps a privileged level (e.g. "Admin / full
 * access") out of the reseller network entirely.
 */
class AddAdminOnlyToKeyLevels extends Migration
{
    public function up()
    {
        if (! $this->db->fieldExists('is_admin_only', 'key_levels')) {
            $this->forge->addColumn('key_levels', [
                'is_admin_only' => [
                    'type'       => 'TINYINT',
                    'constraint' => 1,
                    'default'    => 0,
                    'after'      => 'is_default',
                ],
            ]);
        }
    }

    public function down()
    {
        if ($this->db->fieldExists('is_admin_only', 'key_levels')) {
            $this->forge->dropColumn('key_levels', 'is_admin_only');
        }
    }
}
