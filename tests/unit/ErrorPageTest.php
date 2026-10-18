<?php

use CodeIgniter\Test\CIUnitTestCase;

/**
 * The branded HTML error pages.
 *
 * Why these render the views directly instead of doing a FeatureTest 404 request:
 * `CodeIgniter::display404errors()` re-throws PageNotFoundException when no 404
 * override is set, and in the testing environment that exception bubbles straight
 * into PHPUnit — the view is only rendered by the global exception handler on a
 * real web request. Including the view here still exercises the real markup and
 * the shared partial, without fighting the test harness.
 *
 * @internal
 */
final class ErrorPageTest extends CIUnitTestCase
{
    /** Every status CodeIgniter can route to a dedicated view must ship one. */
    public function testBrandedErrorViewsExist(): void
    {
        $views = ['_kg_error', 'error_400', 'error_403', 'error_404', 'error_429', 'production'];

        foreach ($views as $view) {
            $this->assertFileExists(
                APPPATH . "Views/errors/html/{$view}.php",
                "Missing error view: {$view}.php",
            );
        }
    }

    /** Each status page renders the shared branded shell with its own copy. */
    public function testErrorViewsRenderBrandedShell(): void
    {
        $cases = [
            'error_400'  => ['400', 'Bad request'],
            'error_403'  => ['403', 'Access denied'],
            'error_404'  => ['404', 'Page not found'],
            'error_429'  => ['429', 'Too many requests'],
            'production' => ['500', 'Something went wrong'],
        ];

        foreach ($cases as $file => [$code, $heading]) {
            $html = $this->renderErrorView($file);

            $this->assertStringContainsString($heading, $html, "{$file}: heading");
            $this->assertStringContainsString(">{$code}<", $html, "{$file}: status code");
            $this->assertStringContainsString('kg-err-code', $html, "{$file}: shell markup");
            $this->assertStringContainsString('KeyGen', $html, "{$file}: branding");
            $this->assertStringContainsString('Go to dashboard', $html, "{$file}: action");
            $this->assertStringContainsString('noindex', $html, "{$file}: robots meta");
        }
    }

    /**
     * The raw exception message must never reach the visitor — not even in
     * development. This is the behaviour that used to show the bare
     * "Can't find a route for 'GET: …'." line on the 404 page.
     */
    public function testRawExceptionMessageIsNeverPrinted(): void
    {
        $message = "Can't find a route for 'GET: login1'.";

        foreach (['error_400', 'error_403', 'error_404', 'error_429', 'production'] as $file) {
            $html = $this->renderErrorView($file, ['message' => $message]);

            $this->assertStringNotContainsString('login1', $html, "{$file}: leaked the route");
            $this->assertStringNotContainsString('find a route', $html, "{$file}: leaked the message");
            $this->assertStringNotContainsString('<pre', $html, "{$file}: rendered a detail block");
        }
    }

    /**
     * Render one error view the way the exception handler does: with the
     * collected variables extracted into the include scope.
     *
     * @param array<string, mixed> $vars
     */
    private function renderErrorView(string $file, array $vars = []): string
    {
        helper('url');

        $vars += ['code' => 500, 'message' => 'Test failure detail'];
        extract($vars, EXTR_SKIP);

        ob_start();
        include APPPATH . "Views/errors/html/{$file}.php";

        return (string) ob_get_clean();
    }
}
