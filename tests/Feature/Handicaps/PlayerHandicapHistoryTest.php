<?php

/*
 * Spec handicap-index, « Liste des rounds d'un joueur »: « Lignes de Gab »; design D4.
 *
 * Mutations that turn these tests red:
 * - the history sorted from the oldest round (played_on asc): the round of 2026-10-01 comes
 *   first;
 * - differential_t and index_after_t swapped in the select: the round of 2026-10-10 reads a
 *   differential of 185 and an index of 170.
 */

use App\Domain\Handicaps\Queries\PlayerHandicapHistory;

beforeEach(function () {
    createPlayersOnTest('Gab', 'Sean');
});

test('the rounds of a player go from the latest to the oldest with their differential and index', function () {
    $first = createRoundCard(referenceCardOn('2026-10-01', [[$this->gab->id, strokesOverPar(20)]]));
    $second = createRoundCard(referenceCardOn('2026-10-10', [[$this->gab->id, strokesOverPar(17)]]));
    $third = createRoundCard(referenceCardOn('2026-10-17', [[$this->gab->id, strokesOverPar(12)]]));

    expect(app(PlayerHandicapHistory::class)->get($this->gab->id))->toBe([
        [
            'roundId' => $third->id,
            'playedOn' => '2026-10-17',
            'courseName' => 'BETHPAGE BLACK',
            'tees' => 'Red',
            'differentialT' => 120,
            'indexAfterT' => 100,
            'countsInCurrent' => true,
        ],
        [
            'roundId' => $second->id,
            'playedOn' => '2026-10-10',
            'courseName' => 'BETHPAGE BLACK',
            'tees' => 'Red',
            'differentialT' => 170,
            'indexAfterT' => 185,
            'countsInCurrent' => false,
        ],
        [
            'roundId' => $first->id,
            'playedOn' => '2026-10-01',
            'courseName' => 'BETHPAGE BLACK',
            'tees' => 'Red',
            'differentialT' => 200,
            'indexAfterT' => 200,
            'countsInCurrent' => false,
        ],
    ]);
});

test('a player without round has no history', function () {
    createRoundCard(referenceCardOn('2026-10-01', [[$this->gab->id, strokesOverPar(20)]]));

    expect(app(PlayerHandicapHistory::class)->get($this->sean->id))->toBe([]);
});
