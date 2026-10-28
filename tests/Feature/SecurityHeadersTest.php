<?php

/*
 * Mutations that turn these tests red:
 * - add AddSecurityHeaders to the web group instead of the global middleware: /up and
 *   /api/versions lose the four headers;
 * - in AddSecurityHeaders, set Content-Security-Policy unconditionally: the route that sets
 *   its own policy loses it;
 * - in AddSecurityHeaders, read $request->isSecure() before $next($request): TrustProxies has
 *   not run yet, so the page behind the trusted HTTPS proxy loses Strict-Transport-Security;
 * - add includeSubDomains to Strict-Transport-Security: the header is no longer exactly
 *   max-age=31536000.
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

describe('Strict-Transport-Security', function () {
    beforeEach(function () {
        $this->loadConfigUnder('trustedproxy', ['TRUSTED_PROXIES' => '10.0.0.0/8']);
    });

    test('a page behind the trusted HTTPS proxy carries it', function () {
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.1.5'])
            ->withHeader('X-Forwarded-Proto', 'https')
            ->get('/login')
            ->assertOk()
            ->assertHeader('Strict-Transport-Security', 'max-age=31536000');
    });

    test('the health check in plain HTTP does not carry it', function () {
        $this->get('http://localhost/up')
            ->assertOk()
            ->assertHeaderMissing('Strict-Transport-Security');
    });

    test('a request from an unknown proxy announcing HTTPS does not carry it', function () {
        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.9'])
            ->withHeader('X-Forwarded-Proto', 'https')
            ->get('http://localhost/login')
            ->assertOk()
            ->assertHeaderMissing('Strict-Transport-Security');
    });

    test('an error response served in HTTPS carries it', function () {
        $this->get('https://localhost/api/versions')
            ->assertNotFound()
            ->assertHeader('Strict-Transport-Security', 'max-age=31536000');
    });
});
