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

    $this->withHeader('User-Agent', 'golf-handicaps-test')->get('/')->assertOk();

    $rows = DB::table('sessions')->get();

    expect($rows)->toHaveCount(1)
        ->and($rows[0]->ip_address)->toBe('127.0.0.1')
        ->and($rows[0]->user_agent)->toBe('golf-handicaps-test');
});
