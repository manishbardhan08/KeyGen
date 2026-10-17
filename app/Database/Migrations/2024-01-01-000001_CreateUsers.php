<?php
namespace App\Database\Migrations;
use CodeIgniter\Database\Migration;

class CreateUsers extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'         => ['type'=>'INT','unsigned'=>true,'auto_increment'=>true],
            'name'       => ['type'=>'VARCHAR','constraint'=>100],
            'username'   => ['type'=>'VARCHAR','constraint'=>50],
            'password'   => ['type'=>'VARCHAR','constraint'=>255],
            'role'       => ['type'=>'VARCHAR','constraint'=>20,'default'=>'seller'],
            'api_key'    => ['type'=>'VARCHAR','constraint'=>64,'null'=>true],
            'balance'    => ['type'=>'INT','default'=>0],
            'phone'      => ['type'=>'VARCHAR','constraint'=>20,'null'=>true],
            'expires_at' => ['type'=>'DATETIME','null'=>true],
            'created_at' => ['type'=>'DATETIME','null'=>true],
            'updated_at' => ['type'=>'DATETIME','null'=>true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('username');
        $this->forge->createTable('users', true);
    }
    public function down(){ $this->forge->dropTable('users', true); }
}
