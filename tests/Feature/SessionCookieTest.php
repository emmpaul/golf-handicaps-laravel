<?php

/*
 * Mutation that turns these tests red: in config/session.php, put back the default
 * 'secure' => env('SESSION_SECURE_COOKIE'): in production without SESSION_SECURE_COOKIE,
 * secure is null and the session cookie set by POST /login lacks Secure.
 */

test('in production without a cookie setting, the session cookie is Secure, HttpOnly and SameSite=Lax', function () {
    $this->loadConfigUnder('session', ['APP_ENV' => 'production']);

    expect(config('session.secure'))->toBeTrue();

    $cookie = $this->post('/login', ['passcode' => 'birdie-2026'])
        ->assertRedirect('/')
        ->getCookie(config('session.cookie'), decrypt: false);

    expect($cookie->isSecure())->toBeTrue()
        ->and($cookie->isHttpOnly())->toBeTrue()
        ->and($cookie->getSameSite())->toBe('lax');
});

test('in local without a cookie setting, the session cookie is not Secure and the session holds', function () {
    $this->loadConfigUnder('session', ['APP_ENV' => 'local']);

    expect(config('session.secure'))->toBeFalse();

    // Read back from the store, as the next request of a browser would.
    config(['session.driver' => 'database']);
    $sessionId = $this->logInWithPasscode();

    $this->getWithSessionCookie('http://localhost/', $sessionId)->assertOk();
});
