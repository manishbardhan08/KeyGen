<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Admin-defined license levels.
 *
 * Levels are scoped per admin: admin A's "Level 1" is independent of admin B's.
 * `level_number` is what gets stored on keys/plans; `name` is a human label.
 * There is no "default" level: a key generated without an explicit level simply
 * has no level (0).
 */
class KeyLevelModel extends Model
{
    protected $table         = 'key_levels';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['admin_id', 'level_number', 'name', 'is_admin_only'];
    protected $useTimestamps = true;

    /**
     * Levels for one admin, ordered by number.
     *
     * @return array<int, array<string, mixed>>
     */
    public function forAdmin(int $adminId): array
    {
        return $this->where('admin_id', $adminId)
            ->orderBy('level_number', 'ASC')
            ->findAll();
    }

    /**
     * Map of level_number => name for fast lookup.
     *
     * @return array<int, string>
     */
    public function nameMap(int $adminId): array
    {
        $out = [];
        foreach ($this->forAdmin($adminId) as $row) {
            $out[(int) $row['level_number']] = (string) $row['name'];
        }
        return $out;
    }

    /**
     * True when the admin has defined this level_number.
     */
    public function exists(int $adminId, int $levelNumber): bool
    {
        return $this->where('admin_id', $adminId)
            ->where('level_number', $levelNumber)
            ->first() !== null;
    }

    /**
     * True when this level is flagged admin-only — i.e. only the owning admin
     * may issue keys for it; sellers must never be able to generate it (not via
     * a plan, not via a seller-level fallback).
     */
    public function isAdminOnly(int $adminId, int $levelNumber): bool
    {
        if ($levelNumber <= 0) {
            return false;
        }

        $row = $this->where('admin_id', $adminId)
            ->where('level_number', $levelNumber)
            ->first();

        return $row !== null && (int) ($row['is_admin_only'] ?? 0) === 1;
    }
}
