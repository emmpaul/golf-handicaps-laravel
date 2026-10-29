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
 *   refused by the unique index (UniqueConstraintViolationException);
 * - a do update that leaves source and status (design D7 of course-rating-sgt): the row of the
 *   lookup, without source, refuses the rating (course_ratings_rating_source_check).
 */

use App\Domain\Courses\Actions\SaveCourseRating;
use App\Domain\Courses\CourseRatingStatus;
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

test('a rating typed on a proposal confirms it by hand and keeps what SGT read', function () {
    CourseRating::query()->delete();
    CourseRating::factory()->proposed()->create();

    app(SaveCourseRating::class)->handle('BETHPAGE BLACK', 'Red', 700, 130);

    $rating = CourseRating::query()->sole();
    expect([$rating->rating_t, $rating->slope, $rating->source, $rating->status, $rating->proposed_tee])
        ->toBe([700, 130, 'manual', CourseRatingStatus::Confirmed, 'Red'])
        ->and(array_column($rating->tees_read ?? [], 'name'))->toBe(['Blue', 'White', 'Red', 'Junior', 'PAR3']);
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

    // Only the row of the lookup of the reference card, without rating (SGT_LOOKUP off).
    expect(ratingRows())->toBe([['BETHPAGE BLACK', 'Red', null, null]])
        ->and(differentialsByName())->toBe(['Gab' => 10, 'Manu' => 170, 'Sean' => 170]);
});
