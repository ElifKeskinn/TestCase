<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\RateLimiter;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    /**
     * Clear named rate limiters between tests so per-IP hit counts
     * (reset / play_all / generate_fixtures) do not leak across cases.
     */
    protected function setUp(): void
    {
        parent::setUp();

        foreach (['reset', 'play_all', 'generate_fixtures'] as $key) {
            RateLimiter::clear($key);
        }
    }
}
