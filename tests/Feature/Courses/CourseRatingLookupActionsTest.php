<?php

/*
 * The two Actions around the lookup job (design D5): StartCourseRatingLookup reads the row and
 * the par of the most recent round of its pair, FinishCourseRatingLookup writes the outcome on a
 * row still pending. The rating rows are made before the rounds: CreateRound then finds a row
 * for the pair and queues nothing.
 *
 * Mutations that turn these tests red:
 * - the outcome written without the guard of the status: the proposed row becomes not_found;
 * - rating_t set back to null by the outcome: the row relaunched after its confirmation loses
 *   71,4 / 144;
 * - the most recent round taken by date of saving instead of date of play: the par 72 of the
 *   round of 2026-10-01, saved last, instead of the par 71 of 2026-10-17;
 * - a row taken whatever its status: the proposed row gives a target;
 * - the outcome written without comparing the SGT course of the target with the row: a lookup
 *   relaunched during the reading of a candidate is overwritten by the earlier outcome.
 */

use App\Domain\Courses\Actions\FinishCourseRatingLookup;
use App\Domain\Courses\Actions\StartCourseRatingLookup;
use App\Domain\Courses\CourseRatingLookupOutcome;
use App\Domain\Courses\CourseRatingLookupTarget;
use App\Domain\Courses\CourseRatingStatus;
use App\Domain\Courses\Sgt\SgtCourse;
use App\Domain\Courses\Sgt\SgtTee;
use App\Models\CourseRating;
use Illuminate\Support\Str;

/**
 * The columns of the lookup of a row, in a fixed order.
 *
 * @return list<mixed>
 */
function lookupColumnsOf(CourseRating $rating): array
{
    $rating->refresh();

    return [
        $rating->status, $rating->note, $rating->candidates, $rating->tees_read, $rating->proposed_tee,
        $rating->sgt_course_id, $rating->rating_t, $rating->slope, $rating->source,
    ];
}

/**
 * The target the job read on the row, before the outcome is written.
 */
function lookupTargetOf(CourseRating $rating): CourseRatingLookupTarget
{
    return new CourseRatingLookupTarget($rating->id, $rating->course_name, $rating->tees, $rating->sgt_course_id, 71);
}

describe('StartCourseRatingLookup', function () {
    test('the target of a pending pair carries the par of its most recent round by date of play', function () {
        $rating = CourseRating::factory()->pending()->create(['course_name' => 'BETHPAGE BLACK', 'tees' => 'Red']);
        createPlayersOnTest('Gab', 'Manu');
        createRoundCard(referenceCardData($this->gab->id, $this->manu->id));
        // Played before the reference card, saved after it, with a par 5 on hole 1: par 72.
        $older = referenceCardOn('2026-10-01', [[$this->gab->id, strokesOverPar(3)]]);
        $older['holes'][0]['par'] = 5;
        createRoundCard($older);

        $target = app(StartCourseRatingLookup::class)->handle($rating->id);

        expect($target)->toEqual(new CourseRatingLookupTarget($rating->id, 'BETHPAGE BLACK', 'Red', null, 71));
    });

    test('a row that is not pending, or unknown, gives no target', function () {
        $proposed = CourseRating::factory()->proposed()->create();

        expect(app(StartCourseRatingLookup::class)->handle($proposed->id))->toBeNull()
            ->and(app(StartCourseRatingLookup::class)->handle((string) Str::uuid7()))->toBeNull();
    });

    test('a pending pair without any round left gives a target without par', function () {
        $rating = CourseRating::factory()->pending()->create(['course_name' => 'CALA NOVA', 'tees' => 'White']);

        expect(app(StartCourseRatingLookup::class)->handle($rating->id))
            ->toEqual(new CourseRatingLookupTarget($rating->id, 'CALA NOVA', 'White', null, null));
    });

    test('the target keeps the SGT candidate already chosen', function () {
        $rating = CourseRating::factory()->pending()->create(['course_name' => 'SANCTUARY COVE', 'tees' => 'Blue', 'sgt_course_id' => 1951]);

        expect(app(StartCourseRatingLookup::class)->handle($rating->id)?->sgtCourseId)->toBe(1951);
    });
});

describe('FinishCourseRatingLookup', function () {
    test('not found on a pending row clears what an earlier lookup left', function () {
        $rating = CourseRating::factory()->pending()->create([
            'note' => 'SGT injoignable',
            'candidates' => [['id' => 477, 'name' => 'Sanctuary', 'location' => 'Sedalia, CO', 'par' => 72]],
            'tees_read' => [['name' => 'Red', 'ratingT' => 714, 'slope' => 144]],
            'proposed_tee' => 'Red',
        ]);

        app(FinishCourseRatingLookup::class)->handle(lookupTargetOf($rating), CourseRatingLookupOutcome::notFound());

        expect(lookupColumnsOf($rating))->toBe([CourseRatingStatus::NotFound, null, null, null, null, null, null, null, null]);
    });

    test('a proposal on a row relaunched after its confirmation keeps the confirmed rating', function () {
        $rating = CourseRating::factory()->confirmedFromSgt()->create([
            'status' => CourseRatingStatus::Pending,
            'sgt_course_id' => null,
            'sgt_course_name' => null,
            'sgt_course_location' => null,
        ]);

        app(FinishCourseRatingLookup::class)->handle(lookupTargetOf($rating), CourseRatingLookupOutcome::proposed(
            new SgtCourse(30, 'Bethpage Black', 'Farmingdale, NY', 71),
            [new SgtTee('White', 742, 148), new SgtTee('Red', 714, 144)],
            'Red',
        ));

        // toEqual: jsonb gives the keys of each tee back in its own order.
        expect(lookupColumnsOf($rating))->toEqual([
            CourseRatingStatus::Proposed, null, null,
            [['name' => 'White', 'ratingT' => 742, 'slope' => 148], ['name' => 'Red', 'ratingT' => 714, 'slope' => 144]],
            'Red', 30, 714, 144, 'sgt',
        ])
            ->and([$rating->sgt_course_name, $rating->sgt_course_location])->toBe(['Bethpage Black', 'Farmingdale, NY']);
    });

    test('an outcome of a reading of a candidate on a row relaunched meanwhile writes nothing', function () {
        // The job read the candidate 1951 chosen by the user; the user relaunched the whole
        // lookup before it ended: the row is pending again, without SGT course, and a new job
        // is queued. The outcome of the first job (proposed, course kept from the row) is
        // dropped, and the new job does the whole lookup.
        // Mutation: the guard on the SGT course of the target removed: the row becomes proposed
        // without SGT course, and the new job stops at once (row no longer pending).
        $rating = CourseRating::factory()->pending()->create([
            'course_name' => 'SANCTUARY COVE', 'tees' => 'Blue',
            'sgt_course_id' => 1951, 'sgt_course_name' => 'The Pines Sanctuary Cove', 'sgt_course_location' => 'Hope Island, Queensland, Australia',
        ]);
        $target = lookupTargetOf($rating);
        $rating->update(['sgt_course_id' => null, 'sgt_course_name' => null, 'sgt_course_location' => null]);

        app(FinishCourseRatingLookup::class)->handle($target, CourseRatingLookupOutcome::proposed(
            null,
            [new SgtTee('Blue', 731, 135)],
            'Blue',
        ));

        expect(lookupColumnsOf($rating))->toBe([CourseRatingStatus::Pending, null, null, null, null, null, null, null, null]);
    });

    test('an outcome on a row no longer pending writes nothing', function () {
        $rating = CourseRating::factory()->proposed()->create();
        $before = lookupColumnsOf($rating);

        app(FinishCourseRatingLookup::class)->handle(lookupTargetOf($rating), CourseRatingLookupOutcome::notFound());

        expect(lookupColumnsOf($rating))->toBe($before)
            ->and($before[0])->toBe(CourseRatingStatus::Proposed);
    });
});
