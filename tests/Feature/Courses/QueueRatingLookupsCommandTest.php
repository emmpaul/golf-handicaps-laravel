<?php

/*
 * Spec course-ratings « Recherche des parcours déjà enregistrés »; design D6. The rounds are
 * written by their factory, without CreateRound: no row of course_ratings exists before the
 * command. Queue::fake() holds the jobs: nothing reaches Simulator Golf Tour.
 *
 * Mutations that turn these tests red:
 * - the singular forgotten (count > 0 instead of count > 1): « 0 recherches de rating mises en
 *   file. »;
 * - the rounds read from the most recent to the oldest: the row of Bethpage keeps
 *   « BETHPAGE BLACK » / « Red » instead of the text of the first round.
 */

use App\Domain\Courses\CourseRatingStatus;
use App\Jobs\LookUpCourseRating;
use App\Models\CourseRating;
use App\Models\Round;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
});

/**
 * The rows of course_ratings as [course_name, tees, status], by name then tees.
 *
 * @return list<list<mixed>>
 */
function queuedRatingRows(): array
{
    return CourseRating::query()->orderBy('course_name')->orderBy('tees')->get()
        ->map(fn (CourseRating $rating): array => [$rating->course_name, $rating->tees, $rating->status])
        ->values()->all();
}

/**
 * Six courses and tees, two of them on PAYNES VALLEY with tees that differ by their distance.
 */
function createSixPairs(): void
{
    foreach ([
        ['BETHPAGE BLACK', 'Red'],
        ['PAYNES VALLEY', 'Blue'],
        ['PAYNES VALLEY', 'Blue 6212yd'],
        ['CALA NOVA', 'White'],
        ['THE SANCTUARY', 'White'],
        ['SANCTUARY COVE', 'Blue'],
    ] as [$courseName, $tees]) {
        Round::factory()->create(['course_name' => $courseName, 'tees' => $tees]);
    }
}

test('the first run queues the lookup of each course and tees', function () {
    createSixPairs();

    $this->artisan('golf:queue-rating-lookups')
        ->expectsOutput('6 recherches de rating mises en file.')
        ->assertExitCode(0);

    $pending = CourseRatingStatus::Pending;
    expect(queuedRatingRows())->toBe([
        ['BETHPAGE BLACK', 'Red', $pending],
        ['CALA NOVA', 'White', $pending],
        ['PAYNES VALLEY', 'Blue', $pending],
        ['PAYNES VALLEY', 'Blue 6212yd', $pending],
        ['SANCTUARY COVE', 'Blue', $pending],
        ['THE SANCTUARY', 'White', $pending],
    ]);
    Queue::assertPushed(LookUpCourseRating::class, 6);
});

test('a second run queues nothing and exits with 0', function () {
    createSixPairs();
    $this->artisan('golf:queue-rating-lookups')->assertExitCode(0);
    Queue::fake();

    $this->artisan('golf:queue-rating-lookups')
        ->expectsOutput('0 recherche de rating mise en file.')
        ->assertExitCode(0);

    expect(CourseRating::query()->count())->toBe(6);
    Queue::assertNothingPushed();
});

test('a rating typed by hand is kept and only the course without row is looked up', function () {
    Round::factory()->create(['course_name' => 'BETHPAGE BLACK', 'tees' => 'Red']);
    Round::factory()->create(['course_name' => 'PAYNES VALLEY', 'tees' => 'Blue']);
    CourseRating::factory()->bethpageRed()->create();

    $this->artisan('golf:queue-rating-lookups')
        ->expectsOutput('1 recherche de rating mise en file.')
        ->assertExitCode(0);

    expect(CourseRating::query()->orderBy('course_name')->get(['course_name', 'tees', 'rating_t', 'slope'])->toArray())->toBe([
        ['course_name' => 'BETHPAGE BLACK', 'tees' => 'Red', 'rating_t' => 714, 'slope' => 144],
        ['course_name' => 'PAYNES VALLEY', 'tees' => 'Blue', 'rating_t' => null, 'slope' => null],
    ]);
    Queue::assertPushed(LookUpCourseRating::class, 1);
});

test('the row keeps the text of the oldest round of the course', function () {
    // The most recent round written first: an order by creation would read it first.
    Round::factory()->create(['course_name' => 'BETHPAGE BLACK', 'tees' => 'Red', 'played_on' => '2026-10-17']);
    Round::factory()->create(['course_name' => 'Bethpage Black', 'tees' => 'red', 'played_on' => '2026-10-01']);

    $this->artisan('golf:queue-rating-lookups')
        ->expectsOutput('1 recherche de rating mise en file.')
        ->assertExitCode(0);

    expect(queuedRatingRows())->toBe([['Bethpage Black', 'red', CourseRatingStatus::Pending]]);
    Queue::assertPushed(LookUpCourseRating::class, 1);
});
