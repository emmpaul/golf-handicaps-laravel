<?php

/*
 * Spec handicap-index (visual-redesign), « Statistiques de jeu d'un joueur », « Règles de calcul
 * des statistiques », « Répartition des trous », « Moyennes par par »; design D13.
 *
 * Mutations that turn these tests red:
 * - the par of the latest round on the course instead of the par of the round (a join on the
 *   course name): Sean's later round with other pars changes Gab's classes;
 * - the rounding by truncation (intdiv(10 × sum, n)): Manu's par 3 reads 32 instead of 33;
 * - the tint decided on the rounded delta (5,3 − 5 = +0,3 → good): Gab's par 5 on two rounds
 *   reads good instead of neutral;
 * - 6 instead of +2 and more grouped as « Double bogey + » (strokes - par >= 3): Gab's classes on
 *   two rounds read [0, 4, 16, 11, 2];
 * - a window of the 20 latest rounds (limit 20): Manu's 21 rounds read 360 holes;
 * - the player filter removed: Sean's round changes Gab's values.
 */

use App\Domain\Handicaps\Queries\PlayerScoringStats;
use App\Domain\Handicaps\ScoreTint;

beforeEach(function () {
    createPlayersOnTest('Gab', 'Manu', 'Sean');
    $this->gabStrokes = referenceCardData(null, null)['lines'][0]['strokes'];
    $this->manuStrokes = referenceCardData(null, null)['lines'][1]['strokes'];
});

test('the stats of Gab on the reference card alone', function () {
    createRoundCard(referenceCardOn('2026-10-17', [[$this->gab->id, $this->gabStrokes]]));

    expect(app(PlayerScoringStats::class)->get($this->gab->id))->toBe([
        'rounds' => 1,
        'holes' => 18,
        'averageGrossT' => 720,
        'bestGross' => 72,
        'parOrBetterPercent' => 78,
        'classes' => [0, 3, 11, 4, 0],
        'classPercents' => [0, 17, 61, 22, 0],
        'pars' => [
            ['par' => 3, 'holes' => 4, 'averageT' => 30, 'deltaT' => 0, 'tint' => ScoreTint::Good],
            ['par' => 4, 'holes' => 11, 'averageT' => 43, 'deltaT' => 3, 'tint' => ScoreTint::Good],
            ['par' => 5, 'holes' => 3, 'averageT' => 43, 'deltaT' => -7, 'tint' => ScoreTint::Good],
        ],
    ]);
});

test('the stats of Gab on the reference card and a second round with the strokes of Manu', function () {
    createRoundCard(referenceCardOn('2026-10-17', [[$this->gab->id, $this->gabStrokes]]));
    createRoundCard(referenceCardOn('2026-10-24', [[$this->gab->id, $this->manuStrokes]]));

    expect(app(PlayerScoringStats::class)->get($this->gab->id))->toBe([
        'rounds' => 2,
        'holes' => 36,
        'averageGrossT' => 800,
        'bestGross' => 72,
        'parOrBetterPercent' => 56,
        'classes' => [0, 4, 16, 11, 5],
        'classPercents' => [0, 11, 44, 31, 14],
        'pars' => [
            ['par' => 3, 'holes' => 8, 'averageT' => 31, 'deltaT' => 1, 'tint' => ScoreTint::Good],
            ['par' => 4, 'holes' => 22, 'averageT' => 47, 'deltaT' => 7, 'tint' => ScoreTint::Neutral],
            ['par' => 5, 'holes' => 6, 'averageT' => 53, 'deltaT' => 3, 'tint' => ScoreTint::Neutral],
        ],
    ]);
});

test('the averages by par of Manu round half up and turn red above one stroke', function () {
    createRoundCard(referenceCardOn('2026-10-17', [[$this->manu->id, $this->manuStrokes]]));

    expect(app(PlayerScoringStats::class)->get($this->manu->id)['pars'])->toBe([
        ['par' => 3, 'holes' => 4, 'averageT' => 33, 'deltaT' => 3, 'tint' => ScoreTint::Good],
        ['par' => 4, 'holes' => 11, 'averageT' => 51, 'deltaT' => 11, 'tint' => ScoreTint::Bad],
        ['par' => 5, 'holes' => 3, 'averageT' => 63, 'deltaT' => 13, 'tint' => ScoreTint::Bad],
    ]);
});

test('a hole in one on a par 3 counts as an eagle or better', function () {
    $strokes = $this->gabStrokes;
    $strokes[2] = 1;
    createRoundCard(referenceCardOn('2026-10-17', [[$this->gab->id, $strokes]]));

    $stats = app(PlayerScoringStats::class)->get($this->gab->id);

    expect([$stats['classes'], $stats['classPercents'], $stats['averageGrossT']])
        ->toBe([[1, 3, 10, 4, 0], [6, 17, 56, 22, 0], 700]);
});

test('every round counts, not only the 20 of the index', function () {
    foreach (range(1, 21) as $day) {
        createRoundCard(referenceCardOn(sprintf('2026-09-%02d', $day), [[$this->manu->id, $this->manuStrokes]]));
    }

    expect(app(PlayerScoringStats::class)->get($this->manu->id))
        ->toMatchArray(['rounds' => 21, 'holes' => 378]);
});

test('a player without round has no stats', function () {
    createRoundCard(referenceCardOn('2026-10-17', [[$this->gab->id, $this->gabStrokes]]));

    expect(app(PlayerScoringStats::class)->get($this->sean->id))->toBeNull();
});

test('a later round of another player on the same course with other pars changes nothing for Gab', function () {
    createRoundCard(referenceCardOn('2026-10-17', [[$this->gab->id, $this->gabStrokes]]));
    $other = referenceCardOn('2026-10-24', [[$this->sean->id, array_fill(0, 18, 6)]]);
    $other['holes'][0]['par'] = 5;
    $other['holes'][1]['par'] = 3;
    createRoundCard($other);

    expect(app(PlayerScoringStats::class)->get($this->gab->id))->toMatchArray([
        'rounds' => 1,
        'holes' => 18,
        'averageGrossT' => 720,
        'bestGross' => 72,
        'parOrBetterPercent' => 78,
        'classes' => [0, 3, 11, 4, 0],
    ]);
});
