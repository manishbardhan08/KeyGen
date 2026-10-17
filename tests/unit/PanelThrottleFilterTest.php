<?php

use App\Filters\PanelThrottleFilter;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\URI;
use CodeIgniter\HTTP\UserAgent;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * The panel write-throttle must never get in the way of normal reads or of
 * anonymous traffic (that is the auth filter's job).
 *
 * @internal
 */
final class PanelThrottleFilterTest extends CIUnitTestCase
{
    public function testReadRequestsAreNeverThrottled(): void
    {
        $filter  = new PanelThrottleFilter();
        $request = $this->request();

        foreach (['GET', 'HEAD', 'OPTIONS'] as $method) {
            $request->setMethod($method);
            $this->assertNull($filter->before($request, null), "{$method} must not be throttled.");
        }
    }

    public function testAnonymousWritesAreIgnored(): void
    {
        $filter  = new PanelThrottleFilter();
        $request = $this->request();
        $request->setMethod('POST');

        // No user_id in a fresh test session → the filter bows out.
        $this->assertNull($filter->before($request, null));
    }

    private function request(): IncomingRequest
    {
        return new IncomingRequest(config('App'), new URI(), null, new UserAgent());
    }
}
