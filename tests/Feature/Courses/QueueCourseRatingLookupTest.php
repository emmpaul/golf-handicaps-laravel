<?php

/*
 * Spec course-ratings « Recherche lancée pour un parcours nouveau »; design D6. Queue::fake()
 * holds the lookup jobs: nothing reaches Simulator Golf Tour.
 *
 * Mutations that turn these tests red:
 * - the job pushed before the commit (afterCommit() dropped): it runs inside the rolled back
 *   transaction;
 * - the on conflict on course_name and tees without lower(trim()): the round on
 *   « calanova golf club / yellow » inserts a second row (or the insert has no unique index to
 *   infer and fails);
 * - the lookup queued for the pair the round had before its correction: no job for
 *   « THE SANCTUARY / White ».
 */

use App\Domain\Courses\CourseRatingStatus;
use App\Domain\Rounds\Actions\CreateRound;
use App\Domain\Rounds\Actions\UpdateRound;
use App\Domain\Rounds\Exceptions\IncoherentRoundCard;
use App\Domain\Rounds\RoundCard;
use App\Jobs\LookUpCourseRating;
use App\Models\CourseRating;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

/**
 * The reference card of Gab and Manu on another course and tees.
 *
 * @return array<string, mixed>
 */
function gabAndManuCardOnCourse(string $courseName, string $tees, string $playedOn = '2026-10-17'): array
{
    $card = referenceCardOn($playedOn, [[test()->gab->id, strokesOverPar(1)], [test()->manu->id, strokesOverPar(17)]]);
    $card['courseName'] = $courseName;
    $card['tees'] = $tees;

    return $card;
}

/**
 * The rows of course_ratings as [course_name, tees, status], by name.
 *
 * @return list<list<mixed>>
 */
function ratingPairs(): array
{
    return CourseRating::query()->orderBy('course_name')->get()
        ->map(fn (CourseRating $rating): array => [$rating->course_name, $rating->tees, $rating->status])
        ->values()->all();
}

beforeEach(function () {
    createPlayersOnTest('Gab', 'Manu');
});

describe('with the queue faked', function () {
    beforeEach(function () {
        Queue::fake();
    });

    test('the first round on a course queues the lookup of its pending row', function () {
        createRoundCard(gabAndManuCardOnCourse('CALANOVA GOLF CLUB', 'Yellow'));

        $rating = CourseRating::query()->sole();
        expect(ratingPairs())->toBe([['CALANOVA GOLF CLUB', 'Yellow', CourseRatingStatus::Pending]]);
        Queue::assertPushed(LookUpCourseRating::class, 1);
        Queue::assertPushed(LookUpCourseRating::class, fn (LookUpCourseRating $job): bool => $job->courseRatingId === $rating->id);
    });

    test('a second round on the same course in another case queues nothing', function () {
        createRoundCard(gabAndManuCardOnCourse('CALANOVA GOLF CLUB', 'Yellow'));

        createRoundCard(gabAndManuCardOnCourse('calanova golf club', 'yellow', '2026-10-18'));

        expect(ratingPairs())->toBe([['CALANOVA GOLF CLUB', 'Yellow', CourseRatingStatus::Pending]]);
        Queue::assertPushed(LookUpCourseRating::class, 1);
    });

    test('a round corrected onto a new course queues its lookup and keeps the row of the former one', function () {
        $round = createRoundCard(gabAndManuCardOnCourse('PAYNES VALLEY', 'Blue'));

        app(UpdateRound::class)->handle($round, RoundCard::fromArray(gabAndManuCardOnCourse('THE SANCTUARY', 'White')));

        $sanctuary = CourseRating::query()->where('course_name', 'THE SANCTUARY')->sole();
        expect(ratingPairs())->toBe([
            ['PAYNES VALLEY', 'Blue', CourseRatingStatus::Pending],
            ['THE SANCTUARY', 'White', CourseRatingStatus::Pending],
        ]);
        Queue::assertPushed(LookUpCourseRating::class, 2);
        Queue::assertPushed(LookUpCourseRating::class, fn (LookUpCourseRating $job): bool => $job->courseRatingId === $sanctuary->id);
    });

    test('a rating typed before the round queues nothing and stays', function () {
        CourseRating::factory()->bethpageRed()->create();

        createRoundCard(referenceCardData($this->gab->id, $this->manu->id));

        expect(CourseRating::query()->get(['course_name', 'tees', 'rating_t', 'slope'])->toArray())
            ->toBe([['course_name' => 'BETHPAGE BLACK', 'tees' => 'Red', 'rating_t' => 714, 'slope' => 144]]);
        Queue::assertNothingPushed();
    });

    test('an incoherent card writes no row and queues nothing', function () {
        $card = gabAndManuCardOnCourse('CALANOVA GOLF CLUB', 'Yellow');
        $card['holes'][2]['par'] = 6;

        expect(fn () => app(CreateRound::class)->handle(RoundCard::fromArray($card)))
            ->toThrow(IncoherentRoundCard::class);

        expect(CourseRating::query()->count())->toBe(0);
        Queue::assertNothingPushed();
    });
});

test('the lookup job leaves only once the round is committed', function () {
    // The sync queue of phpunit.xml, which honours afterCommit: the job would run right away.
    $ran = false;
    Queue::before(function () use (&$ran): void {
        $ran = true;
    });

    try {
        DB::transaction(function (): void {
            createRoundCard(gabAndManuCardOnCourse('CALANOVA GOLF CLUB', 'Yellow'));

            throw new LogicException('rolled back');
        });
    } catch (LogicException) {
    }

    expect($ran)->toBeFalse()
        ->and(CourseRating::query()->count())->toBe(0);
});
