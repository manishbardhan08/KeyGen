<?php

namespace App\Filters;

use CodeIgniter\Filters\SecureHeaders;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Extends CodeIgniter's SecureHeaders filter with two extra protections:
 *
 *   • Strict-Transport-Security (HSTS) — sent on every response served over
 *     HTTPS in production. The framework's ForceHTTPS filter only sets HSTS
 *     while REDIRECTING http→https; once traffic is already HTTPS no HSTS
 *     header is emitted, so browsers never get the "always use HTTPS" pin.
 *     Adding it here closes that gap (anti-MITM / SSL-strip).
 *
 *   • Permissions-Policy — switches off powerful browser features this app
 *     never uses (camera, mic, geolocation, …), shrinking the attack surface.
 *
 * All of the base headers (X-Frame-Options, X-Content-Type-Options, etc.)
 * are inherited and still emitted.
 */
class SecurityHeadersFilter extends SecureHeaders
{
    /** Max HSTS age in seconds (1 year). */
    private const HSTS_MAX_AGE = 31_536_000;

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        parent::after($request, $response, $arguments);

        // HSTS only makes sense over HTTPS (browsers ignore it otherwise) and
        // should only be pinned from production.
        if (ENVIRONMENT === 'production' && $request->isSecure()) {
            $response->setHeader(
                'Strict-Transport-Security',
                'max-age=' . self::HSTS_MAX_AGE . '; includeSubDomains'
            );
        }

        $response->setHeader(
            'Permissions-Policy',
            'accelerometer=(), camera=(), geolocation=(), gyroscope=(), magnetometer=(), microphone=(), payment=(), usb=()'
        );

        return $response;
    }
}