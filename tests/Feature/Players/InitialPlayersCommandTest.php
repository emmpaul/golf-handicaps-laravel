<?php

/*
 * Mutations that turn these tests red:
 * - duplicates compared with their case: Gab,gab reaches the unique index and fails with
 *   « Ce joueur existe déjà », a message that does not name INITIAL_PLAYERS;
 * - creation outside the transaction: Gab stays in the base after Gab,gab fails;
 * - no check that the base is empty: Alex and Sam are added next to Gab;
 * - empty names kept: Gab,,  ,Sean fails on a blank name.
 */

use App\Models\Player;

test('INITIAL_PLAYERS creates its players on an empty base, each with its upper case alias', function () {
    $this->loadConfigUnder('golf', ['INITIAL_PLAYERS' => 'Manu, Gab,Sean']);

    $this->artisan('golf:initial-players')->assertExitCode(0);

    expect(playersWithAliases())->toBe([
        'Gab' => ['GAB'],
        'Manu' => ['MANU'],
        'Sean' => ['SEAN'],
    ]);
});

test('empty names are ignored', function () {
    config(['golf.initial_players' => 'Gab,,  ,Sean']);

    $this->artisan('golf:initial-players')->assertExitCode(0);

    expect(playersWithAliases())->toBe(['Gab' => ['GAB'], 'Sean' => ['SEAN']]);
});

test('an empty or missing INITIAL_PLAYERS creates no player', function (?string $raw) {
    config(['golf.initial_players' => $raw]);

    $this->artisan('golf:initial-players')->assertExitCode(0);

    expect(Player::query()->count())->toBe(0);
})->with([
    'empty' => [''],
    'missing' => [null],
]);

test('a name given twice whatever the case fails, names INITIAL_PLAYERS and creates nobody', function () {
    config(['golf.initial_players' => 'Gab,gab']);

    $this->artisan('golf:initial-players')
        ->expectsOutputToContain('INITIAL_PLAYERS')
        ->assertExitCode(1);

    expect(Player::query()->count())->toBe(0);
});

test('a base that already has a player is left as it is', function () {
    Player::factory()->withAlias()->create(['name' => 'Gab']);
    config(['golf.initial_players' => 'Alex,Sam']);

    $this->artisan('golf:initial-players')->assertExitCode(0);

    expect(playersWithAliases())->toBe(['Gab' => ['GAB']]);
});

test('a name given twice fails even on a base that already has a player', function () {
    Player::factory()->withAlias()->create(['name' => 'Gab']);
    config(['golf.initial_players' => 'Alex,alex']);

    $this->artisan('golf:initial-players')
        ->expectsOutputToContain('INITIAL_PLAYERS')
        ->assertExitCode(1);

    expect(playersWithAliases())->toBe(['Gab' => ['GAB']]);
});
