<?php

/*
 * PostgreSQL is the second line behind the validation of the ratings typed by hand.
 *
 * Mutations that turn these tests red:
 * - the unique index on (course_name, tees) without lower(trim()): « bethpage black / RED » is
 *   inserted next to « BETHPAGE BLACK / Red »;
 * - the CHECK on rating_t removed: a rating of 90,1 is inserted;
 * - the CHECK on slope removed: a slope of 54 is inserted.
 */

use App\Models\CourseRating;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;

test('a course and tees have one rating whatever the case and the surrounding spaces', function () {
    CourseRating::factory()->create(['course_name' => 'BETHPAGE BLACK', 'tees' => 'Red']);

    expect(fn () => CourseRating::factory()->create(['course_name' => ' bethpage black', 'tees' => 'RED ']))
        ->toThrow(UniqueConstraintViolationException::class, 'course_ratings_course_tees_unique');
});

test('other tees of the same course have their own rating', function () {
    CourseRating::factory()->create(['course_name' => 'BETHPAGE BLACK', 'tees' => 'Red']);
    CourseRating::factory()->create(['course_name' => 'BETHPAGE BLACK', 'tees' => 'Blue']);

    expect(CourseRating::query()->orderBy('tees')->pluck('tees')->all())->toBe(['Blue', 'Red']);
});

test('a rating above 90,0 is refused', function () {
    expect(fn () => CourseRating::factory()->create(['rating_t' => 901]))
        ->toThrow(QueryException::class, 'course_ratings_rating_t_check');
});

test('a slope under 55 is refused', function () {
    expect(fn () => CourseRating::factory()->create(['slope' => 54]))
        ->toThrow(QueryException::class, 'course_ratings_slope_check');
});

test('the bounds 40,0 and 55 are accepted', function () {
    $rating = CourseRating::factory()->bounds()->create();

    expect([$rating->fresh()?->rating_t, $rating->fresh()?->slope])->toBe([400, 55]);
});
