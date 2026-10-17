<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Per-admin "software on/off" switch.
 *
 *   users.software_enabled  – admin's kill-switch (1 = online, 0 = offline)
 *   users.offline_message   – custom text returned to the client when offline
 *   api_sessions.governing_admin_id – which admin's switch governs this session
 */
class AddSoftwareSwitches extends Migration
{
    public function up()
    {
        // users: switch + message
        if (! $this->db->fieldExists('software_enabled', 'users')) {
            $this->forge->addColumn('users', [
                'software_enabled' => [
                    'type'       => 'TINYINT',
                    'constraint' => 1,
                    'default'    => 1,
                    'after'      => 'product',
                ],
            ]);
        }
        if (! $this->db->fieldExists('offline_message', 'users')) {
            $this->forge->addColumn('users', [
                'offline_message' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 255,
                    'null'       => true,
                    'after'      => 'software_enabled',
                ],
            ]);
        }

        // api_sessions: remember which admin governs the session so validate()
        // doesn't have to re-resolve the seller → admin chain every call.
        if (! $this->db->fieldExists('governing_admin_id', 'api_sessions')) {
            $this->forge->addColumn('api_sessions', [
                'governing_admin_id' => [
                    'type'     => 'INT',
                    'unsigned' => true,
                    'null'     => true,
                    'after'    => 'key_id',
                ],
            ]);
            $this->db->query('CREATE INDEX `idx_sessions_gov_admin` ON `api_sessions` (`governing_admin_id`)');
        }
    }

    public function down()
    {
        foreach (['software_enabled', 'offline_message'] as $col) {
            if ($this->db->fieldExists($col, 'users')) {
                $this->forge->dropColumn('users', $col);
            }
        }
        if ($this->db->fieldExists('governing_admin_id', 'api_sessions')) {
            $this->forge->dropColumn('api_sessions', 'governing_admin_id');
        }
    }
}