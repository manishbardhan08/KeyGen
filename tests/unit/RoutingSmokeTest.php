<?php

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * End-to-end smoke checks over the real route + filter chain. These catch the
 * kind of breakage that unit tests miss: a bad filter alias, a syntax error in
 * Routes.php, or a global filter that 500s on the very first request.
 *
 * They intentionally stay anonymous (no DB-dependent login) so they run
 * anywhere.
 *
 * @internal
 */
final class RoutingSmokeTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    public function testLoginPageLoads(): void
    {
        $result = $this->get('login');

        $result->assertStatus(200);
        $result->assertSee('Sign'); // the login screen renders
    }

    public function testRootRedirectsAnonymousUserToLogin(): void
    {
        $this->get('/')->assertRedirectTo(site_url('login'));
    }

    public function testProtectedPanelRedirectsAnonymousUserToLogin(): void
    {
        $this->get('admin/dashboard')->assertRedirectTo(site_url('login'));
    }

    public function testAnonymousPostIsNotThrottledButRedirectedByAuth(): void
    {
        // A POST with no session must be turned away by the auth filter, never
        // by the write-throttle (which only concerns signed-in users).
        $result = $this->post('admin/keys/purge', []);

        $result->assertRedirectTo(site_url('login'));
    }
}
