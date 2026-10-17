<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Stores the default settings for the ContentSecurityPolicy, if you
 * choose to use it. The values here will be read in and set as defaults
 * for the site. If needed, they can be overridden on a page-by-page basis.
 *
 * Suggested reference for explanations:
 *
 * @see https://www.html5rocks.com/en/tutorials/security/content-security-policy/
 */
class ContentSecurityPolicy extends BaseConfig
{
    // -------------------------------------------------------------------------
    // Broadbrush CSP management
    // -------------------------------------------------------------------------

    /**
     * Default CSP report context
     *
     * ENFORCED in production; report-only everywhere else. Kept that way so a
     * missed nonce can never break the panel while developing (violations are
     * only logged), while the live site gets the real policy.
     */
    public bool $reportOnly = (ENVIRONMENT !== 'production');

    /**
     * Specifies a URL where a browser will send reports
     * when a content security policy is violated.
     */
    public ?string $reportURI = null;

    /**
     * Instructs user agents to rewrite URL schemes, changing
     * HTTP to HTTPS. This directive is for websites with
     * large numbers of old URLs that need to be rewritten.
     */
    public bool $upgradeInsecureRequests = (ENVIRONMENT === 'production');

    // -------------------------------------------------------------------------
    // Sources allowed
    // NOTE: once you set a policy to 'none', it cannot be further restricted
    // -------------------------------------------------------------------------

    /**
     * Will default to self if not overridden
     *
     * @var list<string>|string|null
     */
    public $defaultSrc;

    /**
     * Lists allowed scripts' URLs.
     *
     * NOTE: 'unsafe-inline' is deliberately ABSENT. Every inline <script> in
     * the app carries the `{csp-script-nonce}` tag, which CodeIgniter replaces
     * with a fresh per-response nonce (see $autoNonce below). Inline event
     * handler attributes (onclick=, onsubmit=, …) are NOT covered by a nonce,
     * so they have all been replaced with `data-*` hooks + addEventListener.
     * Allowing a CDN origin still works: host-based allows need no nonce.
     *
     * @var list<string>|string
     */
    public $scriptSrc = [
        "'self'",
        'https://cdn.jsdelivr.net',
    ];

    /**
     * Lists allowed stylesheets' URLs.
     *
     * 'unsafe-inline' is REQUIRED here: the views use ~220 inline `style="…"`
     * attributes, and a CSP nonce does NOT apply to inline style attributes
     * (only to <style> elements). CSS injection is far less dangerous than
     * script injection, so this trade-off keeps the UI intact while script-src
     * stays strict.
     *
     * @var list<string>|string
     */
    public $styleSrc = [
        "'self'",
        "'unsafe-inline'",
        'https://fonts.googleapis.com',
    ];

    /**
     * Defines the origins from which images can be loaded.
     *
     * @var list<string>|string
     */
    public $imageSrc = [
        "'self'",
        'data:',
    ];

    /**
     * Restricts the URLs that can appear in a page's `<base>` element.
     *
     * Will default to self if not overridden
     *
     * @var list<string>|string|null
     */
    public $baseURI;

    /**
     * Lists the URLs for workers and embedded frame contents
     *
     * @var list<string>|string
     */
    public $childSrc = 'self';

    /**
     * Limits the origins that you can connect to (via XHR,
     * WebSockets, and EventSource).
     *
     * @var list<string>|string
     */
    public $connectSrc = 'self';

    /**
     * Specifies the origins that can serve web fonts.
     *
     * @var list<string>|string
     */
    public $fontSrc = [
        "'self'",
        'https://fonts.gstatic.com',
        'data:',
    ];

    /**
     * Lists valid endpoints for submission from `<form>` tags.
     *
     * @var list<string>|string
     */
    public $formAction = 'self';

    /**
     * Specifies the sources that can embed the current page.
     * This directive applies to `<frame>`, `<iframe>`, `<embed>`,
     * and `<applet>` tags. This directive can't be used in
     * `<meta>` tags and applies only to non-HTML resources.
     *
     * @var list<string>|string|null
     */
    public $frameAncestors = "'self'";

    /**
     * The frame-src directive restricts the URLs which may
     * be loaded into nested browsing contexts.
     *
     * @var list<string>|string|null
     */
    public $frameSrc;

    /**
     * Restricts the origins allowed to deliver video and audio.
     *
     * @var list<string>|string|null
     */
    public $mediaSrc;

    /**
     * Allows control over Flash and other plugins.
     *
     * @var list<string>|string
     */
    public $objectSrc = 'self';

    /**
     * @var list<string>|string|null
     */
    public $manifestSrc;

    /**
     * Limits the kinds of plugins a page may invoke.
     *
     * @var list<string>|string|null
     */
    public $pluginTypes;

    /**
     * List of actions allowed.
     *
     * @var list<string>|string|null
     */
    public $sandbox;

    /**
     * Nonce tag for style
     */
    public string $styleNonceTag = '{csp-style-nonce}';

    /**
     * Nonce tag for script
     */
    public string $scriptNonceTag = '{csp-script-nonce}';

    /**
     * Replace nonce tag automatically
     *
     * TRUE: CodeIgniter swaps every `{csp-script-nonce}` / `{csp-style-nonce}`
     * placeholder in the response body for a fresh per-response nonce and adds
     * it to script-src / style-src. This is what lets script-src drop
     * 'unsafe-inline' while the app's inline scripts keep working.
     *
     * In development the policy is report-only (see $reportOnly), so a missing
     * nonce can never break the page there; in production it is enforced and
     * every inline <script> in app/Views carries the placeholder tag.
     */
    public bool $autoNonce = true;
}
