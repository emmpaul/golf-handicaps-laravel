<?php

/*
 * Mutations that turn this test red:
 * - DevelopmentPlayersSeeder that passes config('golf.initial_players') to
 *   CreateInitialPlayers instead of its own list: the base gets Alex;
 * - DatabaseSeeder that also calls DemoRoundsSeeder: the base gets rounds.
 */

use App\Models\Player;
use App\Models\PlayerAlias;
use App\Models\Round;

test('the default seeding writes the development players whatever INITIAL_PLAYERS says, and no round', function () {
    config(['golf.initial_players' => 'Alex']);

    $this->seed();

    expect(Player::query()->orderBy('name')->pluck('name')->all())->toBe(['Gab', 'Manu', 'Sean'])
        ->and(PlayerAlias::query()->orderBy('alias')->pluck('alias')->all())->toBe(['GAB', 'MANU', 'SEAN'])
        ->and(Round::query()->count())->toBe(0);
});
