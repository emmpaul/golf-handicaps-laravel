<?php

/*
 * Port of the playingHandicap and courseHandicap cases of tests/domain/strokes.test.ts of the
 * Next.js application. tests/fixtures/course-handicap.json is shared with
 * resources/js/lib/strokes.test.ts: a gap between the PHP and TypeScript formulas turns one of
 * the two red (design D5 of home-and-match-strokes).
 *
 * Mutations that turn these tests red:
 * - truncation instead of rounding (intdiv): 125 gives 12 and −16 gives −1;
 * - (ratingT − par × 10) removed from courseHandicap(): 10, 174 and −20 give 1, 22 and −3 on
 *   71,4 / 144.
 */

use App\Domain\Handicaps\RatedCourse;
use App\Domain\Handicaps\Whs;

/**
 * The cases of one function in the shared table, as Pest data sets.
 *
 * @return list<array<string, mixed>>
 */
function courseHandicapCases(string $function): array
{
    /** @var array<string, list<array<string, mixed>>> $fixture */
    $fixture = json_decode((string) file_get_contents(__DIR__.'/../../fixtures/course-handicap.json'), true, flags: JSON_THROW_ON_ERROR);

    return $fixture[$function];
}

test('the shared table has cases for both functions', function () {
    expect(courseHandicapCases('playingHandicap'))->toHaveCount(6)
        ->and(courseHandicapCases('courseHandicap'))->toHaveCount(5);
});

test('the playing handicap is the index rounded, 0,5 up', function (int $indexT, int $expected) {
    expect(Whs::playingHandicap($indexT))->toBe($expected);
})->with(courseHandicapCases('playingHandicap'));

test('the course handicap with the rating, or the playing handicap without', function (int $indexT, ?array $course, int $expected) {
    $rated = $course === null ? null : new RatedCourse($course['ratingT'], $course['slope'], $course['par']);

    expect(Whs::courseHandicap($indexT, $rated))->toBe($expected);
})->with(courseHandicapCases('courseHandicap'));
