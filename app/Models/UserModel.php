<?php
namespace App\Models;
use CodeIgniter\Model;

class UserModel extends Model
{
    protected $table         = 'users';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['name','username','password','role','is_banned','api_key_hash','api_key_display','balance','phone','expires_at','created_by','created_by_role','product','level','software_enabled','offline_message','sell_levels','can_extend','can_ban','can_reset_hwid','can_delete'];
    protected $useTimestamps = true;
}
