<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Cache;

abstract class TestCase extends BaseTestCase
{
    /**
     * Testing (Workstream T): `throttle` middleware (added to `/login` and
     * `/reset-password` for real rate-limiting protection) counts EVERY
     * request to that route within its window, not just failed ones, and
     * keys by IP -- which is the same fixed test-client "IP" for the
     * entire suite. Without a per-test reset, the cumulative request
     * count across every test file that POSTs to `/login` throughout a
     * full (well under 60 seconds) test run can exceed the limit,
     * intermittently 429-ing unrelated, otherwise-passing tests depending
     * on execution order -- this is not a security issue, it's the test
     * suite needing the same clean-slate guarantee for the cache store
     * that RefreshDatabase already gives the database.
     */
    protected function setUp(): void
    {
        parent::setUp();

        Cache::store()->flush();
    }
}
