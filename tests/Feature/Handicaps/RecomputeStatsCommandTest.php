<?php

/*
 * Mutations that turn these tests red:
 * - the rows of a player not removed before the insert: the second run hits the unique
 *   (round_id, player_id) and fails;
 * - the command that exits with 1 when no player has a round: the empty base fails.
 */

use App\Models\Player;
use App\Models\PlayerRoundStat;

/**
 * @return list<array<string, mixed>> the rows of player_round_stats without their id
 */
function statRows(): array
{
    return array_values(PlayerRoundStat::query()->orderBy('gross')
        ->get(['round_id', 'player_id', 'gross', 'adjusted', 'differential_t', 'index_before_t', 'index_after_t', 'counts_in_current'])
        ->toArray());
}

test('a base without history gets the differentials of the reference card', function () {
    $gab = Player::factory()->create(['name' => 'Gab']);
    $manu = Player::factory()->create(['name' => 'Manu']);
    Player::factory()->create(['name' => 'Sean']);
    createRoundCard(referenceCardData($gab->id, $manu->id));
    PlayerRoundStat::query()->delete();

    $this->artisan('golf:recompute-stats')
        ->expectsOutput('2 joueurs recalculés.')
        ->assertExitCode(0);

    expect(PlayerRoundStat::query()->orderBy('gross')->get(['player_id', 'differential_t'])->toArray())->toBe([
        ['player_id' => $gab->id, 'differential_t' => 10],
        ['player_id' => $manu->id, 'differential_t' => 170],
    ]);
});

test('two runs give the same history', function () {
    $gab = Player::factory()->create(['name' => 'Gab']);
    $manu = Player::factory()->create(['name' => 'Manu']);
    createRoundCard(referenceCardOn('2026-10-10', [[$manu->id, strokesOverPar(17)]]));
    createRoundCard(referenceCardData($gab->id, $manu->id));

    $this->artisan('golf:recompute-stats')->assertExitCode(0);
    $first = statRows();
    $this->artisan('golf:recompute-stats')->assertExitCode(0);

    expect($first)->toHaveCount(3)
        ->and(statRows())->toBe($first);
});

test('a base without round writes nothing and exits with 0', function () {
    Player::factory()->create(['name' => 'Gab']);

    $this->artisan('golf:recompute-stats')
        ->expectsOutput('0 joueur recalculé.')
        ->assertExitCode(0);

    expect(PlayerRoundStat::query()->count())->toBe(0);
});
