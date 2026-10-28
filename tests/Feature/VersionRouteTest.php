<?php

beforeEach(function () {
    config([
        'app.version' => '0.6.0',
        'app.commit' => '4794e1b0c0ffee00000000000000000000000000',
    ]);
});

test('the version route returns the version and the full commit sha', function () {
    $response = $this->getJson('/api/version');

    $response->assertOk();
    expect($response->json())->toBe([
        'version' => '0.6.0',
        'commit' => '4794e1b0c0ffee00000000000000000000000000',
    ]);
});

test('the version route forbids caching', function () {
    $response = $this->getJson('/api/version');

    expect($response->headers->hasCacheControlDirective('no-store'))->toBeTrue();
});

test('the version route returns a null commit when none is known', function () {
    config(['app.commit' => null]);

    expect($this->getJson('/api/version')->json())->toBe([
        'version' => '0.6.0',
        'commit' => null,
    ]);
});

test('the version route returns a null commit when the sha is malformed', function () {
    config(['app.commit' => '4794e1b']);

    expect($this->getJson('/api/version')->json('commit'))->toBeNull();
});

test('the version route reads the version from version.txt', function () {
    // Undo the beforeEach override: load the value config/app.php derives from the file.
    config(['app.version' => (require base_path('config/app.php'))['version']]);

    expect($this->getJson('/api/version')->json('version'))
        ->toBe(trim((string) file_get_contents(base_path('version.txt'))));
});

test('the version route sets no cookie for a visitor without one', function () {
    $this->get('/api/version')
        ->assertOk()
        ->assertHeaderMissing('Set-Cookie');
});

test('the version route answers HEAD without a body', function () {
    $response = $this->call('HEAD', '/api/version');

    $response->assertOk();
    expect($response->getContent())->toBe('');
});

test('the version route rejects POST with 405', function () {
    $this->postJson('/api/version')->assertStatus(405);
});

test('the version route answers while the database is unreachable', function () {
    config([
        'database.connections.unreachable' => [
            ...config('database.connections.pgsql'),
            'host' => 'db.invalid',
        ],
        'database.default' => 'unreachable',
    ]);

    $response = $this->getJson('/api/version');

    // RefreshDatabase rolls back the default connection on teardown.
    config(['database.default' => 'pgsql']);

    $response->assertOk();
    expect($response->json('version'))->toBe('0.6.0');
});
