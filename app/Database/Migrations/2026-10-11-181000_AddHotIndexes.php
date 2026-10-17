<?php
namespace App\Database\Migrations;
use CodeIgniter\Database\Migration;

/**
 * Adds indexes on the hot lookup/join columns that the panels and the
 * licensing API filter on every request. Without these, key listing,
 * ownership checks and token validation all fall back to full scans.
 */
class AddHotIndexes extends Migration
{
    /** @var array<array{0:string,1:string,2:string}> [table, indexName, columnList] */
    private array $indexes = [
        ['keys',         'idx_keys_created_by',    '`created_by`'],
        ['keys',         'idx_keys_status',        '`status`'],
        ['keys',         'idx_keys_hwid',          '`hwid`'],
        ['users',        'idx_users_created_by',   '`created_by`'],
        ['api_sessions', 'idx_sessions_key_id',    '`key_id`'],
    ];

    public function up()
    {
        foreach ($this->indexes as [$table, $name, $cols]) {
            if (! $this->indexExists($table, $name)) {
                $this->db->query("CREATE INDEX `{$name}` ON `{$table}` ({$cols})");
            }
        }
    }

    public function down()
    {
        foreach ($this->indexes as [$table, $name]) {
            if ($this->indexExists($table, $name)) {
                $this->db->query("DROP INDEX `{$name}` ON `{$table}`");
            }
        }
    }

    private function indexExists(string $table, string $name): bool
    {
        $result = $this->db->query("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$name]);
        return $result !== false && ! empty($result->getResultArray());
    }
}