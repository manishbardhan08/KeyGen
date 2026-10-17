<?php
if (!function_exists('kg_duration')) {
    /**
     * Human label for a duration expressed in HOURS.
     * e.g. 720 → "30d", 12 → "12h", 36 → "36h" (long: "30 days"/"12 hours").
     */
    function kg_duration(int $hours, bool $long = false): string
    {
        if ($hours <= 0) {
            return '—';
        }
        if ($hours % 24 === 0) {
            $d = intdiv($hours, 24);

            return $long ? ($d . ($d === 1 ? ' day' : ' days')) : ($d . 'd');
        }

        return $long ? ($hours . ($hours === 1 ? ' hour' : ' hours')) : ($hours . 'h');
    }
}

if (!function_exists('parse_level_list')) {
    /**
     * Parse a stored CSV of level numbers (e.g. "2,3") into a unique, ordered
     * list of positive ints. Empty/missing → [].
     *
     * @return list<int>
     */
    function parse_level_list(?string $csv): array
    {
        if (! is_string($csv) || trim($csv) === '') {
            return [];
        }

        $out = [];
        foreach (explode(',', $csv) as $part) {
            $n = (int) trim($part);
            if ($n > 0) {
                $out[$n] = $n;
            }
        }

        return array_values($out);
    }
}

if (!function_exists('normalize_key_part')) {
    /**
     * Sanitize one human-supplied key segment: uppercase A-Z0-9 only, length-capped.
     */
    function normalize_key_part(string $s, int $max = 16): string
    {
        $s = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $s));

        return substr($s, 0, $max);
    }
}

if (!function_exists('generate_license_key_for')) {
    /**
     * Build a license key for a specific owner.
     *
     * Format:  <USERNAME>[-<TAG>]-<random>
     *
     * The username is ALWAYS the prefix (built in). The tag is optional — use
     * it to mark whose key it is (customer / batch), or leave it blank.
     *
     * The TOTAL key length is constant (KEY_TOTAL_LEN) whether or not a tag is
     * supplied: the random tail absorbs the difference. Keys only grow longer
     * when username + tag alone already exceed the budget.
     */
    function generate_license_key_for(string $username, string $tag = ''): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $target   = 29; // constant total length

        $head = normalize_key_part($username, 16);
        $tag  = normalize_key_part($tag, 16);
        if ($tag !== '') {
            $head .= '-' . $tag;
        }

        // Chars available for the random tail (its own grouping dashes included).
        // Floor the tail at 12 chars (≈60 bits over the 32-char alphabet) so a
        // long username + long tag can never collapse the key to a guessable
        // handful of random characters; the key simply grows past $target in
        // that (rare) case rather than losing entropy.
        $budget = $target - strlen($head) - 1;
        if ($budget < 12) {
            $budget = 12;
        }

        $out = '';
        $chars = 0;
        while (strlen($out) < $budget) {
            if ($chars > 0 && $chars % 5 === 0 && strlen($out) + 1 <= $budget) {
                $out .= '-';
            }
            $out .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            $chars++;
        }
        $out = substr($out, 0, $budget);

        // never end on a grouping dash
        if ($out !== '' && substr($out, -1) === '-') {
            $out = substr($out, 0, -1) . $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return $head . '-' . $out;
    }
}

if (!function_exists('generate_api_key')) {
    function generate_api_key(): string
    {
        return bin2hex(random_bytes(32));
    }
}
if (!function_exists('kg_icon')) {
    function kg_icon(string $name, int $size = 18): string {
        $s = $size;
        $icons = [
            'users'  => "<svg width=\"$s\" height=\"$s\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"1.8\" stroke-linecap=\"round\" stroke-linejoin=\"round\"><path d=\"M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2\"/><circle cx=\"9\" cy=\"7\" r=\"4\"/><path d=\"M22 21v-2a4 4 0 0 0-3-3.87\"/><path d=\"M16 3.13a4 4 0 0 1 0 7.75\"/></svg>",
            'key'    => "<svg width=\"$s\" height=\"$s\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"1.8\" stroke-linecap=\"round\" stroke-linejoin=\"round\"><circle cx=\"7.5\" cy=\"15.5\" r=\"3.5\"/><path d=\"m21 2-9.6 9.6\"/><path d=\"m15.5 7.5 3 3L22 7l-3-3\"/></svg>",
            'bag'    => "<svg width=\"$s\" height=\"$s\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"1.8\" stroke-linecap=\"round\" stroke-linejoin=\"round\"><path d=\"M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z\"/><path d=\"M3 6h18\"/><path d=\"M16 10a4 4 0 0 1-8 0\"/></svg>",
            'user'   => "<svg width=\"$s\" height=\"$s\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"1.8\" stroke-linecap=\"round\" stroke-linejoin=\"round\"><path d=\"M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2\"/><circle cx=\"12\" cy=\"7\" r=\"4\"/></svg>",
            'out'    => "<svg width=\"$s\" height=\"$s\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"1.8\" stroke-linecap=\"round\" stroke-linejoin=\"round\"><path d=\"M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4\"/><polyline points=\"16 17 21 12 16 7\"/><line x1=\"21\" y1=\"12\" x2=\"9\" y2=\"12\"/></svg>",
            'plus'   => "<svg width=\"$s\" height=\"$s\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\" stroke-linecap=\"round\" stroke-linejoin=\"round\"><line x1=\"12\" y1=\"5\" x2=\"12\" y2=\"19\"/><line x1=\"5\" y1=\"12\" x2=\"19\" y2=\"12\"/></svg>",
            'trash'  => "<svg width=\"$s\" height=\"$s\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"1.8\" stroke-linecap=\"round\" stroke-linejoin=\"round\"><polyline points=\"3 6 5 6 21 6\"/><path d=\"M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2\"/></svg>",
            'shield' => "<svg width=\"$s\" height=\"$s\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"1.8\" stroke-linecap=\"round\" stroke-linejoin=\"round\"><path d=\"M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z\"/></svg>",
            'spark'  => "<svg width=\"$s\" height=\"$s\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"1.8\" stroke-linecap=\"round\" stroke-linejoin=\"round\"><path d=\"M12 2 9.5 9.5 2 12l7.5 2.5L12 22l2.5-7.5L22 12l-7.5-2.5z\"/></svg>",
            'coin'   => "<svg width=\"$s\" height=\"$s\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"1.8\" stroke-linecap=\"round\" stroke-linejoin=\"round\"><circle cx=\"12\" cy=\"12\" r=\"9\"/><path d=\"M12 7v10\"/><path d=\"M9 10h4.5a2.5 2.5 0 0 1 0 5H9\"/></svg>",
            'clock'  => "<svg width=\"$s\" height=\"$s\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"1.8\" stroke-linecap=\"round\" stroke-linejoin=\"round\"><circle cx=\"12\" cy=\"12\" r=\"9\"/><polyline points=\"12 7 12 12 15 14\"/></svg>",
            'layers' => "<svg width=\"$s\" height=\"$s\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"1.8\" stroke-linecap=\"round\" stroke-linejoin=\"round\"><polygon points=\"12 2 2 7 12 12 22 7 12 2\"/><polyline points=\"2 17 12 22 22 17\"/><polyline points=\"2 12 12 17 22 12\"/></svg>",
        ];
        return $icons[$name] ?? '';
    }
}

if (!function_exists('kg_json')) {
    /**
     * JSON-encode a value for SAFE embedding inside an inline <script> block.
     *
     * Plain json_encode() leaves `<`, `>`, `&`, `'` and `"` intact, so a value
     * containing the literal string "</script>" breaks out of the script tag
     * and turns into HTML — classic stored XSS. JSON_HEX_TAG/AMP/APOS/QUOT
     * encodes those characters as \uXXXX escapes, which are still valid JSON
     * and valid JavaScript, but can never terminate the tag or an attribute.
     *
     * @param mixed $value
     */
    function kg_json($value): string
    {
        $flags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
        $json  = json_encode($value, $flags);

        return $json === false ? 'null' : $json;
    }
}

if (!function_exists('key_is_expired')) {
    /**
     * True when a key has been activated AND its expiry has already passed.
     * Unused keys never expire (their clock starts at first activation).
     *
     * @param array<string,mixed> $key
     */
    function key_is_expired(array $key): bool
    {
        return ($key['status'] ?? '') === 'used'
            && ! empty($key['expires_at'])
            && strtotime((string) $key['expires_at']) < time();
    }
}

if (!function_exists('key_is_banned')) {
    /**
     * True when a key has been banned (disabled by its owner/admin).
     *
     * @param array<string,mixed> $key
     */
    function key_is_banned(array $key): bool
    {
        return ($key['status'] ?? '') === 'banned';
    }
}

if (!function_exists('remember_cookie_name')) {
    /**
     * Name of the encrypted "remember me" cookie.
     */
    function remember_cookie_name(): string
    {
        return 'kg_remember';
    }
}

if (!function_exists('remember_signature')) {
    /**
     * Compute the HMAC that authenticates a "remember me" cookie payload.
     *
     * The payload is already encrypted, but this adds a keyed integrity tag
     * (full 256-bit HMAC) AND keeps the automatic revocation-on-password-change
     * behaviour: the user's password hash is folded into the MAC, so any
     * password change makes previously issued cookies fail verification.
     */
    function remember_signature(int $userId, int $exp, string $passwordHash = ''): string
    {
        $key = (string) (config('Encryption')->key ?? '');

        return hash_hmac('sha256', $userId . '|' . $exp . '|' . $passwordHash, $key);
    }
}

if (!function_exists('set_remember_cookie')) {
    /**
     * Issue a persistent, encrypted "remember me" cookie for a user.
     *
     * The payload is {uid, sig, exp} encrypted with the app encrypter.
     * sig is an HMAC over uid + exp + the user's password hash, so it both
     * proves authenticity and is automatically revoked by any password change.
     */
    function set_remember_cookie(int $userId, string $passwordHash = '', ?int $days = null): void
    {
        $days = $days ?? (int) (config('App')->rememberMeDays ?? 30);
        if ($days <= 0) {
            return;
        }

        $exp = time() + $days * 86400;

        try {
            $payload = json_encode([
                'uid' => $userId,
                'sig' => remember_signature($userId, $exp, $passwordHash),
                'exp' => $exp,
            ]);
            $value = base64_encode(service('encrypter')->encrypt($payload));
        } catch (\Throwable $e) {
            log_message('error', 'remember-cookie set failed: ' . $e->getMessage());
            return;
        }

        set_cookie(remember_cookie_name(), $value, $days * 86400);
    }
}

if (!function_exists('clear_remember_cookie')) {
    /**
     * Drop the "remember me" cookie.
     */
    function clear_remember_cookie(): void
    {
        if (isset($_COOKIE[remember_cookie_name()])) {
            unset($_COOKIE[remember_cookie_name()]);
        }
        delete_cookie(remember_cookie_name());
    }
}

if (!function_exists('read_remember_payload')) {
    /**
     * Validate the "remember me" cookie and return the decrypted payload, or
     * null when absent / tampered / expired.
     *
     * @return array{uid: int, sig: string, exp: int}|null
     */
    function read_remember_payload(): ?array
    {
        $raw = $_COOKIE[remember_cookie_name()] ?? null;
        if (! is_string($raw) || $raw === '') {
            return null;
        }

        $decoded = base64_decode($raw, true);
        if ($decoded === false) {
            return null;
        }

        try {
            $json = service('encrypter')->decrypt($decoded);
        } catch (\Throwable $e) {
            return null;
        }
        if (! is_string($json)) {
            return null;
        }

        $data = json_decode($json, true);
        if (! is_array($data) || empty($data['uid']) || empty($data['exp'])) {
            return null;
        }
        if ((int) $data['exp'] < time()) {
            return null;
        }

        return [
            'uid' => (int) $data['uid'],
            'sig' => (string) ($data['sig'] ?? ''),
            'exp' => (int) $data['exp'],
        ];
    }
}

if (!function_exists('hash_api_key')) {
    /**
     * Hash an API key for storage.
     *
     * Uses HMAC-SHA256 with the app encryption key as a server-side salt,
     * so the hash is still queryable (deterministic) but cannot be cracked
     * with a generic rainbow table without also knowing the encryption key.
     */
    function hash_api_key(string $plain): string
    {
        $key = (string) (config('Encryption')->key ?? '');
        return hash_hmac('sha256', $plain, $key);
    }
}

if (!function_exists('encrypt_api_key')) {
    function encrypt_api_key(string $plain): ?string {
        try { return base64_encode(service('encrypter')->encrypt($plain)); }
        catch (\Throwable $e) { log_message('error', 'enc: '.$e->getMessage()); return null; }
    }
}

if (!function_exists('decrypt_api_key')) {
    function decrypt_api_key(?string $enc): ?string {
        if (! $enc) return null;
        try { return service('encrypter')->decrypt(base64_decode($enc)); }
        catch (\Throwable $e) { return null; }
    }
}
