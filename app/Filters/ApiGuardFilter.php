<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;

/**
 * Edge guard for the public licensing API (/api/v1/*).
 *
 * Protects the endpoint against:
 *   • MITM / credential sniffing — in production, plain-HTTP API calls are
 *     rejected outright (426) instead of being redirected, so an API key,
 *     license key or session token is NEVER transmitted in the clear.
 *   • DDoS / brute force — a layered token-bucket rate limit:
 *       1. per client IP      (stops a single host hammering us)
 *       2. per credential     (api_key / license_key / token — stops a leaked
 *          credential, or a host rotating IPs, from hammering one target)
 *   • token caching — API responses are marked no-store so intermediaries
 *     (and the browser) never cache a session token.
 *
 * NOTE ON PROXIES: behind a reverse proxy / CDN you MUST set app.proxyIPs in
 * Config\App (or .env) to your proxy address, otherwise $request->isSecure()
 * and getIPAddress() see the proxy, not the client. See DEPLOYMENT.md §12.
 */
class ApiGuardFilter implements FilterInterface
{
    /** Max requests per window, per client IP. */
    private const IP_CAPACITY = 60;
    private const IP_WINDOW   = 60;

    /** Max requests per window, per api_key / license_key / token. */
    private const CRED_CAPACITY = 30;
    private const CRED_WINDOW   = 60;

    /**
     * Largest request body we will even look at (bytes). The API payloads are
     * tiny JSON objects, so anything larger is abuse — reject with 413 before
     * PHP/CI4 tries to parse it (an oversized body makes getJSON() throw a 500).
     */
    private const MAX_BODY_BYTES = 65_536; // 64 KB

    public function before(RequestInterface $request, $arguments = null)
    {
        // ---- 1) Refuse cleartext in production (anti-MITM) ----------------
        // Rejecting (rather than redirecting) guarantees credentials are never
        // sent over HTTP even for a moment.
        if (ENVIRONMENT === 'production' && ! $request->isSecure()) {
            return service('response')
                ->setStatusCode(426) // Upgrade Required
                ->setHeader('Upgrade', 'TLS/1.2')
                ->setJSON([
                    'status'  => 'error',
                    'message' => 'HTTPS is required for this API.',
                ]);
        }

        // ---- 2) Reject absurd body sizes before parsing -------------------
        $declared = (int) $request->getHeaderLine('Content-Length');
        if ($declared > self::MAX_BODY_BYTES) {
            return service('response')
                ->setStatusCode(413) // Content Too Large
                ->setJSON([
                    'status'  => 'error',
                    'message' => 'Request body too large.',
                ]);
        }

        $throttler = Services::throttler();

        // ---- 3a) Per-IP bucket --------------------------------------------
        // Throttler keys cannot contain reserved characters → hash everything.
        $ipKey = 'api-ip-' . hash('sha256', $request->getIPAddress());
        if ($throttler->check($ipKey, self::IP_CAPACITY, self::IP_WINDOW) === false) {
            return $this->tooMany($throttler->getTokenTime());
        }

        // ---- 3b) Per-credential buckets -----------------------------------
        // Read the payload once; CI4 caches the raw body so the controller can
        // still read it afterwards. getJSON() throws on a malformed/empty body
        // (e.g. one PHP already discarded) — catch it and answer 400, never 500.
        $body = [];
        try {
            $body = $request->getJSON(true) ?? $request->getPost();
        } catch (\Throwable $e) {
            return service('response')
                ->setStatusCode(400)
                ->setJSON([
                    'status'  => 'error',
                    'message' => 'Malformed request body.',
                ]);
        }

        if (is_array($body)) {
            foreach (['api_key', 'license_key', 'token'] as $field) {
                $value = $body[$field] ?? null;
                if (is_string($value) && $value !== '') {
                    $bucket = 'api-' . $field . '-' . hash('sha256', $value);
                    if ($throttler->check($bucket, self::CRED_CAPACITY, self::CRED_WINDOW) === false) {
                        return $this->tooMany($throttler->getTokenTime());
                    }
                }
            }
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // Never let a session token / level response sit in any cache.
        $response->setHeader('Cache-Control', 'no-store, max-age=0')
            ->setHeader('Pragma', 'no-cache');

        return $response;
    }

    /**
     * Build a 429 response carrying the per-IP Retry-After hint.
     */
    private function tooMany(int $retryAfter): ResponseInterface
    {
        $retry = max(self::CRED_WINDOW, min(600, $retryAfter));

        return service('response')
            ->setStatusCode(429)
            ->setJSON([
                'status'  => 'error',
                'message' => 'Too many requests. Please slow down and try again shortly.',
            ])
            ->setHeader('Retry-After', (string) $retry);
    }
}