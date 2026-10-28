<?php

/*
 * Mutation that turns these tests red: in config/session.php, set 'lifetime' => 120: the
 * session opened 89 days earlier has expired and the home page redirects to /login.
 */

beforeEach(function () {
    config(['session.driver' => 'database']);
});

test('each request starts the 90 days again', function () {
    $sessionId = $this->logInWithPasscode();

    $this->travel(89)->days();
    $this->getWithSessionCookie('/', $sessionId)->assertOk();

    // 178 days after login, 89 days after the last request.
    $this->travel(89)->days();
    $this->getWithSessionCookie('/', $sessionId)->assertOk();
});

test('a session left 91 days without a request is refused', function () {
    $sessionId = $this->logInWithPasscode();

    $this->travel(91)->days();

    $this->getWithSessionCookie('/', $sessionId)->assertRedirect('/login');
});
