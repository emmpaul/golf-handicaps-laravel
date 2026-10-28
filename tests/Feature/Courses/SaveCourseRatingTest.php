<?php

/*
 * Port of the manual cases of tests/server/courses/manage.test.ts of the Next.js application
 * (spec course-ratings, « Saisie et correction à la main »; design D6).
 *
 * Mutations that turn these tests red:
 * - no recompute after the write: Gab and Manu stay at 10 and 170;
 * - the players of the course looked up with the case (course_name = ? and tees = ?): Sean, on
 *   « Bethpage Black / RED », stays at 170;
 * - an insert without on conflict: the second rating typed for « bethpage black / red » is
 *   refused by the unique index (UniqueConstraintViolationException).
 */

use App\Domain\Courses\Actions\SaveCourseRating;
use App\Domain\Courses\Exceptions\UnknownCourse;
use App\Models\CourseRating;

beforeEach(function () {
    createPlayersOnTest('Gab', 'Manu', 'Sean');
    createRoundCard(referenceCardData($this->gab->id, $this->manu->id));
    createManuStrokesInAnotherCase($this->sean);
});

/**
 * The ratings in base: [course_name, tees, rating_t, slope] per row.
 *
 * @return list<list<mixed>>
 */
function ratingRows(): array
{
    return array_values(CourseRating::query()->orderBy('course_name')->get()
        ->map(fn (CourseRating $rating): array => [$rating->course_name, $rating->tees, $rating->rating_t, $rating->slope])
        ->all());
}

test('a typed rating is written and recomputes the players of the course', function () {
    expect(differentialsByName())->toBe(['Gab' => 10, 'Manu' => 170, 'Sean' => 170]);

    app(SaveCourseRating::class)->handle('BETHPAGE BLACK', 'Red', 714, 144);

    expect(ratingRows())->toBe([['BETHPAGE BLACK', 'Red', 714, 144]])
        ->and(differentialsByName())->toBe(['Gab' => 5, 'Manu' => 130, 'Sean' => 130]);
});

test('a correction in another case updates the same row', function () {
    app(SaveCourseRating::class)->handle('BETHPAGE BLACK', 'Red', 714, 144);

    app(SaveCourseRating::class)->handle('bethpage black', 'red', 700, 130);

    expect(CourseRating::query()->count())->toBe(1)
        ->and(ratingRows())->toBe([['BETHPAGE BLACK', 'Red', 700, 130]])
        ->and(differentialsByName())->toBe(['Gab' => 17, 'Manu' => 156, 'Sean' => 156]);
});

test('a course and tees without round are refused and nothing is written', function () {
    expect(fn () => app(SaveCourseRating::class)->handle('PAYNES VALLEY', 'Blue', 714, 144))
        ->toThrow(UnknownCourse::class, 'Parcours introuvable');

    expect(CourseRating::query()->count())->toBe(0)
        ->and(differentialsByName())->toBe(['Gab' => 10, 'Manu' => 170, 'Sean' => 170]);
});
