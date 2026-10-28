<?php

/*
 * Mutations that turn these tests red:
 * - a unique index on name instead of lower(name): « gab » is inserted next to « Gab »;
 * - a unique index on alias instead of lower(alias): « manu » is inserted next to « MANU »;
 * - the player_aliases foreign key without cascade: deleting the player is refused.
 */

use App\Models\Player;
use App\Models\PlayerAlias;
use Illuminate\Database\UniqueConstraintViolationException;

test('two players cannot share a name whatever the case', function () {
    Player::factory()->create(['name' => 'Gab']);

    expect(fn () => Player::factory()->create(['name' => 'gab']))
        ->toThrow(UniqueConstraintViolationException::class);
});

test('two aliases cannot share a spelling whatever the case', function () {
    PlayerAlias::factory()->for(Player::factory()->create(['name' => 'Manu']))->create(['alias' => 'MANU']);
    $sean = Player::factory()->create(['name' => 'Sean']);

    expect(fn () => PlayerAlias::factory()->for($sean)->create(['alias' => 'manu']))
        ->toThrow(UniqueConstraintViolationException::class);
});

test('deleting a player deletes its aliases', function () {
    $manu = Player::factory()->withAlias()->create(['name' => 'Manu']);
    $sean = Player::factory()->withAlias()->create(['name' => 'Sean']);

    $manu->delete();

    expect(PlayerAlias::query()->pluck('alias')->all())->toBe(['SEAN'])
        ->and($sean->aliases()->pluck('alias')->all())->toBe(['SEAN']);
});
