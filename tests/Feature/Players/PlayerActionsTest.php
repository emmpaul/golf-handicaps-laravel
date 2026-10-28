<?php

/*
 * Mutations that turn these tests red:
 * - AssignAlias without deleting the alias first: the insert of SEANP for Sean violates the
 *   unique index while Manu still carries it;
 * - RenamePlayer that checks the name against every player, itself included: renaming Manu
 *   to MANU is refused;
 * - AddPlayer that lets UniqueConstraintViolationException through instead of DuplicatePlayer.
 */

use App\Domain\Players\Actions\AddPlayer;
use App\Domain\Players\Actions\AssignAlias;
use App\Domain\Players\Actions\RemoveAlias;
use App\Domain\Players\Actions\RenamePlayer;
use App\Domain\Players\Exceptions\DuplicatePlayer;
use App\Models\Player;
use App\Models\PlayerAlias;

test('adding a player creates it without alias', function () {
    $alex = app(AddPlayer::class)->handle('Alex');

    expect(Player::query()->pluck('name')->all())->toBe(['Alex'])
        ->and($alex->aliases()->count())->toBe(0);
});

test('adding a name taken whatever the case is refused', function () {
    Player::factory()->create(['name' => 'Gab']);

    expect(fn () => app(AddPlayer::class)->handle('gab'))
        ->toThrow(DuplicatePlayer::class, 'Ce joueur existe déjà');

    expect(Player::query()->pluck('name')->all())->toBe(['Gab']);
});

test('a player can be renamed to its own name in another case', function () {
    $manu = Player::factory()->create(['name' => 'Manu']);

    app(RenamePlayer::class)->handle($manu, 'MANU');

    expect($manu->fresh()->name)->toBe('MANU');
});

test('renaming a player to a name taken by another is refused', function () {
    Player::factory()->create(['name' => 'Gab']);
    $manu = Player::factory()->create(['name' => 'Manu']);

    expect(fn () => app(RenamePlayer::class)->handle($manu, 'GAB'))
        ->toThrow(DuplicatePlayer::class, 'Ce joueur existe déjà');

    expect($manu->fresh()->name)->toBe('Manu');
});

test('assigning an alias moves it from the player who carried it', function () {
    $manu = Player::factory()->withAlias()->create(['name' => 'Manu']);
    PlayerAlias::factory()->for($manu)->create(['alias' => 'SEANP']);
    $sean = Player::factory()->withAlias()->create(['name' => 'Sean']);

    app(AssignAlias::class)->handle($sean, 'SeanP');

    expect($manu->aliases()->orderBy('alias')->pluck('alias')->all())->toBe(['MANU'])
        ->and($sean->aliases()->orderBy('alias')->pluck('alias')->all())->toBe(['SEAN', 'SEANP']);
});

test('removing an alias deletes it', function () {
    $manu = Player::factory()->withAlias()->create(['name' => 'Manu']);
    $alias = PlayerAlias::factory()->for($manu)->create(['alias' => 'SEANP']);

    app(RemoveAlias::class)->handle($alias);

    expect($manu->aliases()->pluck('alias')->all())->toBe(['MANU']);
});
