<?php

/*
 * Port of parseRatingT of src/server/courses/manage.ts of the Next.js application (spec
 * course-ratings, « Saisie et correction à la main »; design D6).
 *
 * Mutations that turn these tests red:
 * - the comma refused ([.] instead of [.,] in the pattern): « 71,4 » gives null;
 * - two decimals rounded (\d{1,2} after the separator, then rounded to the tenth): « 71,45 »
 *   gives 715 instead of null;
 * - the upper bound 90,1 accepted (<= 901 instead of <= 900): « 90,1 » gives 901.
 */

use App\Domain\Courses\RatingInput;

test('a rating typed with a comma or a point gives its tenths', function (string $typed, int $tenths) {
    expect(RatingInput::tenths($typed))->toBe($tenths);
})->with([
    'comma' => ['71,4', 714],
    'point' => ['71.4', 714],
    'surrounding spaces' => ['  70,0  ', 700],
    'lower bound' => ['40,0', 400],
    'upper bound' => ['90.0', 900],
    'no decimal' => ['71', 710],
]);

test('a rating out of bounds or badly formed gives null', function (string $typed) {
    expect(RatingInput::tenths($typed))->toBeNull();
})->with([
    'under 40,0' => ['39,9'],
    'above 90,0' => ['90,1'],
    'two decimals' => ['71,45'],
    'letters' => ['abc'],
    'empty' => [''],
]);
