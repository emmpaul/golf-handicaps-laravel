<?php

/*
 * Port of the differentialTenths cases of tests/domain/handicap.test.ts of the Next.js
 * application, on the reference card IMG_3064 (par 71).
 *
 * Mutations that turn these tests red:
 * - slope and 113 swapped: Gab gets 8 and Manu 212 on 71,4 / 144;
 * - the rating ignored (par × 10 used instead): Gab gets 8 and Manu 133 on 71,4 / 144;
 * - floor without + 0.5: Gab gets 4 on 71,4 / 144.
 */

use App\Domain\Handicaps\CourseRating;
use App\Domain\Handicaps\Whs;

/**
 * @return list<int> the strokes of a line of the reference card, hole 1 replaced when given
 */
function referenceStrokes(int $line, ?int $firstHole = null): array
{
    $strokes = referenceCardData(null, null)['lines'][$line]['strokes'];
    if ($firstHole !== null) {
        $strokes[0] = $firstHole;
    }

    return $strokes;
}

/**
 * @return list<int>
 */
function referencePars(): array
{
    return array_column(referenceCardData(null, null)['holes'], 'par');
}

test('the differential without rating falls back on par 71 and slope 113', function (int $line, ?int $firstHole, int $differentialT) {
    expect(Whs::differentialTenths(referenceStrokes($line, $firstHole), referencePars(), new CourseRating(710, 113)))
        ->toBe($differentialT);
})->with([
    'Gab' => [0, null, 10],
    'Manu' => [1, null, 170],
    'Manu with 10 on hole 1 (par 4)' => [1, 10, 180],
    'Gab with 3 on hole 1, under par' => [0, 3, -10],
]);

test('the differential uses the rating and the slope', function (int $ratingT, int $slope, int $line, int $differentialT) {
    expect(Whs::differentialTenths(referenceStrokes($line), referencePars(), new CourseRating($ratingT, $slope)))
        ->toBe($differentialT);
})->with([
    'Gab on 71,4 / 144' => [714, 144, 0, 5],
    'Manu on 71,4 / 144' => [714, 144, 1, 130],
    'Gab on 70,0 / 130' => [700, 130, 0, 17],
    'Manu on 70,0 / 130' => [700, 130, 1, 156],
]);
