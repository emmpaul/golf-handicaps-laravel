<?php

/*
 * Spec course-ratings « Choix d'un parcours SGT candidat », « Relance de la recherche »,
 * « Actions de recherche refusées »; design D7. Queue::fake() holds the lookup jobs: nothing
 * reaches Simulator Golf Tour.
 *
 * Mutations that turn these tests red:
 * - the relaunch that clears rating_t (and slope and source): the confirmed 71,4 / 144 is lost
 *   and Gab and Manu go back to 10 and 170;
 * - the candidate written without its location: sgt_course_location stays null;
 * - the CandidateNotListed refusal removed: the SGT course 30 is written and a job pushed;
 * - no job pushed by the relaunch: Queue::assertPushed finds none.
 */

use App\Domain\Courses\Actions\ChooseSgtCandidate;
use App\Domain\Courses\Actions\RelaunchCourseRatingLookup;
use App\Domain\Courses\CourseRatingStatus;
use App\Domain\Courses\Exceptions\CandidateNotListed;
use App\Domain\Handicaps\Actions\RecomputePlayerStats;
use App\Jobs\LookUpCourseRating;
use App\Models\CourseRating;
use Illuminate\Support\Facades\Queue;

/**
 * The lookup columns of a row: [status, sgt_course_id, sgt_course_name, sgt_course_location,
 * note, rating_t, slope, source].
 *
 * @return list<mixed>
 */
function lookupColumns(CourseRating $rating): array
{
    $rating->refresh();

    return [
        $rating->status, $rating->sgt_course_id, $rating->sgt_course_name, $rating->sgt_course_location,
        $rating->note, $rating->rating_t, $rating->slope, $rating->source,
    ];
}

beforeEach(function () {
    createPlayersOnTest('Gab', 'Manu');
    createRoundCard(referenceCardData($this->gab->id, $this->manu->id));
    // The row of the lookup of the card (SGT_LOOKUP off) gives way to the state under test.
    CourseRating::query()->delete();
    Queue::fake();
});

test('choosing the candidate 1951 retains it with its name and location and queues the lookup', function () {
    $rating = CourseRating::factory()->chooseCourse()->create(['note' => 'Ancienne note']);

    app(ChooseSgtCandidate::class)->handle($rating, 1951);

    expect(lookupColumns($rating))->toBe([
        CourseRatingStatus::Pending, 1951, 'The Pines Sanctuary Cove', 'Hope Island, Queensland, Australia',
        null, null, null, null,
    ]);
    Queue::assertPushed(LookUpCourseRating::class, 1);
    Queue::assertPushed(LookUpCourseRating::class, fn (LookUpCourseRating $job): bool => $job->courseRatingId === $rating->id);
});

test('a candidate absent from the candidates is refused, nothing changes and no job leaves', function () {
    $rating = CourseRating::factory()->chooseCourse()->create();

    expect(fn () => app(ChooseSgtCandidate::class)->handle($rating, 30))
        ->toThrow(CandidateNotListed::class, 'Parcours SGT absent des candidats');

    expect(lookupColumns($rating))->toBe([CourseRatingStatus::ChooseCourse, null, null, null, null, null, null, null]);
    Queue::assertNothingPushed();
});

test('relaunching a confirmed course forgets its SGT course and keeps the confirmed rating', function () {
    $rating = CourseRating::factory()->confirmedFromSgt()->create(['note' => 'Ancienne note']);
    // The factory writes without recomputing: the history is computed on 71,4 / 144 here.
    app(RecomputePlayerStats::class)->handle([$this->gab->id, $this->manu->id]);
    $stats = allStats();

    app(RelaunchCourseRatingLookup::class)->handle($rating);

    expect(lookupColumns($rating))->toBe([CourseRatingStatus::Pending, null, null, null, null, 714, 144, 'sgt'])
        ->and(allStats())->toBe($stats)
        ->and(differentialsByName())->toBe(['Gab' => 5, 'Manu' => 130]);
    Queue::assertPushed(LookUpCourseRating::class, fn (LookUpCourseRating $job): bool => $job->courseRatingId === $rating->id);
});

test('relaunching a pending course queues one more job', function () {
    $rating = CourseRating::factory()->pending()->create(['course_name' => 'BETHPAGE BLACK', 'tees' => 'Red']);

    app(RelaunchCourseRatingLookup::class)->handle($rating);
    app(RelaunchCourseRatingLookup::class)->handle($rating);

    expect($rating->refresh()->status)->toBe(CourseRatingStatus::Pending);
    Queue::assertPushed(LookUpCourseRating::class, 2);
});
