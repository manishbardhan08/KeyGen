<?php
namespace App\Database\Migrations;
use CodeIgniter\Database\Migration;

class AddCreatedByRole extends Migration
{
    public function up()
    {
        $this->forge->addColumn('users', [
            'created_by_role' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'null'       => true,
                'after'      => 'created_by',
            ],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('users', 'created_by_role');
    }
}
