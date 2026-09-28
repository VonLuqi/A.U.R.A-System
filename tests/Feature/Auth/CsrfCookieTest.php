<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * §5.8 — CSRF bootstrap for SPA (no Sanctum).
 */
class CsrfCookieTest extends TestCase
{
    use RefreshDatabase;

    public function test_csrf_cookie_endpoint_returns_no_content_and_sets_xsrf_cookie(): void
    {
        $response = $this->get('/api/csrf-cookie');

        $response->assertNoContent();
        $response->assertCookie('XSRF-TOKEN');
    }

    public function test_spa_shell_also_sets_xsrf_cookie(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertCookie('XSRF-TOKEN');
    }
}
