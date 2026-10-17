<?php
namespace App\Database\Seeds;
use CodeIgniter\Database\Seeder;

class OwnerSeeder extends Seeder
{
    public function run()
    {
        $exists = $this->db->table('users')->where('username', 'owner')->countAllResults();
        if ($exists > 0) {
            echo "  owner 'owner' already exists — skipping\n";
            return;
        }

        // Production must never get the known dev password. Require an explicit
        // OWNER_PASSWORD in the environment; refuse to create a weak default.
        $envPassword = (string) (getenv('OWNER_PASSWORD') ?: '');

        if (ENVIRONMENT === 'production' && $envPassword === '') {
            echo "  Refusing to seed owner in production without OWNER_PASSWORD set.\n";
            echo "  Re-run as:  OWNER_PASSWORD='a-strong-password' php spark db:seed OwnerSeeder\n";
            return;
        }

        if ($envPassword !== '') {
            $password = $envPassword;
            $note     = 'Password taken from OWNER_PASSWORD.';
        } else {
            // Development only.
            $password = 'changeme123';
            $note     = "[SECURITY WARNING] Development default password 'changeme123' — change it immediately.";
        }

        $this->db->table('users')->insert([
            'name'       => 'Owner',
            'username'   => 'owner',
            'password'   => password_hash($password, PASSWORD_BCRYPT),
            'role'       => 'owner',
            'balance'    => 0,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        echo "\n[SECURITY] Created owner account: username='owner'. {$note}\n\n";
    }
}
