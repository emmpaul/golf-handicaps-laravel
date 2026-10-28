<?php

/*
 * Mutation that turns these tests red: in LoginController::destroy(), replace
 * $request->session()->invalidate() with DB::table('sessions')->delete(): the session of the
 * other browser disappears too and its home page redirects to /login.
 */

use Illuminate\Support\Facades\DB;

beforeEach(function () {
    config(['session.driver' => 'database']);
});

test('logging out closes the session of this browser only', function () {
    $phoneSessionId = $this->logInWithPasscode();
    $computerSessionId = $this->logInWithPasscode();

    $this->postWithSessionCookie('/logout', $phoneSessionId)->assertRedirect('/login');

    expect(DB::table('sessions')->where('id', $phoneSessionId)->exists())->toBeFalse();
    $this->getWithSessionCookie('/', $phoneSessionId)->assertRedirect('/login');
    $this->getWithSessionCookie('/', $computerSessionId)->assertOk();
});

test('a JSON logout without a session gets 401', function () {
    $this->postJson('/logout')
        ->assertUnauthorized()
        ->assertExactJson(['error' => 'Session requise']);
});
