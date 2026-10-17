<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Switches key/plan duration from DAYS to HOURS.
 *
 *   keys.duration_hours       – canonical key lifetime, in hours
 *   key_plans.duration_hours  – canonical plan duration, in hours
 *
 * The old `duration_days` columns are kept (back-filled / derived) so nothing
 * that still reads them breaks, but all logic now uses hours. The plan
 * uniqueness moves from (admin_id, duration_days, level) to
 * (admin_id, duration_hours, level) so e.g. a 12h and a 24h plan can coexist.
 */
class DurationInHours extends Migration
{
    public function up()
    {
        // ---- keys.duration_hours ----
        if (! $this->db->fieldExists('duration_hours', 'keys')) {
            $this->forge->addColumn('keys', [
                'duration_hours' => [
                    'type'     => 'INT',
                    'unsigned' => true,
                    'null'     => false,
                    'default'  => 720, // 30 days (legacy default)
                ],
            ]);
        }
        $this->db->query('UPDATE `keys` SET duration_hours = GREATEST(duration_days, 0) * 24 WHERE duration_hours = 720 AND duration_days IS NOT NULL');

        // ---- key_plans.duration_hours ----
        if (! $this->db->fieldExists('duration_hours', 'key_plans')) {
            $this->forge->addColumn('key_plans', [
                'duration_hours' => [
                    'type'     => 'INT',
                    'unsigned' => true,
                    'null'     => false,
                    'default'  => 0,
                ],
            ]);
        }
        $this->db->query('UPDATE `key_plans` SET duration_hours = GREATEST(duration_days, 0) * 24 WHERE duration_hours = 0');

        // ---- plan uniqueness now keyed on hours ----
        $this->dropIndexIfExists('key_plans', 'admin_duration_level');
        $this->dropIndexIfExists('key_plans', 'admin_id_duration_days');
        if (! $this->indexExists('key_plans', 'admin_hours_level')) {
            $this->db->query(
                'ALTER TABLE `key_plans` ADD UNIQUE KEY `admin_hours_level` (`admin_id`, `duration_hours`, `level_number`)'
            );
        }
    }

    public function down()
    {
        $this->dropIndexIfExists('key_plans', 'admin_hours_level');
        if (! $this->indexExists('key_plans', 'admin_duration_level')) {
            $this->db->query(
                'ALTER TABLE `key_plans` ADD UNIQUE KEY `admin_duration_level` (`admin_id`, `duration_days`, `level_number`)'
            );
        }
        if ($this->db->fieldExists('duration_hours', 'keys')) {
            $this->forge->dropColumn('keys', 'duration_hours');
        }
        if ($this->db->fieldExists('duration_hours', 'key_plans')) {
            $this->forge->dropColumn('key_plans', 'duration_hours');
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
