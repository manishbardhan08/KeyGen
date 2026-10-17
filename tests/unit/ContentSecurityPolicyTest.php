<?php

use CodeIgniter\Test\CIUnitTestCase;

/**
 * Guards the hardened Content-Security-Policy.
 *
 * The panel relies on two things holding together:
 *   1. script-src is strict (NO 'unsafe-inline') and inline scripts are allowed
 *      only through the per-response nonce placeholder `{csp-script-nonce}`.
 *   2. every view keeps up its end of the bargain — no bare inline <script> and
 *      no inline on*= event handlers, because a nonce cannot cover those.
 *
 * If someone adds an inline handler or forgets the nonce tag, these tests fail
 * BEFORE it silently breaks the live (enforcing) policy.
 *
 * @internal
 */
final class ContentSecurityPolicyTest extends CIUnitTestCase
{
    public function testConfigKeepsScriptStrictButAllowsInlineStyles(): void
    {
        $config = config('ContentSecurityPolicy');

        $this->assertTrue($config->autoNonce, 'autoNonce must be enabled for the nonce tags to be replaced.');

        $this->assertNotContains("'unsafe-inline'", $config->scriptSrc, 'script-src must not allow inline scripts.');
        $this->assertContains("'self'", $config->scriptSrc);

        // Inline style="…" attributes (~220 of them) can't be nonce'd, so the
        // style directive intentionally keeps 'unsafe-inline'.
        $this->assertContains("'unsafe-inline'", $config->styleSrc);
    }

    public function testNoncePlaceholderIsReplacedAndAddedToTheHeader(): void
    {
        $response = service('response');
        $response->setBody('<script {csp-script-nonce}>var x = 1;</script>');

        $response->getCSP()->finalize($response);
        $body = $response->getBody();

        // Non-production runs are report-only, so the policy lands on the
        // report-only header; production uses the enforcing one. Accept either.
        $header = $response->getHeaderLine('Content-Security-Policy');
        if ($header === '') {
            $header = $response->getHeaderLine('Content-Security-Policy-Report-Only');
        }
        $this->assertNotSame('', $header, 'A CSP header must be emitted.');

        // 1) Placeholder swapped for a real nonce, and it never leaks through.
        $this->assertStringNotContainsString('{csp-script-nonce}', $body);
        $this->assertMatchesRegularExpression('/<script nonce="[0-9a-f]+">/', $body);

        // 2) The same nonce is advertised on script-src (and not unsafe-inline).
        $this->assertMatchesRegularExpression('/script-src ([^;]*)/', $header);
        preg_match('/script-src ([^;]*)/', $header, $m);
        $this->assertStringContainsString('nonce-', $m[1]);
        $this->assertStringNotContainsString("'unsafe-inline'", $m[1]);
    }

    public function testEveryAppInlineScriptCarriesTheNonceTag(): void
    {
        $offenders = [];

        foreach ($this->appViewFiles() as $path) {
            $src = (string) file_get_contents($path);

            if (preg_match_all('/<script\b([^>]*)>/i', $src, $m)) {
                foreach ($m[1] as $attrs) {
                    // External scripts (src=…) are allowed by host and need no nonce.
                    if (stripos($attrs, 'src=') !== false) {
                        continue;
                    }
                    if (str_contains($attrs, '{csp-script-nonce}')) {
                        continue;
                    }
                    $offenders[] = $this->short($path) . ' :: <script' . $attrs . '>';
                }
            }
        }

        $this->assertSame([], $offenders, 'Inline <script> missing {csp-script-nonce}: ' . implode(' | ', $offenders));
    }

    public function testNoInlineEventHandlersRemainInAppViews(): void
    {
        $offenders = [];

        foreach ($this->appViewFiles() as $path) {
            $src = (string) file_get_contents($path);

            // Match an on*= attribute that sits INSIDE an HTML tag (after "<tag").
            // This deliberately won't match "onclick=" appearing in a JS comment
            // or string, which is how the replaced handlers are documented.
            if (preg_match_all('/<[a-zA-Z][a-zA-Z0-9]*\b[^>]*\son[a-z]+\s*=/i', $src, $m)) {
                foreach ($m[0] as $tag) {
                    $offenders[] = $this->short($path) . ' :: ' . trim($tag);
                }
            }
        }

        $this->assertSame([], $offenders, 'Inline event handlers cannot be nonce\'d: ' . implode(' | ', $offenders));
    }

    /**
     * All app view templates except the stock CodeIgniter dev-only error pages
     * (never used in production — production.php has no scripts).
     *
     * @return list<string>
     */
    private function appViewFiles(): array
    {
        $files = [];
        $dir   = APPPATH . 'Views';

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            /** @var SplFileInfo $file */
            if ($file->getExtension() !== 'php') {
                continue;
            }
            if (str_contains($file->getPathname(), DIRECTORY_SEPARATOR . 'errors' . DIRECTORY_SEPARATOR)) {
                continue;
            }
            $files[] = $file->getPathname();
        }

        sort($files);

        return $files;
    }

    private function short(string $path): string
    {
        return str_replace(APPPATH, '', $path);
    }
}
