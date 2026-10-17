<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;

/**
 * Rate-limits STATE-CHANGING requests coming from a signed-in panel session
 * (i.e. any POST/PUT/PATCH/DELETE on the owner/admin/seller panels).
 *
 * Why this exists
 * ---------------
 * The panels are full of destructive, one-click writes (delete key, purge,
 * ban seller, regenerate API key, change password, …). Two things can turn a
 * perfectly valid session into a flood of those writes:
 *
 *   1. a stuck client-side retry loop (a JS bug / flaky network re-posting the
 *      same form over and over), and
 *   2. an attacker who got hold of a session or a CSRF token and is now
 *      hammering the destructive endpoints.
 *
 * Neither the login throttle (Auth::attempt) nor the API guard (ApiGuardFilter)
 * covers the authenticated panel, so this closes that gap. It is deliberately
 * generous: the limits sit FAR above any human click-rate, so a real user will
 * never hit them — they only bite on machine-speed abuse.
 *
 * Placed in Config\Filters AFTER `auth` (so the session already exists) and
 * AFTER `csrf` (so only requests that passed CSRF are counted). GET/HEAD reads
 * and the API are never touched.
 */
class PanelThrottleFilter implements FilterInterface
{
    /** HTTP methods that mutate state. */
    private const WRITE_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    /** Max write actions per window, per signed-in user. */
    private const USER_CAPACITY = 120;
    private const USER_WINDOW   = 60;

    /** Max write actions per window, per client IP (across all accounts). */
    private const IP_CAPACITY = 360;
    private const IP_WINDOW   = 60;

    public function before(RequestInterface $request, $arguments = null)
    {
        // Reads are not throttled — only state-changing requests.
        if (! in_array($request->getMethod(), self::WRITE_METHODS, true)) {
            return null;
        }

        $session = session();
        $userId  = $session->get('user_id');

        // Anonymous write attempts are the `auth` filter's job, not ours.
        if (! $userId) {
            return null;
        }

        $throttler = Services::throttler();

        // ---- Per-IP ceiling ------------------------------------------------
        // Throttler keys cannot contain reserved characters → hash everything.
        $ipKey = 'panel-ip-' . hash('sha256', $request->getIPAddress());
        if ($throttler->check($ipKey, self::IP_CAPACITY, self::IP_WINDOW) === false) {
            return $this->tooMany();
        }

        // ---- Per-user bucket ----------------------------------------------
        $userKey = 'panel-user-' . hash('sha256', $userId . '|' . $session->get('role'));
        if ($throttler->check($userKey, self::USER_CAPACITY, self::USER_WINDOW) === false) {
            return $this->tooMany();
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }

    /**
     * Send the user back with a flash message — the panel's forms are plain
     * (non-AJAX) posts, so a redirect keeps the UI consistent instead of
     * dumping a bare error page.
     */
    private function tooMany()
    {
        return redirect()->back()
            ->with('error', 'Too many actions at once. Please slow down and try again in a moment.');
    }
}
