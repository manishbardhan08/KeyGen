<?php
namespace App\Database\Migrations;
use CodeIgniter\Database\Migration;

class AddCreatedByToUsers extends Migration
{
    public function up()
    {
        $this->forge->addColumn('users', [
            'created_by' => [
                'type'       => 'INT',
                'unsigned'   => true,
                'null'       => true,
                'after'      => 'api_key',
            ],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('users', 'created_by');
    }
}
