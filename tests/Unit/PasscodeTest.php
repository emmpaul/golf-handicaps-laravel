<?php

/*
 * Mutation that turns these tests red: in App\Support\Passcode::matches(), compare with
 * strcasecmp($input, config('app.passcode')) === 0: Birdie-2026 is then accepted.
 */

use App\Support\Passcode;
use Tests\TestCase;

// Passcode lit config('app.passcode') et config('app.key') : l'application doit être démarrée.
pest()->extend(TestCase::class);

beforeEach(function () {
    config(['app.passcode' => 'birdie-2026']);
});

test('the configured passcode matches', function () {
    expect(Passcode::matches('birdie-2026'))->toBeTrue();
});

test('another case or an empty input does not match', function (string $input) {
    expect(Passcode::matches($input))->toBeFalse();
})->with([
    'other case' => ['Birdie-2026'],
    'empty' => [''],
]);

test('the fingerprint changes with the passcode', function () {
    $before = Passcode::fingerprint();

    config(['app.passcode' => 'eagle-2027']);

    expect(Passcode::fingerprint())->not->toBe($before);
});

test('the fingerprint does not contain the passcode', function () {
    expect(Passcode::fingerprint())->not->toContain('birdie-2026');
});
