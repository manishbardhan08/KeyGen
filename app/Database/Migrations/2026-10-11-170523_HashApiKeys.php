<?php
namespace App\Database\Migrations;
use CodeIgniter\Database\Migration;

class HashApiKeys extends Migration
{
    public function up()
    {
        // add hashed column (idempotent)
        if (! $this->db->fieldExists('api_key_hash', 'users')) {
            $this->forge->addColumn('users', [
                'api_key_hash' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 128,
                    'null'       => true,
                    'after'      => 'role',
                ],
            ]);
        }

        // backfill: hash all existing plaintext api_keys (only if the old column exists)
        $db = \Config\Database::connect();
        if ($this->db->fieldExists('api_key', 'users')) {
            $rows = $db->query("SELECT id, api_key FROM users WHERE api_key IS NOT NULL AND api_key != ''")->getResultArray();
            foreach ($rows as $r) {
                $hash = hash('sha256', $r['api_key']);
                $db->query("UPDATE users SET api_key_hash = ? WHERE id = ?", [$hash, $r['id']]);
            }

            // drop plaintext column
            $this->forge->dropColumn('users', 'api_key');
        }
    }

    public function down()
    {
        $this->forge->addColumn('users', [
            'api_key' => [
                'type'       => 'VARCHAR',
                'constraint' => 128,
                'null'       => true,
                'after'      => 'role',
            ],
        ]);
        $this->forge->dropColumn('users', 'api_key_hash');
    }
}
