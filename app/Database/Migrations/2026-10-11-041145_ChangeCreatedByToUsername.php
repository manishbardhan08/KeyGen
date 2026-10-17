<?php
namespace App\Database\Migrations;
use CodeIgniter\Database\Migration;

class ChangeCreatedByToUsername extends Migration
{
    public function up()
    {
        // 1. widen to VARCHAR
        $this->forge->modifyColumn('users', [
            'created_by' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'null'       => true,
                'after'      => 'api_key',
            ],
        ]);

        // 2. backfill: replace numeric id with that user's username
        $db = \Config\Database::connect();
        $db->query("
            UPDATE users u
            LEFT JOIN users p ON p.id = CAST(u.created_by AS UNSIGNED)
            SET u.created_by = p.username
            WHERE u.role = 'seller'
              AND u.created_by IS NOT NULL
              AND u.created_by REGEXP '^[0-9]+$'
              AND p.id IS NOT NULL
        ");
    }

    public function down()
    {
        $this->forge->modifyColumn('users', [
            'created_by' => [
                'type'     => 'INT',
                'unsigned' => true,
                'null'     => true,
            ],
        ]);
    }
}
