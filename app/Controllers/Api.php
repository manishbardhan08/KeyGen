<?php
namespace App\Controllers;
use CodeIgniter\RESTful\ResourceController;
use App\Models\UserModel;
use App\Models\KeyModel;
use App\Models\ApiSessionModel;

class Api extends ResourceController
{
    protected $format = 'json';

    /**
     * Default text sent when an admin has switched their software off.
     */
    private const DEFAULT_OFFLINE_MSG = 'Server off, kindly wait.';

    /**
     * Resolve the admin whose switch governs a given seller/creator.
     * - admin owns the scope directly
     * - seller → the admin who created them
     *
     * @param array<string,mixed> $user
     * @return array<string,mixed>|null
     */
    private function governingAdmin(array $user): ?array
    {
        $u = new UserModel();
        if (($user['role'] ?? '') === 'admin') {
            return $user;
        }
        if (($user['role'] ?? '') === 'seller' && ! empty($user['created_by'])) {
            $admin = $u->where('username', $user['created_by'])->where('role', 'admin')->first();
            return $admin ?: null;
        }
        // owners / unknown have no per-user switch
        return null;
    }

    /**
     * If the governing admin has switched software off, return a 503 response.
     * Otherwise null.
     */
    private function offlineResponse(?array $admin)
    {
        if ($admin && (int) ($admin['software_enabled'] ?? 1) === 0) {
            $msg = trim((string) ($admin['offline_message'] ?? ''));
            return $this->response
                ->setStatusCode(503)
                ->setHeader('Retry-After', '300')
                ->setJSON([
                    'status'  => 'offline',
                    'message' => $msg !== '' ? $msg : self::DEFAULT_OFFLINE_MSG,
                ]);
        }
        return null;
    }

    public function auth()
    {
        $json = $this->request->getJSON(true) ?? $this->request->getPost();

        // The body is attacker-controlled: every field must be a string before
        // it reaches hash(), a query bind or a comparison. An array/object here
        // would otherwise throw a TypeError and leak a 500.
        if (! is_array($json)) {
            return $this->fail('Malformed request body.', 400);
        }

        $apiKey  = $json['api_key']     ?? null;
        $licKey  = $json['license_key'] ?? null;
        $hwid    = $json['hwid']        ?? null;
        $product = $json['product']     ?? '';

        if (! is_string($apiKey) || ! is_string($licKey) || ! is_string($hwid) || ! is_string($product)) {
            return $this->fail('Missing parameters.', 400);
        }

        $apiKey  = trim($apiKey);
        $licKey  = trim($licKey);
        $hwid    = trim($hwid);
        $product = trim($product);

        if ($apiKey === '' || $licKey === '' || $hwid === '') {
            return $this->fail('Missing parameters.', 400);
        }

        $u = new UserModel();
        $seller = $u->where('api_key_hash', hash_api_key($apiKey))->first();
        if (! $seller || ! in_array($seller['role'], ['admin','seller'], true)) {
            return $this->fail('Invalid API key.', 401);
        }
        if ((int) ($seller['is_banned'] ?? 0) === 1) {
            return $this->fail('Reseller account is banned.', 403);
        }
        if ($seller['expires_at'] && strtotime($seller['expires_at']) < time()) {
            return $this->fail('Reseller account expired.', 403);
        }

        // ----- software on/off gate (before touching keys) -----
        $admin = $this->governingAdmin($seller);
        if ($off = $this->offlineResponse($admin)) {
            return $off;
        }

        $km = new KeyModel();
        $key = $km->where('key', $licKey)->first();
        // Do not distinguish "not found" from "not yours" — that would let an
        // attacker enumerate valid license keys. One generic message either way.
        if (! $key || $key['status'] === 'banned') {
            return $this->fail('Invalid license key.', 403);
        }

        // ownership: admin owns key, OR seller who was created by admin owns it
        $allowed = ((int) $key['created_by'] === (int) $seller['id']);
        if (! $allowed && $seller['role'] === 'admin') {
            $creator = $u->find($key['created_by']);
            if ($creator
                && $creator['role'] === 'seller'
                && ($creator['created_by'] ?? '') === $seller['username']) {
                $allowed = true;
            }
        }
        if (! $allowed) {
            return $this->fail('Invalid license key.', 403);
        }

        // product check — enforced whenever the key or the account declares a
        // product, so a client cannot skip the check by omitting "product".
        // Generic message; the expected product is never echoed to a prober.
        $want = mb_strtolower(trim($product));
        $kp   = mb_strtolower(trim((string) ($key['product']    ?? '')));
        $sp   = mb_strtolower(trim((string) ($seller['product'] ?? '')));

        if ($kp !== '' || $sp !== '') {
            if ($want === ''
                || ($kp !== '' && $kp !== $want)
                || ($sp !== '' && $sp !== $want)) {
                return $this->fail('Product mismatch.', 403);
            }
        }

        // ===== HWID lifecycle (multi-device aware) =====
        $devices = new \App\Models\KeyDeviceModel();
        $limit   = max(1, (int) ($key['device_limit'] ?? 1));

        // Case A · unused key → FIRST LOGIN → activate + bind this device
        if ($key['status'] === 'unused') {
            $now     = date('Y-m-d H:i:s');
            $hours   = max(1, (int) $key['duration_hours']);
            $expires = date('Y-m-d H:i:s', strtotime("+{$hours} hours", strtotime($now)));

            $km->update($key['id'], [
                'status'     => 'used',
                'used_by'    => $seller['id'],
                'used_at'    => $now,       // activation timestamp
                'hwid'       => $hwid,      // primary device
                'expires_at' => $expires,
            ]);
            if (! $devices->isBound($key['id'], $hwid)) {
                $devices->insert(['key_id' => $key['id'], 'hwid' => $hwid]);
            }
            $key = $km->find($key['id']);
        }
        // Case B · used key → accept any already-bound device, else bind if room
        else {
            // Expiry is checked FIRST: an expired key must not be able to
            // accumulate new device bindings from repeated failed logins.
            if ($key['expires_at'] && strtotime($key['expires_at']) < time()) {
                return $this->fail('License expired.', 403);
            }

            // legacy key activated before key_devices existed → register its HWID
            if (! empty($key['hwid']) && ! $devices->isBound($key['id'], $key['hwid'])) {
                $devices->insert(['key_id' => $key['id'], 'hwid' => $key['hwid']]);
            }

            if (! $devices->isBound($key['id'], $hwid)) {
                // Atomic bind: lock the key's device rows, re-count, then insert —
                // so two concurrent activations can't both slip under the limit.
                $db = \Config\Database::connect();
                $db->transStart();

                $locked = $db->query(
                    'SELECT COUNT(*) AS c FROM `key_devices` WHERE `key_id` = ? FOR UPDATE',
                    [$key['id']]
                )->getRowArray();
                $used = (int) ($locked['c'] ?? 0);

                if ($used >= $limit) {
                    $db->transRollback();

                    return $this->fail('This license is already active on its maximum number of devices.', 403);
                }

                $devices->insert(['key_id' => $key['id'], 'hwid' => $hwid]);

                // if there was no primary HWID yet, set it now
                if (empty($key['hwid'])) {
                    $km->update($key['id'], ['hwid' => $hwid]);
                }

                $db->transComplete();
                $key = $km->find($key['id']);
            }
        }

        // ===== session =====
        // We hand the raw token to the client but only store its SHA-256 hash,
        // so a DB leak does not expose usable session tokens.
        $token     = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);
        (new ApiSessionModel())->insert([
            'token'              => $tokenHash,
            'user_id'            => $seller['id'],
            'key_id'             => $key['id'],
            'governing_admin_id' => $admin['id'] ?? null,
            'hwid'               => $hwid,
            'ip'                 => $this->request->getIPAddress(),
            'expires_at'         => $key['expires_at'] ?? null,
        ]);

        return $this->respond([
            'status'         => 'ok',
            'token'          => $token,
            'expires_at'     => $key['expires_at'],
            'duration_hours' => (int) $key['duration_hours'],
            'duration_days'  => (int) ceil(((int) $key['duration_hours']) / 24),
            'level'          => (int) ($key['level'] ?? 0),
            'level_name'     => $this->levelName((int) ($key['level'] ?? 0), $seller),
            'device_limit'   => $limit,
            'devices_used'   => $devices->countForKey((int) $key['id']),
            'hwid'           => $hwid,        // echo back for verification
            'activated_at'   => $key['used_at'] ?? null,
        ]);
    }

    /**
     * Resolve a human name for a level number in the governing admin's scope.
     * Returns null when the level is 0 or has no defined name.
     *
     * @param array<string,mixed> $seller  the admin/seller that owns the key
     */
    private function levelName(int $level, array $seller): ?string
    {
        if ($level <= 0) {
            return null;
        }
        $admin = $this->governingAdmin($seller);
        if (! $admin) {
            return null;
        }
        $map = (new \App\Models\KeyLevelModel())->nameMap((int) $admin['id']);
        return $map[$level] ?? null;
    }

    public function validateToken()
    {
        $json = $this->request->getJSON(true) ?? $this->request->getPost();
        if (! is_array($json)) return $this->fail('Malformed request body.', 400);
        $token = $json['token'] ?? null;
        $hwid  = $json['hwid']  ?? null;
        if (! is_string($token) || ! is_string($hwid) || ! $token || ! $hwid) {
            return $this->fail('Missing parameters.', 400);
        }

        $sm = new ApiSessionModel();
        $session = $sm->where('token', hash('sha256', $token))->first();
        if (! $session) return $this->fail('Invalid token.', 401);
        if ($session['hwid'] !== $hwid) return $this->fail('HWID mismatch.', 403);

        // Validate the token fully BEFORE revealing anything about the
        // governing admin's software switch, so a bogus/expired token gets a
        // 401/403 rather than a 503 (which would leak the switch state).
        $u = new UserModel();

        if (! empty($session['expires_at']) && strtotime((string) $session['expires_at']) < time()) {
            $sm->delete($session['id']); // opportunistic cleanup
            return $this->fail('Session expired.', 403);
        }

        // The session owner must still exist AND still be active. Without this,
        // a session issued to a since-deleted/expired account would keep
        // validating (and reporting a level) until the token itself expired.
        $owner = $u->find($session['user_id']);
        if (! $owner || ! in_array($owner['role'], ['admin', 'seller'], true)) {
            $sm->delete($session['id']);
            return $this->fail('Session invalid.', 401);
        }
        if ($owner['expires_at'] && strtotime($owner['expires_at']) < time()) {
            $sm->delete($session['id']);
            return $this->fail('Account expired.', 403);
        }
        if ((int) ($owner['is_banned'] ?? 0) === 1) {
            $sm->delete($session['id']);
            return $this->fail('Account is banned.', 403);
        }

        // look up the key so we can echo its current level on every check
        $key = (new KeyModel())->find($session['key_id']);
        if (! $key || $key['status'] === 'banned') {
            $sm->delete($session['id']);
            return $this->fail('License no longer valid.', 403);
        }

        // Check the KEY's live expiry (not the stale session copy). If an admin
        // extended the key after this session was issued, the session expiry
        // may be outdated; the key's own expires_at is authoritative.
        if (! empty($key['expires_at']) && strtotime((string) $key['expires_at']) < time()) {
            $sm->delete($session['id']);
            return $this->fail('License expired.', 403);
        }

        // software on/off gate (only after the token is proven valid): resolve
        // the governing admin stored at auth time, or re-resolve from the owner.
        // Live sessions are blocked too, so flipping the switch off kills
        // running clients.
        $admin = null;
        if (! empty($session['governing_admin_id'])) {
            $admin = $u->find($session['governing_admin_id']);
        }
        if (! $admin) {
            $admin = $this->governingAdmin($owner);
        }
        if ($off = $this->offlineResponse($admin)) {
            return $off;
        }

        $level     = (int) ($key['level'] ?? 0);
        $levelName = null;
        if ($level > 0) {
            $levelName = $this->levelName($level, $owner);
        }

        return $this->respond([
            'status'     => 'ok',
            'expires_at' => $session['expires_at'],
            'level'      => $level,
            'level_name' => $levelName,
        ]);
    }

    public function logout()
    {
        $json = $this->request->getJSON(true) ?? $this->request->getPost();
        if (! is_array($json)) return $this->fail('Malformed request body.', 400);
        $token = $json['token'] ?? null;
        if (! is_string($token) || $token === '') return $this->fail('Missing token.', 400);
        $sm = new ApiSessionModel();
        $session = $sm->where('token', hash('sha256', $token))->first();
        if ($session) $sm->delete($session['id']);
        return $this->respond(['status' => 'ok']);
    }
}
