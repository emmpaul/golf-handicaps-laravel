<?php

/*
 * Mutations that turn these tests red:
 * - remove EnsurePasscodeConfigured from the global middleware: /up answers 200, and /login
 *   reports no exception naming APP_PASSCODE;
 * - move the check into AppServiceProvider::boot() instead of the HTTP stack: config:cache
 *   boots the application without APP_PASSCODE and fails;
 * - in bootstrap/app.php, append AddSecurityHeaders to the global middleware instead of
 *   prepending it after EnsurePasscodeConfigured: the 500 is built outside it and carries
 *   none of the four security headers.
 */

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Exceptions;

test('without a passcode an HTTP request fails with an error naming APP_PASSCODE', function (string $uri) {
    Exceptions::fake();
    config(['app.passcode' => '']);

    $this->get($uri)
        ->assertInternalServerError()
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('Content-Security-Policy', "frame-ancestors 'none'")
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');

    Exceptions::assertReported(fn (RuntimeException $exception): bool => $exception->getMessage()
        === 'APP_PASSCODE must be set: the shared passcode protects every page.');
})->with([
    'health' => ['/up'],
    'login page' => ['/login'],
]);

test('artisan commands run without a passcode', function () {
    // config:cache boots a fresh application that reads the environment, not the test config.
    $saved = [getenv('APP_PASSCODE'), $_ENV['APP_PASSCODE'] ?? null, $_SERVER['APP_PASSCODE'] ?? null];
    $cachePath = sys_get_temp_dir().'/missing-passcode-config-'.getmypid().'.php';
    putenv('APP_PASSCODE=');
    $_ENV['APP_PASSCODE'] = $_SERVER['APP_PASSCODE'] = '';
    // Keeps the cached configuration away from bootstrap/cache, shared with the running app.
    putenv("APP_CONFIG_CACHE={$cachePath}");
    $_ENV['APP_CONFIG_CACHE'] = $_SERVER['APP_CONFIG_CACHE'] = $cachePath;

    try {
        $exitCode = Artisan::call('config:cache');
        $cachedPasscode = (require $cachePath)['app']['passcode'];
        Artisan::call('config:clear');
    } finally {
        putenv($saved[0] === false ? 'APP_PASSCODE' : "APP_PASSCODE={$saved[0]}");
        $_ENV['APP_PASSCODE'] = $saved[1];
        $_SERVER['APP_PASSCODE'] = $saved[2];
        putenv('APP_CONFIG_CACHE');
        unset($_ENV['APP_CONFIG_CACHE'], $_SERVER['APP_CONFIG_CACHE']);
        @unlink($cachePath);
    }

    expect($exitCode)->toBe(0)
        ->and($cachedPasscode)->toBe('');
});
