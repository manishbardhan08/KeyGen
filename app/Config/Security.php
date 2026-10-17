<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class Security extends BaseConfig
{
    /**
     * --------------------------------------------------------------------------
     * CSRF Protection Method
     * --------------------------------------------------------------------------
     *
     * Protection Method for Cross Site Request Forgery protection.
     *
     * @var string 'cookie' or 'session'
     */
    public string $csrfProtection = 'cookie';

    /**
     * --------------------------------------------------------------------------
     * CSRF Token Randomization
     * --------------------------------------------------------------------------
     *
     * Randomize the CSRF Token for added security.
     */
    public bool $tokenRandomize = false;

    /**
     * --------------------------------------------------------------------------
     * CSRF Token Name
     * --------------------------------------------------------------------------
     *
     * Token name for Cross Site Request Forgery protection.
     */
    public string $tokenName = 'csrf_test_name';

    /**
     * --------------------------------------------------------------------------
     * CSRF Header Name
     * --------------------------------------------------------------------------
     *
     * Header name for Cross Site Request Forgery protection.
     */
    public string $headerName = 'X-CSRF-TOKEN';

    /**
     * --------------------------------------------------------------------------
     * CSRF Cookie Name
     * --------------------------------------------------------------------------
     *
     * Cookie name for Cross Site Request Forgery protection.
     */
    public string $cookieName = 'csrf_cookie_name';

    /**
     * --------------------------------------------------------------------------
     * CSRF Expires
     * --------------------------------------------------------------------------
     *
     * Expiration time for Cross Site Request Forgery protection cookie.
     *
     * Defaults to two hours (in seconds).
     */
    public int $expires = 7200;

    /**
     * --------------------------------------------------------------------------
     * CSRF Regenerate
     * --------------------------------------------------------------------------
     *
     * Regenerate CSRF Token on every submission.
     *
     * IMPORTANT: keep this FALSE in this app.
     *
     * The panel's delete/ban/reset actions are built from a token rendered
     * once into each page (the `<meta name="csrf-token">` tag and the inline
     * `csrf_field()` forms). With regeneration ON, the FIRST successful POST
     * rotates the token, so every already-rendered page — most visibly a page
     * reached with the browser Back button (e.g. "All Keys" after deleting a
     * level) — still carries the OLD token. The next POST then fails CSRF with
     * "The action you requested is not allowed.", which looks like key
     * deletion being broken.
     *
     * Turning it off keeps one stable, random, per-visitor token for the
     * cookie's lifetime (see $expires). CSRF protection is unchanged: a
     * cross-site attacker still cannot read the token (HttpOnly + SameSite
     * cookie); it simply stops rotating after every request.
     */
    public bool $regenerate = false;

    /**
     * --------------------------------------------------------------------------
     * CSRF Redirect
     * --------------------------------------------------------------------------
     *
     * Redirect to previous page with error on failure.
     *
     * @see https://codeigniter4.github.io/userguide/libraries/security.html#redirection-on-failure
     */
    public bool $redirect = (ENVIRONMENT === 'production');
}
