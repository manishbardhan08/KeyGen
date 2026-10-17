<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Per-seller controls decided by the admin at seller-creation time:
 *
 *   users.sell_levels     – CSV of level numbers this seller may sell keys for
 *                           (e.g. "2,3"). Empty = none.
 *   users.can_extend      – may extend a key's expiry
 *   users.can_ban         – may ban/unban a key
 *   users.can_reset_hwid  – may reset a key's HWID
 *   users.can_delete      – may delete a key
 *
 * Existing sellers are backfilled so nothing they could do before is lost:
 * every NON admin-only level of their creating admin, and all four permissions on.
 */
class AddSellerPermissions extends Migration
{
    public function up()
    {
        $fields = [];

        if (! $this->db->fieldExists('sell_levels', 'users')) {
            $fields['sell_levels'] = [
                'type' => 'VARCHAR', 'constraint' => 255, 'null' => true,
            ];
        }
        foreach (['can_extend', 'can_ban', 'can_reset_hwid', 'can_delete'] as $col) {
            if (! $this->db->fieldExists($col, 'users')) {
                $fields[$col] = ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0];
            }
        }
        if ($fields !== []) {
            $this->forge->addColumn('users', $fields);
        }

        // Backfill existing sellers (idempotent-ish: only fills empty values).
        $db = \Config\Database::connect();
        $sellers = $db->table('users')
            ->select('id, level, created_by, sell_levels')
            ->where('role', 'seller')
            ->get()->getResultArray();

        foreach ($sellers as $s) {
            $levels = [];

            $admin = $db->table('users')
                ->select('id')
                ->where('username', $s['created_by'])
                ->where('role', 'admin')
                ->get()->getRowArray();

            if ($admin) {
                $rows = $db->table('key_levels')
                    ->select('level_number')
                    ->where('admin_id', (int) $admin['id'])
                    ->where('is_admin_only', 0)
                    ->get()->getResultArray();
                foreach ($rows as $r) {
                    $levels[] = (int) $r['level_number'];
                }
            }
            // fall back to the old single assigned level if no levels found
            if ($levels === [] && (int) $s['level'] > 0) {
                $levels[] = (int) $s['level'];
            }

            $db->table('users')->where('id', (int) $s['id'])->update([
                'sell_levels'    => implode(',', $levels),
                'can_extend'     => 1,
                'can_ban'        => 1,
                'can_reset_hwid' => 1,
                'can_delete'     => 1,
            ]);
        }
    }

    public function down()
    {
        foreach (['sell_levels', 'can_extend', 'can_ban', 'can_reset_hwid', 'can_delete'] as $col) {
            if ($this->db->fieldExists($col, 'users')) {
                $this->forge->dropColumn('users', $col);
            }
        }
    }
}
