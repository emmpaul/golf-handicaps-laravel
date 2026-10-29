<?php

/*
 * Port of tests/server/home/summary.test.ts of the Next.js application. Spec home-dashboard,
 * « Index des joueurs »: « Dernier round par date de jeu », « Joueur sans round », « Premier
 * round »; design D1.
 *
 * Mutations that turn these tests red:
 * - the delta taken on the latest round saved instead of the latest played (the distinct on
 *   ordered by rounds.created_at first): Gab reads 155 and −15 instead of 158 and 3;
 * - deltaT 0 instead of null on a first round (index_before_t null read as 0 by
 *   `(int) $row->index_after_t - (int) $row->index_before_t`): Gab with one round reads a
 *   delta of 100 instead of null.
 */

use App\Domain\Handicaps\Queries\PlayerStandings;
use App\Models\Player;
use App\Models\PlayerRoundStat;
use Carbon\CarbonImmutable;

/**
 * Saves the reference card for $player on each date of $rounds, in this order, one hour apart,
 * then forces the index before and after each round: CreateRound recomputes the whole history
 * of the player, so the indexes are forced once every round is saved.
 *
 * @param  list<array{string, int|null, int}>  $rounds  [playedOn, index before, index after]
 */
function roundsWithIndexes(Player $player, array $rounds): void
{
    $saved = [];
    foreach ($rounds as $hour => [$playedOn]) {
        test()->travelTo(CarbonImmutable::parse('2026-10-18 10:00:00', 'UTC')->addHours($hour));
        $saved[] = createRoundCard(referenceCardOn($playedOn, [[$player->id, strokesOverPar(10)]]));
    }

    foreach ($rounds as $i => [, $beforeT, $afterT]) {
        PlayerRoundStat::query()
            ->where('round_id', $saved[$i]->id)
            ->update(['index_before_t' => $beforeT, 'index_after_t' => $afterT]);
    }
}

beforeEach(function () {
    createPlayersOnTest('Gab', 'Manu', 'Sean');
});

test('the index and delta come from the latest round played, not the latest saved', function () {
    // Saved in this order: the round of 2026-10-10 is the latest saved.
    roundsWithIndexes($this->gab, [
        ['2026-10-17', 155, 158],
        ['2026-10-01', null, 170],
        ['2026-10-10', 170, 155],
    ]);

    $gab = collect(app(PlayerStandings::class)->get())->firstWhere('name', 'Gab');

    expect($gab)->toBe([
        'id' => $this->gab->id,
        'name' => 'Gab',
        'indexT' => 158,
        'provisional' => false,
        'deltaT' => 3,
    ]);
});

test('a player without round has no index and no delta', function () {
    $sean = collect(app(PlayerStandings::class)->get())->firstWhere('name', 'Sean');

    expect($sean)->toBe([
        'id' => $this->sean->id,
        'name' => 'Sean',
        'indexT' => null,
        'provisional' => false,
        'deltaT' => null,
    ]);
});

test('a first round gives a provisional index without delta', function () {
    roundsWithIndexes($this->gab, [['2026-10-17', null, 100]]);

    $gab = collect(app(PlayerStandings::class)->get())->firstWhere('name', 'Gab');

    expect($gab['indexT'])->toBe(100)
        ->and($gab['deltaT'])->toBeNull()
        ->and($gab['provisional'])->toBeTrue();
});

test('the players are ordered by name whatever the case', function () {
    Player::factory()->create(['name' => 'alice']);

    expect(array_column(app(PlayerStandings::class)->get(), 'name'))->toBe(['alice', 'Gab', 'Manu', 'Sean']);
});
