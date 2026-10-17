<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * `api_sessions.expires_at` is set from the license key's own expiry, which is
 * NULL for a key that has never been activated (or a legacy key without an
 * expiry). The column was created `NOT NULL`, so such a session insert would
 * fail with a 500. Making it nullable lets a session exist with no expiry,
 * which validate()/prune already treat as "never expires".
 */
class MakeSessionExpiryNullable extends Migration
{
    public function up()
    {
        if ($this->db->fieldExists('expires_at', 'api_sessions')) {
            $this->forge->modifyColumn('api_sessions', [
                'expires_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
        }
    }

    public function down()
    {
        // A NOT NULL column cannot hold NULLs — expire the stragglers first.
        $this->db->query('UPDATE `api_sessions` SET `expires_at` = NOW() WHERE `expires_at` IS NULL');

        $this->forge->modifyColumn('api_sessions', [
            'expires_at' => ['type' => 'DATETIME', 'null' => false],
        ]);
    }
}
