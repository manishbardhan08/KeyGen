<?php
namespace App\Database\Migrations;
use CodeIgniter\Database\Migration;

/**
 * Adds the encrypted-display column used to show an admin's API key back
 * to them in the profile / admins UI. The plaintext key is encrypted at
 * rest with the app encrypter (Config\Encryption::$key), while the SHA-256
 * hash in `api_key_hash` is what the API actually authenticates against.
 */
class AddApiKeyDisplayColumn extends Migration
{
    public function up()
    {
        if (! $this->db->fieldExists('api_key_display', 'users')) {
            $this->forge->addColumn('users', [
                'api_key_display' => [
                    'type'  => 'TEXT',
                    'null'  => true,
                    'after' => 'api_key_hash',
                ],
            ]);
        }
    }

    public function down()
    {
        $this->forge->dropColumn('users', 'api_key_display');
    }
}