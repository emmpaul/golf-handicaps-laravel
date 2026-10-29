<?php

/*
 * Spec handicap-index, page « Calcul de l'index »: « Étape 01, score ajusté », « Étape 02,
 * différentiel », « Liste des 20 derniers rounds de l'étape 03 », « Étape 05, index »; design
 * D2 and D3 of index-computation-page.
 *
 * Mutations that turn these tests red:
 * - the rounds sorted from the oldest (played_on asc): the round of 2026-10-01 comes first;
 * - limit(21) instead of 20: 21 rounds come back, the oldest of 2026-09-01 among them;
 * - the rating join without its lower(trim(...)): the round on « Bethpage Black / RED » reads no
 *   rating and the coherence test recomputes 170 instead of 130;
 * - a filter on status = 'confirmed' in the join, which RecomputePlayerStats does not have: the
 *   relaunched row, pending with its rating 72,0 / 130 still applied, reads null and the
 *   coherence test recomputes 10 instead of 0;
 * - the capped hole sorted by hole number alone: hole 1 at 9 instead of hole 2 at 10;
 * - the capped hole sorted by overage before the date: hole 5 (11, par 4) of 2026-10-10 instead
 *   of hole 1 of the latest round;
 * - the capped hole looked for on every round, not the 20 latest: the oldest round's hole 1;
 * - the mean taken over every differential instead of those that count: 6 rounds give 160;
 * - the adjustment of the provisional case taken from the line of 3: 2 rounds give -20.
 */

use App\Domain\Courses\CourseRatingStatus;
use App\Domain\Handicaps\CourseRating as Rating;
use App\Domain\Handicaps\Queries\PlayerIndexComputation;
use App\Domain\Handicaps\Whs;
use App\Models\CourseRating;
use App\Models\Round;
use App\Models\RoundHole;
use App\Models\Score;

/**
 * Manu's strokes of the reference card IMG_3064 (88), with the holes of $holes (hole number =>
 * strokes) changed.
 *
 * @param  array<int, int>  $holes
 * @return list<int>
 */
function manuReferenceStrokesWith(array $holes): array
{
    $strokes = referenceCardData(null, null)['lines'][1]['strokes'];
    foreach ($holes as $hole => $holeStrokes) {
        $strokes[$hole - 1] = $holeStrokes;
    }

    return $strokes;
}

/**
 * Rounds played one day after the other from 2026-09-01 by $player, $over strokes over par each.
 *
 * @param  list<int>  $overs
 */
function createDailyRoundsOf(string $playerId, array $overs): void
{
    foreach ($overs as $day => $over) {
        createRoundCard(referenceCardOn(sprintf('2026-09-%02d', $day + 1), [[$playerId, strokesOverPar($over)]]));
    }
}

/**
 * Saves the reference card alone for Manu of the running test on $playedOn, with Manu's strokes
 * changed on the holes of $holes (hole number => strokes).
 *
 * @param  array<int, int>  $holes
 */
function createManuRoundOn(string $playedOn, array $holes): Round
{
    return createRoundCard(referenceCardOn($playedOn, [[test()->manu->id, manuReferenceStrokesWith($holes)]]));
}

/**
 * The capped hole that PlayerIndexComputation gives for $playerId.
 *
 * @return array{hole: int, par: int, strokes: int}|null
 */
function cappedHoleOf(string $playerId): ?array
{
    return app(PlayerIndexComputation::class)->get($playerId)['cappedHole'];
}

beforeEach(function () {
    createPlayersOnTest('Gab', 'Manu', 'Sean');
});

describe('rounds', function () {
    test('the rounds go from the latest to the oldest with their facts', function () {
        [$first, $second, $third] = createRoundsOfOctober($this->gab->id);

        expect(app(PlayerIndexComputation::class)->get($this->gab->id)['rounds'])->toBe([
            [
                'roundId' => $third->id,
                'playedOn' => '2026-10-17',
                'courseName' => 'BETHPAGE BLACK',
                'gross' => 83,
                'adjusted' => 83,
                'parTotal' => 71,
                'ratingT' => null,
                'slope' => null,
                'differentialT' => 120,
                'countsInCurrent' => true,
            ],
            [
                'roundId' => $second->id,
                'playedOn' => '2026-10-10',
                'courseName' => 'BETHPAGE BLACK',
                'gross' => 88,
                'adjusted' => 88,
                'parTotal' => 71,
                'ratingT' => null,
                'slope' => null,
                'differentialT' => 170,
                'countsInCurrent' => false,
            ],
            [
                'roundId' => $first->id,
                'playedOn' => '2026-10-01',
                'courseName' => 'BETHPAGE BLACK',
                'gross' => 91,
                'adjusted' => 91,
                'parTotal' => 71,
                'ratingT' => null,
                'slope' => null,
                'differentialT' => 200,
                'countsInCurrent' => false,
            ],
        ]);
    });

    test('a player with 21 rounds gets the 20 latest, without the oldest', function () {
        createDailyRoundsOf($this->manu->id, [0, ...array_fill(0, 20, 17)]);

        $rounds = app(PlayerIndexComputation::class)->get($this->manu->id)['rounds'];

        expect(count($rounds))->toBe(20)
            ->and($rounds[0]['playedOn'])->toBe('2026-09-21')
            ->and($rounds[19]['playedOn'])->toBe('2026-09-02');
    });

    test('a capped hole gives the gross and the adjusted score of the round', function () {
        createManuRoundOn('2026-10-17', [1 => 10]);

        $round = app(PlayerIndexComputation::class)->get($this->manu->id)['rounds'][0];

        expect([$round['gross'], $round['adjusted']])->toBe([91, 89]);
    });

    test('the rating and slope are those of the confirmed rating of the course and tees', function (string $rating, ?int $ratingT, ?int $slope) {
        match ($rating) {
            'confirmed' => CourseRating::factory()->bethpageRed()->create(),
            'proposed' => CourseRating::factory()->proposed()->create(),
            'none' => null,
        };
        createRoundCard(referenceCardData($this->gab->id, $this->manu->id));

        $round = app(PlayerIndexComputation::class)->get($this->gab->id)['rounds'][0];

        expect([$round['parTotal'], $round['ratingT'], $round['slope']])->toBe([71, $ratingT, $slope]);
    })->with([
        'no rating' => ['none', null, null],
        'confirmed' => ['confirmed', 714, 144],
        'only proposed' => ['proposed', null, null],
    ]);
});

describe('capped hole', function () {
    test('the capped hole of the reference card is hole 1 at 10, par 4', function () {
        createManuRoundOn('2026-10-17', [1 => 10]);

        expect(cappedHoleOf($this->manu->id))->toBe(['hole' => 1, 'par' => 4, 'strokes' => 10]);
    });

    test('a player without any hole above par + 4 has no capped hole', function () {
        createRoundCard(referenceCardData($this->gab->id, $this->manu->id));

        expect(cappedHoleOf($this->gab->id))->toBeNull();
    });

    test('the capped hole comes from the latest round that has one', function () {
        createManuRoundOn('2026-10-10', [5 => 11]);
        createManuRoundOn('2026-10-17', [1 => 10]);

        expect(cappedHoleOf($this->manu->id))->toBe(['hole' => 1, 'par' => 4, 'strokes' => 10]);
    });

    test('in that round the capped hole is the largest overage', function () {
        createManuRoundOn('2026-10-17', [1 => 9, 2 => 10]);

        expect(cappedHoleOf($this->manu->id))->toBe(['hole' => 2, 'par' => 4, 'strokes' => 10]);
    });

    test('on equal overage the capped hole is the lowest hole number', function () {
        // Hole 1 at 9 (par 4) and hole 4 at 10 (par 5): both five over par.
        createManuRoundOn('2026-10-17', [1 => 9, 4 => 10]);

        expect(cappedHoleOf($this->manu->id))->toBe(['hole' => 1, 'par' => 4, 'strokes' => 9]);
    });

    test('a capped hole older than the 20 latest rounds is not shown', function () {
        createManuRoundOn('2026-08-31', [1 => 10]);
        createDailyRoundsOf($this->manu->id, array_fill(0, 20, 17));

        expect(cappedHoleOf($this->manu->id))->toBeNull();
    });
});

describe('mean and adjustment', function () {
    test('the mean of the differentials retained and the adjustment of the WHS rule', function (array $overs, int $meanT, int $adjustmentT) {
        createDailyRoundsOf($this->gab->id, $overs);

        $computation = app(PlayerIndexComputation::class)->get($this->gab->id);

        expect([$computation['meanT'], $computation['adjustmentT']])->toBe([$meanT, $adjustmentT]);
    })->with([
        '6 rounds' => [[17, 12, 20, 15, 14, 18], 130, -10],
        '3 rounds' => [[17, 12, 20], 120, -20],
        '5 rounds' => [[17, 12, 20, 15, 14], 120, 0],
        '2 rounds, provisional' => [[17, 12], 145, 0],
        'cap' => [[63, 70, 65], 630, -20],
    ]);

    test('a player without round has no round, no capped hole, no mean and no adjustment', function () {
        createRoundCard(referenceCardData($this->gab->id, $this->manu->id));

        expect(app(PlayerIndexComputation::class)->get($this->sean->id))->toBe([
            'rounds' => [],
            'cappedHole' => null,
            'meanT' => null,
            'adjustmentT' => 0,
        ]);
    });
});

test('the rating and slope returned recompute the differential the history saved', function () {
    // Design D3: the join of the ratings is copied from RecomputePlayerStats::history(); this
    // test fails when the two diverge.
    CourseRating::factory()->bethpageRed()->create();
    // A relaunched lookup: pending again, the confirmed rating still applied.
    CourseRating::factory()->create([
        'course_name' => 'SANCTUARY COVE',
        'tees' => 'Blue',
        'rating_t' => 720,
        'slope' => 130,
        'source' => 'manual',
        'status' => CourseRatingStatus::Pending,
    ]);
    CourseRating::factory()->chooseTee()->create();

    createRoundCard(referenceCardData($this->gab->id, $this->manu->id));
    createManuStrokesInAnotherCase($this->manu);
    $relaunched = referenceCardOn('2026-10-03', [[$this->gab->id, strokesOverPar(1)], [$this->manu->id, strokesOverPar(9)]]);
    $relaunched['courseName'] = 'SANCTUARY COVE';
    $relaunched['tees'] = 'Blue';
    createRoundCard($relaunched);
    $unrated = referenceCardOn('2026-10-05', [[$this->gab->id, strokesOverPar(4)], [$this->sean->id, strokesOverPar(15)]]);
    $unrated['tees'] = 'Gold';
    createRoundCard($unrated);

    $checked = [];
    foreach ([$this->gab, $this->manu, $this->sean] as $player) {
        foreach (app(PlayerIndexComputation::class)->get($player->id)['rounds'] as $round) {
            $pars = RoundHole::query()->where('round_id', $round['roundId'])->orderBy('hole')->pluck('par')->all();
            $strokes = Score::query()->where('round_id', $round['roundId'])->where('player_id', $player->id)
                ->orderBy('hole')->pluck('strokes')->all();
            $rating = new Rating($round['ratingT'] ?? $round['parTotal'] * 10, $round['slope'] ?? 113);

            $checked[] = [$player->name, $round['playedOn'], Whs::differentialTenths($strokes, $pars, $rating), $round['differentialT']];
        }
    }

    // Every round of the base, each differential saved equal to the one recomputed.
    expect($checked)->toBe([
        ['Gab', '2026-10-17', 5, 5],
        ['Gab', '2026-10-05', 40, 40],
        ['Gab', '2026-10-03', 0, 0],
        ['Manu', '2026-10-24', 130, 130],
        ['Manu', '2026-10-17', 130, 130],
        ['Manu', '2026-10-03', 70, 70],
        ['Sean', '2026-10-05', 150, 150],
    ]);
});
