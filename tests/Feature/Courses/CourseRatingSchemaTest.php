<?php

/*
 * PostgreSQL is the second line behind the validation of the ratings typed by hand.
 *
 * Mutations that turn these tests red:
 * - the unique index on (course_name, tees) without lower(trim()): « bethpage black / RED » is
 *   inserted next to « BETHPAGE BLACK / Red »;
 * - the CHECK on rating_t removed: a rating of 90,1 is inserted;
 * - the CHECK on slope removed: a slope of 54 is inserted.
 *
 * Lookup columns (design D1 of course-rating-sgt), mutations named on each test.
 */

use App\Domain\Courses\CourseRatingStatus;
use App\Models\CourseRating;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

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

// Mutation that turns this test red: rating_t or slope left NOT NULL by the migration.
test('a pending row without rating nor slope is accepted', function () {
    $rating = CourseRating::factory()->create(['status' => 'pending', 'rating_t' => null, 'slope' => null, 'source' => null]);

    expect([$rating->fresh()?->status, $rating->fresh()?->rating_t, $rating->fresh()?->slope])
        ->toBe([CourseRatingStatus::Pending, null, null]);
});

// Mutation that turns this test red: the CHECK on status removed.
test('a status outside the eight of the lookup is refused', function () {
    expect(fn () => DB::table('course_ratings')->insert([
        'id' => '0199c2d0-0000-7000-8000-000000000001',
        'course_name' => 'BETHPAGE BLACK',
        'tees' => 'Red',
        'status' => 'searching',
    ]))->toThrow(QueryException::class, 'course_ratings_status_check');
});

// Mutation that turns this test red: the CHECK « confirmed has a rating » removed.
test('a confirmed row without rating is refused', function () {
    expect(fn () => CourseRating::factory()->create(['status' => 'confirmed', 'rating_t' => null, 'slope' => null, 'source' => null]))
        ->toThrow(QueryException::class, 'course_ratings_confirmed_rating_check');
});

// Mutation that turns this test red: the CHECK (rating_t IS NULL) = (slope IS NULL) removed.
test('a rating without slope is refused', function () {
    expect(fn () => CourseRating::factory()->create(['rating_t' => 714, 'slope' => null]))
        ->toThrow(QueryException::class, 'course_ratings_rating_slope_check');
});

// Mutation that turns this test red: the CHECK (rating_t IS NULL) = (source IS NULL) removed.
test('a rating without source is refused', function () {
    expect(fn () => CourseRating::factory()->create(['rating_t' => 714, 'slope' => 144, 'source' => null]))
        ->toThrow(QueryException::class, 'course_ratings_rating_source_check');
});

// Mutation that turns this test red: the CHECK source IN ('sgt', 'manual') removed.
test('a source other than sgt or manual is refused', function () {
    expect(fn () => CourseRating::factory()->create(['source' => 'ocr']))
        ->toThrow(QueryException::class, 'course_ratings_source_check');
});

// Mutation that turns this test red: the reprise without source (existing rows keep source
// null, the CHECK on rating and source then refuses the migration), or a default status other
// than confirmed. The migration is played down then up inside the transaction of the test:
// PostgreSQL rolls the DDL back with it.
test('a rating typed before the migration is confirmed by hand after it', function () {
    $migration = require database_path('migrations/2026_10_29_000011_add_lookup_to_course_ratings_table.php');
    $migration->down();
    DB::table('course_ratings')->insert([
        'id' => '0199c2d0-0000-7000-8000-000000000002',
        'course_name' => 'BETHPAGE BLACK',
        'tees' => 'Red',
        'rating_t' => 714,
        'slope' => 144,
    ]);

    $migration->up();

    expect(DB::table('course_ratings')->get(['status', 'source', 'rating_t', 'slope'])->map(fn (object $row): array => (array) $row)->all())
        ->toBe([['status' => 'confirmed', 'source' => 'manual', 'rating_t' => 714, 'slope' => 144]]);
});
