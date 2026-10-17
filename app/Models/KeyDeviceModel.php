<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * One row per device (HWID) a license key has been activated on.
 *
 * A key may have up to keys.device_limit rows. Rows cascade-delete with the key.
 */
class KeyDeviceModel extends Model
{
    protected $table         = 'key_devices';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['key_id', 'hwid'];
    protected $useTimestamps = true;

    /**
     * Number of devices currently bound to a key.
     */
    public function countForKey(int $keyId): int
    {
        return $this->where('key_id', $keyId)->countAllResults();
    }

    /**
     * True when this exact (key, hwid) pair is already bound.
     */
    public function isBound(int $keyId, string $hwid): bool
    {
        return $this->where('key_id', $keyId)->where('hwid', $hwid)->first() !== null;
    }
}
