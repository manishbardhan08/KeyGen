<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * Creates the single vault owner account.
 *
 * Everything is configurable from `.env` — you never edit code:
 *
 *   OWNER_NAME     = 'Manish Bardhan'    # display name   (optional, default "Owner")
 *   OWNER_USERNAME = 'manish'            # login username (optional, default "owner")
 *   OWNER_PASSWORD = 'a-strong-password' # REQUIRED in production
 *
 * Then run:  php spark db:seed OwnerSeeder
 *
 * In production the seeder REFUSES to run without OWNER_PASSWORD, so the
 * well-known development password can never leak into a live install.
 */
class OwnerSeeder extends Seeder
{
    public function run()
    {
        $username = trim((string) env('OWNER_USERNAME', 'owner')) ?: 'owner';
        $name     = trim((string) env('OWNER_NAME', 'Owner')) ?: 'Owner';
        $password = (string) env('OWNER_PASSWORD', '');

        $exists = $this->db->table('users')->where('username', $username)->countAllResults();
        if ($exists > 0) {
            echo "  owner '{$username}' already exists — skipping\n";

            return;
        }

        if (ENVIRONMENT === 'production' && $password === '') {
            echo "  Refusing to seed owner in production without OWNER_PASSWORD set.\n";
            echo "  Add OWNER_PASSWORD to .env, or run:\n";
            echo "  OWNER_PASSWORD='a-strong-password' php spark db:seed OwnerSeeder\n";

            return;
        }

        if ($password !== '') {
            $note = 'Password taken from OWNER_PASSWORD.';
        } else {
            // Development only.
            $password = 'changeme123';
            $note     = "[SECURITY WARNING] Development default password 'changeme123' — change it immediately.";
        }

        $this->db->table('users')->insert([
            'name'       => $name,
            'username'   => $username,
            'password'   => password_hash($password, PASSWORD_BCRYPT),
            'role'       => 'owner',
            'balance'    => 0,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        echo "\n[SECURITY] Created owner: name='{$name}', username='{$username}'. {$note}\n\n";
    }
}
