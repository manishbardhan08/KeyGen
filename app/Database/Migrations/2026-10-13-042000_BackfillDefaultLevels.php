<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Gives every admin that already has levels a default one (their
 * lowest-numbered level), so "No level" keys immediately inherit a level
 * without the admin having to click "set default" first.
 */
class BackfillDefaultLevels extends Migration
{
    public function up()
    {
        if (! $this->db->fieldExists('is_default', 'key_levels')) {
            return;
        }

        $db = \Config\Database::connect();

        $adminIds = $db->table('key_levels')
            ->select('admin_id')
            ->groupBy('admin_id')
            ->get()->getResultArray();

        foreach ($adminIds as $row) {
            $adminId = (int) $row['admin_id'];

            $hasDefault = $db->table('key_levels')
                ->where('admin_id', $adminId)
                ->where('is_default', 1)
                ->countAllResults();
            if ($hasDefault > 0) {
                continue;
            }

            $first = $db->table('key_levels')
                ->where('admin_id', $adminId)
                ->orderBy('level_number', 'ASC')
                ->get(1)->getRowArray();

            if ($first) {
                $db->table('key_levels')->where('id', $first['id'])->update(['is_default' => 1]);
            }
        }
    }

    public function down()
    {
        // non-destructive backfill — nothing to undo
    }
}
