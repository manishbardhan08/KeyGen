<?php

namespace App\Filters;

use App\Models\UserModel;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Persistent-login ("Remember me") re-authentication.
 *
 * Runs globally before the role filter. When there is no live panel session
 * but a valid, encrypted remember cookie is present, it silently rebuilds the
 * session so the user is not bounced to the login screen.
 *
 * The cookie only ever carries an encrypted {uid, exp} payload (see
 * set_remember_cookie()); it is invalidated when the account is deleted or
 * expired, and cleared on logout.
 */
class RememberFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $session = session();

        // Already authenticated — nothing to do.
        if ($session->get('user_id')) {
            return null;
        }

        $payload = read_remember_payload();
        if (! $payload) {
            return null;
        }

        $uid = (int) $payload['uid'];
        $sig = (string) ($payload['sig'] ?? '');

        $user = (new UserModel())->find($uid);

        // Account gone, or expired (owners never expire) → forget the cookie.
        if (! $user || ! in_array($user['role'], ['owner', 'admin', 'seller'], true)) {
            clear_remember_cookie();
            return null;
        }
        if ((int) ($user['is_banned'] ?? 0) === 1) {
            clear_remember_cookie();
            return null;
        }
        if ($user['role'] !== 'owner'
            && $user['expires_at']
            && strtotime($user['expires_at']) < time()) {
            clear_remember_cookie();
            return null;
        }

        // Automatic revocation: the cookie carries an HMAC over
        // uid + exp + the current password hash. A password change (or any
        // tamper with the payload) makes the MAC mismatch, revoking the cookie
        // immediately on every device.
        if ($sig !== '') {
            $expectedSig = remember_signature($uid, (int) $payload['exp'], (string) ($user['password'] ?? ''));
            if (! hash_equals($expectedSig, $sig)) {
                clear_remember_cookie();
                return null;
            }
        }

        // Fresh session id on silent re-auth (defends against fixation), then
        // seed it exactly like Auth::attempt() does.
        $session->regenerate();
        $session->set([
            'user_id'  => $user['id'],
            'username' => $user['username'],
            'name'     => $user['name'],
            'role'     => $user['role'],
        ]);

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null) {}
}
