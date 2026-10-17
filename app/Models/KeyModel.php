<?php
namespace App\Models;
use CodeIgniter\Model;

class KeyModel extends Model
{
    protected $table         = 'keys';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['key','created_by','created_by_username','created_by_role','duration_days','duration_hours','level','status','used_by','used_at','hwid','expires_at','product','device_limit'];
    protected $useTimestamps = true;
}
