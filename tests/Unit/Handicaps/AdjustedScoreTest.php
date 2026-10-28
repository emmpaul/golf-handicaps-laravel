<?php

/*
 * Port of tests/domain/handicap.test.ts of the Next.js application (adjusted score).
 *
 * Mutations that turn these tests red:
 * - the cap at par + 3 instead of par + 4: 10 on a par 4 counts 7, 10 on a par 5 counts 8;
 * - adjustedScore() that sums the gross strokes: Manu with 10 on hole 1 counts 91, not 89.
 */

use App\Domain\Handicaps\Whs;

test('a hole counts at most par + 4', function (int $strokes, int $par, int $adjusted) {
    expect(Whs::adjustedHoleScore($strokes, $par))->toBe($adjusted);
})->with([
    '10 on a par 4' => [10, 4, 8],
    '8 on a par 4' => [8, 4, 8],
    '7 on a par 4' => [7, 4, 7],
    '9 on a par 5' => [9, 5, 9],
    '10 on a par 5' => [10, 5, 9],
    '3 on a par 3' => [3, 3, 3],
]);

test('the adjusted score of the reference card', function (int $line, ?int $firstHole, int $adjusted) {
    $card = referenceCardData(null, null);
    $strokes = $card['lines'][$line]['strokes'];
    if ($firstHole !== null) {
        $strokes[0] = $firstHole;
    }

    expect(Whs::adjustedScore($strokes, array_column($card['holes'], 'par')))->toBe($adjusted);
})->with([
    'Gab' => [0, null, 72],
    'Manu' => [1, null, 88],
    'Manu with 10 on hole 1 (par 4)' => [1, 10, 89],
]);
