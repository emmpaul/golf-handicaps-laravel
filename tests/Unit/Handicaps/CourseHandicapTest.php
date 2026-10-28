<?php

/*
 * Port of the playingHandicap and courseHandicap cases of tests/domain/strokes.test.ts of the
 * Next.js application.
 *
 * Mutations that turn these tests red:
 * - truncation instead of rounding (intdiv): 125 gives 12 and −16 gives −1;
 * - (ratingT − par × 10) removed from courseHandicap(): 10, 174 and −20 give 1, 22 and −3 on
 *   71,4 / 144.
 */

use App\Domain\Handicaps\RatedCourse;
use App\Domain\Handicaps\Whs;

test('the playing handicap is the index rounded, 0,5 up', function (int $indexT, int $handicap) {
    expect(Whs::playingHandicap($indexT))->toBe($handicap);
})->with([
    [174, 17],
    [10, 1],
    [125, 13],
    [0, 0],
    [-15, -1],
    [-16, -2],
]);

test('the course handicap on Bethpage Black / Red, 71,4 / 144, par 71', function (int $indexT, int $handicap) {
    expect(Whs::courseHandicap($indexT, new RatedCourse(714, 144, 71)))->toBe($handicap);
})->with([
    [10, 2],
    [174, 23],
    [-20, -2],
]);

test('the course handicap without rating is the playing handicap', function (int $indexT, int $handicap) {
    expect(Whs::courseHandicap($indexT, null))->toBe($handicap);
})->with([
    [10, 1],
    [174, 17],
]);
