<?php

/*
 * Mutations that turn these tests red:
 * - remove RequirePasscode from the web group in bootstrap/app.php: / and the section page
 *   answer 200 and /icon-admin answers 404 without a session;
 * - in RequirePasscode, replace the fingerprint comparison with
 *   $request->session()->has('access.passcode'): the session opened with birdie-2026 still
 *   gets in after the passcode becomes eagle-2027;
 * - in bootstrap/app.php, place RequirePasscode after PreventRequestForgery in the priority
 *   list: the stale CSRF token then answers 419 instead of 401;
 * - in RequirePasscode, answer an Inertia write with redirect('/login', 307): the PUT visit gets
 *   307, which the Inertia client replays as a PUT on /login (405), instead of 303.
 */

use App\Support\Passcode;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Route;

test('a page without a session redirects to the login page', function (string $uri) {
    $this->get($uri)->assertRedirect('/login');
})->with([
    'home' => ['/'],
    'section' => ['/rounds/0190a6b2-7c3e-7000-8000-000000000001'],
    'unknown address' => ['/icon-admin'],
]);

test('an unknown address with a session answers 404', function () {
    $this->withPasscodeSession()->get('/icon-admin')->assertNotFound();
});

test('the open routes answer without a session', function (string $uri) {
    $this->get($uri)->assertOk();
})->with([
    'version' => ['/api/version'],
    'health' => ['/up'],
]);

test('an address close to the version route answers 404', function (string $uri) {
    $this->get($uri)->assertNotFound();
})->with([
    'sub-address' => ['/api/version/x'],
    'plural' => ['/api/versions'],
]);

test('a session opened with a former passcode is redirected to the login page', function () {
    $this->withSession(['access.passcode' => Passcode::fingerprint()]);

    config(['app.passcode' => 'eagle-2027']);

    $this->get('/')->assertRedirect('/login');
});

describe('a write without a session', function () {
    beforeEach(function () {
        $this->writes = 0;

        $write = function () {
            $this->writes++;

            return response()->noContent();
        };

        Route::middleware('web')->post('/test-write', $write);
        Route::middleware('web')->put('/test-write', $write);
    });

    test('a JSON client gets 401 and nothing is written', function () {
        $this->postJson('/test-write')
            ->assertUnauthorized()
            ->assertExactJson(['error' => 'Session requise']);

        expect($this->writes)->toBe(0);
    });

    test('an Inertia visit is redirected to the login page and nothing is written', function () {
        $this->withHeader('X-Inertia', 'true')
            ->post('/test-write')
            ->assertRedirect('/login');

        expect($this->writes)->toBe(0);
    });

    test('an Inertia PUT visit gets a 303 to the login page and nothing is written', function () {
        // HandleInertiaRequests runs inside RequirePasscode and never sees this redirect: the global
        // Inertia\Middleware\EnsureGetOnRedirect turns the 302 into the 303 the client follows
        // with a GET.
        $this->withHeader('X-Inertia', 'true')
            ->put('/test-write')
            ->assertStatus(303)
            ->assertHeader('Location', url('/login'));

        expect($this->writes)->toBe(0);
    });

    test('a stale CSRF token gets 401, not 419', function () {
        // PreventRequestForgery skips its check while unit tests run: this subclass keeps it on.
        $this->app->bind(PreventRequestForgery::class, fn ($app) => new class($app, $app['encrypter']) extends PreventRequestForgery
        {
            protected function runningUnitTests(): bool
            {
                return false;
            }
        });

        $this->withHeader('X-CSRF-TOKEN', 'token-of-the-former-session')
            ->postJson('/test-write')
            ->assertUnauthorized()
            ->assertExactJson(['error' => 'Session requise']);

        expect($this->writes)->toBe(0);
    });
});
