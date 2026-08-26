<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Sanctum decides whether a request is "stateful" — and therefore whether it gets
     * a session at all — from the Origin/Referer header. A real browser always sends
     * one; the test HTTP client does not, so requests would silently run session-less
     * and every auth test would fail on a missing session store.
     *
     * Setting it here tests the same code path production uses, rather than special-
     * casing the middleware for the test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->withHeader('Origin', (string) config('app.frontend_url'));
    }
}
