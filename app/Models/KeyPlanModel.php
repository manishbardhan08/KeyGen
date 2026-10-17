<?php
namespace App\Models;
use CodeIgniter\Model;

class KeyPlanModel extends Model
{
    protected $table         = 'key_plans';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['admin_id', 'duration_hours', 'duration_days', 'level_number', 'price', 'is_active'];
    protected $useTimestamps = true;

    /**
     * Active plans for an admin, cheapest duration first.
     *
     * @return array<int, array<string, mixed>>
     */
    public function activeForAdmin(int $adminId): array
    {
        return $this->where('admin_id', $adminId)
            ->where('is_active', 1)
            ->orderBy('duration_hours', 'ASC')
            ->orderBy('level_number', 'ASC')
            ->findAll();
    }

    /**
     * All plans for an admin (including disabled), for the editor screen.
     *
     * @return array<int, array<string, mixed>>
     */
    public function allForAdmin(int $adminId): array
    {
        return $this->where('admin_id', $adminId)
            ->orderBy('duration_hours', 'ASC')
            ->orderBy('level_number', 'ASC')
            ->findAll();
    }
}