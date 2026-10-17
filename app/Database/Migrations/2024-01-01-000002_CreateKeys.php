<?php
namespace App\Database\Migrations;
use CodeIgniter\Database\Migration;

class CreateKeys extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'            => ['type'=>'INT','unsigned'=>true,'auto_increment'=>true],
            'key'           => ['type'=>'VARCHAR','constraint'=>64],
            'created_by'    => ['type'=>'INT','unsigned'=>true],
            'duration_days' => ['type'=>'INT','default'=>30],
            'status'        => ['type'=>'VARCHAR','constraint'=>20,'default'=>'unused'],
            'used_by'       => ['type'=>'INT','unsigned'=>true,'null'=>true],
            'used_at'       => ['type'=>'DATETIME','null'=>true],
            'hwid'          => ['type'=>'VARCHAR','constraint'=>128,'null'=>true],
            'expires_at'    => ['type'=>'DATETIME','null'=>true],
            'created_at'    => ['type'=>'DATETIME','null'=>true],
            'updated_at'    => ['type'=>'DATETIME','null'=>true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('key');
        $this->forge->createTable('keys', true);
    }
    public function down(){ $this->forge->dropTable('keys', true); }
}
