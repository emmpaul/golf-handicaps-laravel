<?php

/*
 * Spec performance-measurement (optimize-performance), « Temps du serveur dans chaque réponse »;
 * design D1.
 *
 * Mutations that turn these tests red:
 * - bind QueryClock with singleton() instead of scoped(): the clock survives the start of the
 *   next request, whose db-count then adds up the queries of the previous one;
 * - name the count metric db_count instead of db-count: no db-count;dur= in the header;
 * - add AddServerTiming to the web group instead of the global middleware: /api/version has no
 *   Server-Timing header.
 *
 * Classic FrankenPHP starts each request in a new process, and Octane forgets the scoped
 * instances when a request comes in: requestInNewLifecycle() forgets them the same way, so the
 * queries run by the test setup (migrations, factories) are not counted.
 */

use App\Models\Player;
use Illuminate\Testing\TestResponse;

function requestInNewLifecycle(string $uri): TestResponse
{
    app()->forgetScopedInstances();

    return test()->get($uri);
}

test('a section page with a session carries the three metrics as numbers', function () {
    $this->withPasscodeSession();

    $header = requestInNewLifecycle('/rounds')->assertOk()->headers->get('Server-Timing');

    expect($header)->toMatch('/^app;dur=\d+(\.\d+)?, db;dur=\d+(\.\d+)?, db-count;dur=\d+$/');
});

test('a response without any SQL query counts zero queries', function () {
    $header = requestInNewLifecycle('/api/version')->assertOk()->headers->get('Server-Timing');

    expect($header)->toContain('db;dur=0,')
        ->and($header)->toEndWith('db-count;dur=0');
});

test('the redirect to the login page carries the header too', function () {
    $response = requestInNewLifecycle('/rounds');

    $response->assertRedirect('/login');
    expect($response->headers->get('Server-Timing'))
        ->toMatch('/^app;dur=\d+(\.\d+)?, db;dur=\d+(\.\d+)?, db-count;dur=\d+$/');
});

test('the header carries no data, no SQL and no table name', function () {
    Player::factory()->create(['name' => 'Zoé']);
    $this->withPasscodeSession();

    $header = requestInNewLifecycle('/players')->assertOk()->headers->get('Server-Timing');

    expect($header)->toStartWith('app;dur=')
        ->and($header)->not->toContain('Zoé')
        ->and(strtolower($header))->not->toContain('select')
        ->and($header)->not->toContain('players');
});

test('the second request does not add up the queries of the first', function () {
    $this->withPasscodeSession();
    $first = requestInNewLifecycle('/rounds')->assertOk()->headers->get('Server-Timing');
    expect($first)->not->toEndWith('db-count;dur=0');

    $second = requestInNewLifecycle('/api/version')->assertOk()->headers->get('Server-Timing');

    expect($second)->toEndWith('db-count;dur=0');
});
