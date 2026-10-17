<?php
namespace App\Models;
use CodeIgniter\Model;

class ApiSessionModel extends Model
{
    protected $table         = 'api_sessions';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['token','user_id','key_id','governing_admin_id','hwid','ip','expires_at'];
    protected $useTimestamps = true;
}
