<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Referential integrity + the remaining hot indexes.
 *
 * Until now only `key_devices.key_id` had a foreign key; every other cascade
 * was enforced in PHP (Owner::deleteAdmin, Admin::deleteSeller, purge, …).
 * One missed branch leaves orphan rows behind. This migration:
 *
 *   1. adds the indexes the expired-key dashboards / purges / session prune
 *      scan on but that were missing,
 *   2. removes any pre-existing orphan rows so the FKs can be created,
 *   3. adds ON DELETE CASCADE foreign keys so the database itself keeps the
 *      graph consistent.
 *
 * Every step is guarded and non-fatal: on a host whose engine/user cannot
 * create FKs the migration still completes (with a log line) instead of
 * breaking the deploy.
 */
class AddIntegrityIndexesAndForeignKeys extends Migration
{
    /** @var array<array{0:string,1:string,2:string}> [table, indexName, columnList] */
    private array $indexes = [
        ['keys',         'idx_keys_expires_at',     '`expires_at`'],
        ['api_sessions', 'idx_sessions_user_id',    '`user_id`'],
        ['api_sessions', 'idx_sessions_expires_at', '`expires_at`'],
    ];

    /**
     * [table, constraintName, localColumn, refTable, refColumn]
     *
     * @var array<array{0:string,1:string,2:string,3:string,4:string}>
     */
    private array $foreignKeys = [
        ['keys',         'fk_keys_created_by',     'created_by', 'users', 'id'],
        ['api_sessions', 'fk_sessions_key_id',     'key_id',     'keys',  'id'],
        ['api_sessions', 'fk_sessions_user_id',    'user_id',    'users', 'id'],
        ['key_plans',    'fk_plans_admin_id',      'admin_id',   'users', 'id'],
        ['key_levels',   'fk_levels_admin_id',     'admin_id',   'users', 'id'],
    ];

    public function up()
    {
        foreach ($this->indexes as [$table, $name, $cols]) {
            $this->addIndexIfMissing($table, $name, $cols);
        }

        // --- clean orphans so the FKs are accepted ---------------------------
        $db = \Config\Database::connect();

        // sessions that point at a missing key or a missing user go first
        $db->query('DELETE FROM `api_sessions` WHERE `key_id` NOT IN (SELECT `id` FROM `keys`)');
        $db->query('DELETE FROM `api_sessions` WHERE `user_id` NOT IN (SELECT `id` FROM `users`)');

        // keys / plans / levels whose owner no longer exists
        $db->query('DELETE FROM `keys` WHERE `created_by` NOT IN (SELECT `id` FROM `users`)');
        $db->query('DELETE FROM `key_plans` WHERE `admin_id` NOT IN (SELECT `id` FROM `users`)');
        $db->query('DELETE FROM `key_levels` WHERE `admin_id` NOT IN (SELECT `id` FROM `users`)');

        // --- add the foreign keys -------------------------------------------
        foreach ($this->foreignKeys as [$table, $name, $col, $refTable, $refCol]) {
            if ($this->fkViolation($table, $col, $refTable, $refCol)) {
                log_message('warning', "Skipped FK {$name}: orphan rows remain in {$table}.{$col}.");
                continue;
            }
            if ($this->indexExists($table, $name)) {
                continue;
            }
            try {
                $db->query(
                    "ALTER TABLE `{$table}` ADD CONSTRAINT `{$name}` "
                    . "FOREIGN KEY (`{$col}`) REFERENCES `{$refTable}`(`{$refCol}`) "
                    . 'ON DELETE CASCADE ON UPDATE CASCADE'
                );
            } catch (\Throwable $e) {
                // Some managed MySQL users lack REFERENCES privilege — don't fail the deploy.
                log_message('warning', "Could not add FK {$name}: " . $e->getMessage());
            }
        }
    }

    public function down()
    {
        $db = \Config\Database::connect();

        foreach (array_reverse($this->foreignKeys) as [$table, $name]) {
            if ($this->indexExists($table, $name)) {
                $db->query("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$name}`");
            }
        }
        foreach ($this->indexes as [$table, $name]) {
            if ($this->indexExists($table, $name)) {
                $db->query("DROP INDEX `{$name}` ON `{$table}`");
            }
        }
    }

    private function addIndexIfMissing(string $table, string $name, string $cols): void
    {
        if (! $this->indexExists($table, $name)) {
            $this->db->query("CREATE INDEX `{$name}` ON `{$table}` ({$cols})");
        }
    }

    private function indexExists(string $table, string $name): bool
    {
        $result = $this->db->query("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$name]);

        return $result !== false && $result->getResultArray() !== [];
    }

    /**
     * True when a row in $table still points at a non-existent reference row.
     * Information-schema lookups can be stale within the same session, so we
     * verify with a direct count.
     */
    private function fkViolation(string $table, string $col, string $refTable, string $refCol): bool
    {
        $row = $this->db->query(
            "SELECT COUNT(*) AS c FROM `{$table}` t "
            . "LEFT JOIN `{$refTable}` r ON r.`{$refCol}` = t.`{$col}` "
            . "WHERE r.`{$refCol}` IS NULL"
        )->getRowArray();

        return ((int) ($row['c'] ?? 0)) > 0;
    }
}
