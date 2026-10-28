<?php

/*
 * Mutation that turns these tests red: in App\Support\AppBuild::commit(), accept
 * 7 characters ('/^[0-9a-f]{7,40}$/'): the short sha 4794e1b is then kept.
 */

use App\Support\AppBuild;
use Tests\TestCase;

// AppBuild lit config('app.version') et config('app.commit') : l'application doit être démarrée.
pest()->extend(TestCase::class);

test('the version is read from the configuration', function () {
    config(['app.version' => '0.6.0']);

    expect(AppBuild::version())->toBe('0.6.0');
});

test('a full lowercase 40 character sha is kept', function () {
    config(['app.commit' => '4794e1b0c0ffee00000000000000000000000000']);

    expect(AppBuild::commit())->toBe('4794e1b0c0ffee00000000000000000000000000');
});

test('a malformed or missing commit is reported as null', function (?string $commit) {
    config(['app.commit' => $commit]);

    expect(AppBuild::commit())->toBeNull();
})->with([
    'short sha' => ['4794e1b'],
    'uppercase sha' => ['4794E1B0C0FFEE00000000000000000000000000'],
    'missing' => [null],
]);
