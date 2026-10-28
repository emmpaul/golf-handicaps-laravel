<?php

/*
 * Mutation that turns the shared app tests red: in App\Support\AppBuild::commit(), accept
 * 7 characters ('/^[0-9a-f]{7,40}$/'): the short sha 4794e1b then reaches the page.
 */

use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withPasscodeSession();
});

test('the home page responds', function () {
    $this->get('/')->assertOk();
});

test('the home page receives the version and the full commit sha', function () {
    config([
        'app.version' => '0.6.0',
        'app.commit' => '4794e1b0c0ffee00000000000000000000000000',
    ]);

    $this->get('/')->assertInertia(fn (Assert $page) => $page
        ->where('app', [
            'version' => '0.6.0',
            'commit' => '4794e1b0c0ffee00000000000000000000000000',
        ]));
});

test('the home page receives a null commit when the sha is malformed', function () {
    config([
        'app.version' => '0.6.0',
        'app.commit' => '4794e1b',
    ]);

    $this->get('/')->assertInertia(fn (Assert $page) => $page
        ->where('app', [
            'version' => '0.6.0',
            'commit' => null,
        ]));
});
