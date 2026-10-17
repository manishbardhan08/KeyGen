<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * A seller plan is identified by duration *and* level, so the same admin can
 * sell e.g. "30 days · Level 1" and "30 days · Level 2" as separate plans.
 *
 * The original UNIQUE key was (admin_id, duration_days), which made a second
 * same-duration plan overwrite the first. We widen it to include level_number.
 */
class PlanUniquePerLevel extends Migration
{
    public function up()
    {
        // only relevant once the level_number column exists
        if (! $this->db->fieldExists('level_number', 'key_plans')) {
            return;
        }

        // drop the old (admin_id, duration_days) unique, if present
        $this->dropIndexIfExists('key_plans', 'admin_id_duration_days');

        // add the wider unique (admin_id, duration_days, level_number), if absent
        if (! $this->indexExists('key_plans', 'admin_duration_level')) {
            $this->db->query(
                'ALTER TABLE `key_plans` ADD UNIQUE KEY `admin_duration_level` (`admin_id`, `duration_days`, `level_number`)'
            );
        }
    }

    public function down()
    {
        $this->dropIndexIfExists('key_plans', 'admin_duration_level');

        if (! $this->indexExists('key_plans', 'admin_id_duration_days')) {
            $this->db->query(
                'ALTER TABLE `key_plans` ADD UNIQUE KEY `admin_id_duration_days` (`admin_id`, `duration_days`)'
            );
        }
    }

    private function indexExists(string $table, string $index): bool
    {
        $rows = $this->db->query("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$index])->getResultArray();
        return $rows !== [];
    }

    private function dropIndexIfExists(string $table, string $index): void
    {
        if ($this->indexExists($table, $index)) {
            $this->db->query("ALTER TABLE `{$table}` DROP INDEX `{$index}`");
        }
    }
}