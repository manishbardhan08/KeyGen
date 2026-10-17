<?php
namespace App\Database\Migrations;
use CodeIgniter\Database\Migration;

class AddCreatorUsernameToKeys extends Migration
{
    public function up()
    {
        if (! $this->db->fieldExists('created_by_username', 'keys')) {
            $this->forge->addColumn('keys', [
                'created_by_username' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 50,
                    'null'       => true,
                    'after'      => 'created_by',
                ],
            ]);
        }
        if (! $this->db->fieldExists('created_by_role', 'keys')) {
            $this->forge->addColumn('keys', [
                'created_by_role' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 20,
                    'null'       => true,
                    'after'      => 'created_by_username',
                ],
            ]);
        }
    }

    public function down()
    {
        $this->forge->dropColumn('keys', ['created_by_username', 'created_by_role']);
    }
}
