<?php

/*
 * Mutations that turn these tests red:
 * - add AddSecurityHeaders to the web group instead of the global middleware: /up and
 *   /api/versions lose the four headers;
 * - in AddSecurityHeaders, set Content-Security-Policy unconditionally: the route that sets
 *   its own policy loses it.
 */

use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;

function assertSecurityHeaders(TestResponse $response): void
{
    $response->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('Content-Security-Policy', "frame-ancestors 'none'")
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
}

test('the home page with a session carries the four security headers', function () {
    $response = $this->withPasscodeSession()->get('/');

    $response->assertOk();
    assertSecurityHeaders($response);
});

test('responses without a session carry the four security headers', function (string $uri, int $status) {
    $response = $this->get($uri);

    $response->assertStatus($status);
    assertSecurityHeaders($response);
})->with([
    'health' => ['/up', 200],
    'version' => ['/api/version', 200],
    'unknown api address' => ['/api/versions', 404],
    'redirect to the login page' => ['/', 302],
]);

test('the login page carries the four security headers', function () {
    $response = $this->get('/login');

    $response->assertOk();
    assertSecurityHeaders($response);
});

test('a response that sets its own content security policy keeps it', function () {
    Route::get('/test-own-policy', fn () => response('ok')
        ->header('Content-Security-Policy', "default-src 'self'"));

    $this->get('/test-own-policy')
        ->assertOk()
        ->assertHeader('Content-Security-Policy', "default-src 'self'")
        ->assertHeader('X-Frame-Options', 'DENY');
});
