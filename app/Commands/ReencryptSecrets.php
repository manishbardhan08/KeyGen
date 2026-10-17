<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Encryption as EncryptionConfig;
use Config\Services;

/**
 * Re-encrypts every stored secret that uses `Config\Encryption::$key` under a
 * NEW key, so the encryption key can be rotated without losing data.
 *
 * Today the only such secret is `users.api_key_display` (the reversible copy
 * used to show an admin their own API key). The API itself authenticates
 * against the SHA-256 `api_key_hash`, so even a lost display copy does not
 * break licensing — but rotating the key WITH this command keeps the display
 * working too.
 *
 * Usage:
 *   # rotate to a freshly generated key and print the .env line
 *   php spark keyvault:reencrypt
 *
 *   # rotate from a known old key to a known new key
 *   php spark keyvault:reencrypt --old=<64-hex> --new=<64-hex>
 *
 * The command reads the OLD key from `encryption.key` when `--old` is omitted,
 * and prints the new `encryption.key` line to paste into `.env`. It does NOT
 * edit `.env` itself — that keeps the change auditable.
 */
class ReencryptSecrets extends BaseCommand
{
    protected $group       = 'KeyVault';
    protected $name        = 'keyvault:reencrypt';
    protected $description = 'Rotate the encryption key and re-encrypt stored secrets.';
    protected $usage       = 'keyvault:reencrypt [--old <hex>] [--new <hex>]';
    protected $options     = [
        '--old' => 'Current 64-char hex key. Defaults to encryption.key from config.',
        '--new' => 'New 64-char hex key. Defaults to a freshly generated one.',
    ];

    public function run(array $params)
    {
        $oldKey = $this->resolveKey($params['old'] ?? CLI::getOption('old'), config(EncryptionConfig::class)->key);
        if ($oldKey === null) {
            CLI::error('Could not resolve the current encryption key (--old or encryption.key).');

            return EXIT_ERROR;
        }

        $newHex = $params['new'] ?? CLI::getOption('new');
        if (is_string($newHex) && $newHex !== '') {
            $newKey = $this->hexToKey($newHex);
            if ($newKey === null) {
                CLI::error('--new must be 64 hex characters.');

                return EXIT_ERROR;
            }
        } else {
            $newKey = random_bytes(32);
            $newHex = bin2hex($newKey);
        }

        $oldEnc = Services::encrypter($this->configFor($oldKey));
        $newEnc = Services::encrypter($this->configFor($newKey));

        $db   = db_connect();
        $rows = $db->table('users')
            ->select('id, api_key_display')
            ->where('api_key_display IS NOT NULL')
            ->get()->getResultArray();

        $ok = 0;
        $skipped = 0;
        foreach ($rows as $row) {
            $raw = (string) $row['api_key_display'];
            try {
                $plain = $oldEnc->decrypt(base64_decode($raw, true));
            } catch (\Throwable $e) {
                // Already under an older/other key, or corrupt — leave it be.
                $plain = null;
            }
            if (! is_string($plain) || $plain === '') {
                $skipped++;
                continue;
            }
            $db->table('users')->where('id', (int) $row['id'])->update([
                'api_key_display' => base64_encode($newEnc->encrypt($plain)),
            ]);
            $ok++;
        }

        CLI::write("Re-encrypted {$ok} stored secret(s).", 'green');
        if ($skipped > 0) {
            CLI::write("Skipped {$skipped} value(s) that could not be decrypted with the old key.", 'yellow');
        }
        CLI::newLine();
        CLI::write('Put this into .env (and keep it safe — it decrypts your API-key displays):', 'yellow');
        CLI::write('encryption.key = hex2bin:' . $newHex, 'white');
        CLI::newLine();

        return EXIT_SUCCESS;
    }

    /**
     * Accept either a 64-char hex string or an already-binary key.
     */
    private function resolveKey(mixed $value, mixed $configKey): ?string
    {
        if (is_string($value) && $value !== '') {
            return $this->hexToKey($value);
        }
        if (is_string($configKey) && $configKey !== '') {
            return $configKey;
        }

        return null;
    }

    private function hexToKey(string $hex): ?string
    {
        $hex = str_replace('hex2bin:', '', trim($hex));

        if (strlen($hex) === 64 && ctype_xdigit($hex)) {
            return hex2bin($hex);
        }

        // Not hex — assume the caller passed raw key bytes.
        return $hex !== '' ? $hex : null;
    }

    private function configFor(string $key): EncryptionConfig
    {
        $config      = new EncryptionConfig();
        $config->key = $key;

        return $config;
    }
}
