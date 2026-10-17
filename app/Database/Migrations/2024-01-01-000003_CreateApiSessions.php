<?php
namespace App\Database\Migrations;
use CodeIgniter\Database\Migration;

class CreateApiSessions extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'         => ['type'=>'INT','unsigned'=>true,'auto_increment'=>true],
            'token'      => ['type'=>'VARCHAR','constraint'=>128],
            'user_id'    => ['type'=>'INT','unsigned'=>true],
            'key_id'     => ['type'=>'INT','unsigned'=>true],
            'hwid'       => ['type'=>'VARCHAR','constraint'=>128],
            'ip'         => ['type'=>'VARCHAR','constraint'=>64,'null'=>true],
            'expires_at' => ['type'=>'DATETIME'],
            'created_at' => ['type'=>'DATETIME','null'=>true],
            'updated_at' => ['type'=>'DATETIME','null'=>true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('token');
        $this->forge->createTable('api_sessions', true);
    }
    public function down(){ $this->forge->dropTable('api_sessions', true); }
}
