<?php

use Inertia\Testing\AssertableInertia as Assert;

test('public home page returns 200 with inertia home component and indonesian lang', function () {
    $response = $this->get('/');

    $response->assertOk()
        ->assertSee('lang="id"', false)
        ->assertInertia(fn (Assert $page) => $page->component('Home'));
});

test('public response includes owasp a02 security headers', function () {
    $response = $this->get('/');

    $response->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeader('Permissions-Policy');

    expect($response->headers->get('Content-Security-Policy'))
        ->toContain("default-src 'self'")
        ->toContain("frame-ancestors 'none'");
});

test('session cookie defaults enforce http only and lax same site', function () {
    expect(config('session.http_only'))->toBeTrue()
        ->and(config('session.same_site'))->toBe('lax')
        ->and(config('database.connections.pgsql.driver'))->toBe('pgsql');
});
