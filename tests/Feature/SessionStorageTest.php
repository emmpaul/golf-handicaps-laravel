<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('the migrated schema has no account tables', function () {
    expect(Schema::hasTable('users'))->toBeFalse()
        ->and(Schema::hasTable('password_reset_tokens'))->toBeFalse();
});

test('the sessions table holds no person identifier', function () {
    expect(Schema::getColumnListing('sessions'))
        ->toEqualCanonicalizing(['id', 'ip_address', 'user_agent', 'payload', 'last_activity']);
});

test('a web request writes its session to the database', function () {
    config(['session.driver' => 'database']);

    $this->withPasscodeSession()->withHeader('User-Agent', 'golf-handicaps-test')->get('/')->assertOk();

    $rows = DB::table('sessions')->get();

    expect($rows)->toHaveCount(1)
        ->and($rows[0]->ip_address)->toBe('127.0.0.1')
        ->and($rows[0]->user_agent)->toBe('golf-handicaps-test');
});

// Mutation that turns this test red: in config/session.php, set 'encrypt' => false: the
// decoded payload shows _token and the access key in clear.
test('the session row after login reads nothing in clear', function () {
    config(['session.driver' => 'database']);

    $this->post('/login', ['passcode' => 'birdie-2026'])->assertRedirect('/');

    $rows = DB::table('sessions')->get();
    expect($rows)->toHaveCount(1);

    // The database handler stores the payload base64 encoded: the check reads it decoded.
    $payload = base64_decode($rows[0]->payload);
    expect($payload)
        ->not->toContain('birdie-2026')
        ->not->toContain('access')
        ->not->toContain('passcode')
        ->not->toContain('_token');
});
