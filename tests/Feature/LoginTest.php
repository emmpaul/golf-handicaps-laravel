<?php

/*
 * Mutations that turn these tests red:
 * - remove $request->session()->regenerate(true) from LoginController::store(): the session
 *   identifier after login is still the one sent before;
 * - remove 'passcode' from dontFlash in bootstrap/app.php: the wrong passcode comes back in
 *   the flashed old input;
 * - in LoginRequest, let 11 attempts through (tooManyAttempts(..., 11)): the 11th request,
 *   with the right passcode, opens the session;
 * - drop the IP from the limiter key ('login'): 203.0.113.8 is blocked by the attempts of
 *   203.0.113.7;
 * - memoize Passcode::fingerprint() in a static property computed once: after the passcode
 *   becomes eagle-2027, eagle-2027 still gets "Code incorrect" and birdie-2026 still gets in.
 */

use Inertia\Testing\AssertableInertia as Assert;

test('the login page renders without a session', function () {
    $this->get('/login')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('login'));
});

test('the login page redirects a browser that already has a session to the home page', function () {
    $this->withPasscodeSession()->get('/login')->assertRedirect('/');
});

test('the right passcode opens a new session and leads to the home page', function () {
    $sessionIdBefore = str_repeat('a', 40);

    $this->withCookie(config('session.cookie'), $sessionIdBefore)
        ->post('/login', ['passcode' => 'birdie-2026'])
        ->assertRedirect('/');

    $sessionIdAfter = session()->getId();
    expect($sessionIdAfter)->not->toBe($sessionIdBefore);

    $this->withCookie(config('session.cookie'), $sessionIdAfter)->get('/')->assertOk();
});

test('once the passcode becomes eagle-2027, eagle-2027 opens the session and birdie-2026 no longer does', function () {
    // A browser logged in while the passcode was still birdie-2026.
    $this->post('/login', ['passcode' => 'birdie-2026'])->assertRedirect('/');

    config(['app.passcode' => 'eagle-2027']);

    $this->post('/login', ['passcode' => 'eagle-2027'])
        ->assertRedirect('/')
        ->assertSessionHasNoErrors();
    $this->get('/')->assertOk();

    $this->from('/login')
        ->post('/login', ['passcode' => 'birdie-2026'])
        ->assertRedirect('/login')
        ->assertSessionHasErrors(['passcode' => 'Code incorrect']);
});

test('a wrong passcode shows an error and opens no session', function (?string $passcode) {
    $this->from('/login')
        ->post('/login', ['passcode' => $passcode])
        ->assertRedirect('/login')
        ->assertSessionHasErrors(['passcode' => 'Code incorrect']);

    $this->get('/')->assertRedirect('/login');
})->with([
    'wrong case' => ['Birdie-2026'],
    'empty' => [''],
]);

test('a wrong passcode never comes back in the flashed input', function () {
    $this->from('/login')->post('/login', ['passcode' => 'Birdie-2026']);

    expect(session()->getOldInput())->not->toHaveKey('passcode');
});

describe('the attempt limit', function () {
    beforeEach(function () {
        foreach (range(1, 10) as $attempt) {
            $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.7'])
                ->post('/login', ['passcode' => 'Birdie-2026'])
                ->assertSessionHasErrors(['passcode' => 'Code incorrect']);
        }
    });

    test('the 11th attempt in the minute is refused, even with the right passcode', function () {
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.7'])
            ->post('/login', ['passcode' => 'birdie-2026'])
            ->assertSessionHasErrors(['passcode' => "Trop d'essais. Réessaie dans une minute."]);

        $this->get('/')->assertRedirect('/login');
    });

    test('another IP still opens the session', function () {
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.8'])
            ->post('/login', ['passcode' => 'birdie-2026'])
            ->assertRedirect('/')
            ->assertSessionHasNoErrors();

        $this->get('/')->assertOk();
    });

    test('the same IP opens the session once the minute has passed', function () {
        $this->travel(61)->seconds();

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.7'])
            ->post('/login', ['passcode' => 'birdie-2026'])
            ->assertRedirect('/')
            ->assertSessionHasNoErrors();

        $this->get('/')->assertOk();
    });
});
