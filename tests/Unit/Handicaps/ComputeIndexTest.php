<?php

/*
 * Port of tests/domain/handicap-index.test.ts of the Next.js application.
 *
 * Mutations that turn these tests red:
 * - a wrong line of the WHS table (6 rounds without the − 1,0 adjustment): 6 rounds give 130;
 * - no window of 20 rounds: the oldest round (0,0) counts and 21 rounds give 105;
 * - createdAt ignored: on the same day, the rounds keep the reversed order of the list, the
 *   oldest (0,0) stays in and 21 rounds give 109;
 * - the index rounded down (floor without + 0.5): 13,25 gives 132;
 * - no cap at 540: 63,0, 70,0 and 65,0 give 610;
 * - the worst differentials retained and marked: 3 rounds give 180.
 */

use App\Domain\Handicaps\RoundDifferential;
use App\Domain\Handicaps\Whs;

/**
 * Rounds played one day after the other, in the order of the list.
 *
 * @param  list<int>  $differentials
 * @return list<RoundDifferential>
 */
function dailyHistory(array $differentials): array
{
    return array_map(
        fn (int $differentialT, int $i): RoundDifferential => new RoundDifferential(
            sprintf('2026-01-%02d', $i + 1),
            '2026-02-01T10:00:00.000000+00:00',
            $differentialT,
        ),
        $differentials,
        array_keys($differentials),
    );
}

/** 20 distinct differentials; the first n make a history of n rounds. */
const TWENTY_DIFFERENTIALS = [
    170, 120, 200, 150, 140, 180, 160, 130, 190, 110, 175, 125, 165, 135, 185, 115, 155, 145, 195,
    105,
];

test('the WHS table gives the index of n rounds', function (int $n, int $indexT, bool $provisional) {
    $index = Whs::computeIndex(dailyHistory(array_slice(TWENTY_DIFFERENTIALS, 0, $n)));

    expect([$index->indexT, $index->provisional])->toBe([$indexT, $provisional]);
})->with([
    [1, 170, true],
    [2, 145, true],
    [3, 100, false],
    [4, 110, false],
    [5, 120, false],
    [6, 120, false],
    [7, 130, false],
    [8, 125, false],
    [9, 130, false],
    [10, 120, false],
    [11, 120, false],
    [12, 121, false],
    [13, 121, false],
    [14, 121, false],
    [15, 124, false],
    [16, 120, false],
    [17, 123, false],
    [18, 123, false],
    [19, 125, false],
    [20, 123, false],
]);

test('the scenarios of the spec', function (array $differentials, ?int $indexT, bool $provisional) {
    $index = Whs::computeIndex(dailyHistory($differentials));

    expect([$index->indexT, $index->provisional])->toBe([$indexT, $provisional]);
})->with([
    'three rounds' => [[170, 120, 200], 100, false],
    'six rounds' => [[170, 120, 200, 150, 140, 180], 120, false],
    'two rounds' => [[170, 120], 145, true],
    'no round' => [[], null, false],
    'rounding of 13,25' => [[140, 145, 200, 200, 200, 200], 133, false],
    'cap of 61,0' => [[630, 700, 650], 540, false],
    'negative index' => [[-10, 50, 60], -30, false],
]);

/**
 * @return array<string, array{list<RoundDifferential>}>
 */
function twentyOneRounds(): array
{
    $inOrder = dailyHistory([0, ...TWENTY_DIFFERENTIALS]);
    $sameDayByCreation = array_reverse(array_map(
        fn (RoundDifferential $round, int $i): RoundDifferential => new RoundDifferential(
            '2026-03-01',
            sprintf('2026-03-01T10:%02d:00.000000+00:00', $i),
            $round->differentialT,
        ),
        $inOrder,
        array_keys($inOrder),
    ));

    return [
        'in order' => [$inOrder],
        'given in reverse order' => [array_reverse($inOrder)],
        'on the same day, ordered by createdAt' => [$sameDayByCreation],
    ];
}

test('21 rounds: the oldest (0,0) is left out', function (array $rounds) {
    $index = Whs::computeIndex($rounds);

    $retained = [];
    foreach ($rounds as $i => $round) {
        if ($index->counts[$i]) {
            $retained[] = $round->differentialT;
        }
    }
    sort($retained);

    expect($index->indexT)->toBe(123)
        ->and($retained)->toBe([105, 110, 115, 120, 125, 130, 135, 140]);
})->with(twentyOneRounds());

test('the retained differentials are marked', function (array $differentials, array $counts) {
    expect(Whs::computeIndex(dailyHistory($differentials))->counts)->toBe($counts);
})->with([
    '6 rounds: the 2 best' => [[170, 120, 200, 150, 140, 180], [false, true, false, false, true, false]],
    '2 provisional rounds: both' => [[170, 120], [true, true]],
    'no round' => [[], []],
]);
