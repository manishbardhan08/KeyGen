<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Global "must be signed in" guard.
 *
 * It is registered in Config\Filters BEFORE `csrf`, and AFTER `remember`, so:
 *   - a valid "remember me" cookie has already had the chance to restore the
 *     session first, and
 *   - a request that is still unauthenticated (e.g. the 30-minute session
 *     has expired) is turned into a clean redirect to /login — instead of being
 *     rejected by the CSRF filter with the confusing
 *     "The action you requested is not allowed." error page.
 *
 * Without this, an expired session + any POST (logout, delete, save) hits the
 * CSRF filter first (global filters run before the per-route `role` filter)
 * and dies with a SecurityException even though the real problem is simply
 * "you are no longer signed in".
 */
class AuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        if (session()->get('user_id')) {
            return null;
        }

        // Not signed in → send to login. The login view shows this flash.
        return redirect()->to('/login')->with('error', 'Your session has expired. Please sign in again.');
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null) {}
}
