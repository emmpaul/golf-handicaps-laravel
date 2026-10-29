<?php

/*
 * Spec course-ratings « Actions de recherche refusées », « Actions de recherche protégées »;
 * design D7. Queue::fake() holds the lookup jobs: nothing reaches Simulator Golf Tour.
 *
 * Mutations that turn these tests red:
 * - a route without whereUuid() and the CourseRating binding without the uuid check of HasUuids
 *   (resolveRouteBindingQuery overridden by a plain where): the query on the uuid column fails
 *   with 500 instead of the 404 for « 12 ». whereUuid() removed alone stays green: HasUuids
 *   already turns a value that is not a uuid into a 404, as in ImportPhotoTest;
 * - a route outside the session (withoutMiddleware(RequirePasscode::class)): the visit without
 *   session confirms the proposal instead of redirecting to /login;
 * - the success toast of the relaunch reworded « Rating confirmée. »;
 * - the rule integer dropped from sgtCourseId: « abc » reaches the action, which refuses it as a
 *   TypeError (500) instead of the validation error;
 * - the rule required dropped from tee: an empty tee reaches the action and is refused with the
 *   error toast instead of the validation error.
 */

use App\Domain\Courses\CourseRatingStatus;
use App\Jobs\LookUpCourseRating;
use App\Models\CourseRating;
use Illuminate\Support\Facades\Queue;

const UNKNOWN_COURSE_RATING = '0190a6b2-7c3e-7000-8000-000000000099';

beforeEach(function () {
    createPlayersOnTest('Gab', 'Manu');
    createRoundCard(referenceCardData($this->gab->id, $this->manu->id));
    // The row of the lookup of the card (SGT_LOOKUP off) gives way to the state under test.
    CourseRating::query()->delete();
    Queue::fake();
});

test('without a session each action redirects to /login and nothing changes', function (string $action, array $body) {
    $rating = CourseRating::factory()->proposed()->create();

    // An Inertia visit, as the buttons of the page send it.
    $this->withHeader('X-Inertia', 'true')
        ->post("/courses/ratings/{$rating->id}/{$action}", $body)
        ->assertRedirect('/login');

    expect($rating->refresh()->status)->toBe(CourseRatingStatus::Proposed)
        ->and($rating->rating_t)->toBeNull();
    Queue::assertNothingPushed();
})->with([
    'confirmation' => ['confirmation', []],
    'tee' => ['tee', ['tee' => 'White']],
    'candidate' => ['candidate', ['sgtCourseId' => 477]],
    'lookup' => ['lookup', []],
]);

describe('with a session', function () {
    beforeEach(function () {
        $this->withPasscodeSession();
    });

    test('the confirmation goes back with « Rating confirmée. »', function () {
        $rating = CourseRating::factory()->proposed()->create();

        $this->from('/courses')
            ->post("/courses/ratings/{$rating->id}/confirmation")
            ->assertRedirect('/courses')
            ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Rating confirmée.']);

        expect([$rating->refresh()->rating_t, $rating->slope, $rating->status])->toBe([714, 144, CourseRatingStatus::Confirmed]);
    });

    test('the choice of a tee goes back with « Rating confirmée. »', function () {
        $rating = CourseRating::factory()->chooseTee()->create(['tees' => 'Red']);

        $this->from('/courses')
            ->post("/courses/ratings/{$rating->id}/tee", ['tee' => 'White'])
            ->assertRedirect('/courses')
            ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Rating confirmée.']);

        expect([$rating->refresh()->rating_t, $rating->slope, $rating->proposed_tee])->toBe([742, 148, 'White']);
    });

    test('the choice of a candidate goes back with « Recherche relancée. »', function () {
        $rating = CourseRating::factory()->chooseCourse()->create();

        $this->from('/courses')
            ->post("/courses/ratings/{$rating->id}/candidate", ['sgtCourseId' => 1951])
            ->assertRedirect('/courses')
            ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Recherche relancée.']);

        expect([$rating->refresh()->status, $rating->sgt_course_id])->toBe([CourseRatingStatus::Pending, 1951]);
        Queue::assertPushed(LookUpCourseRating::class, 1);
    });

    test('the relaunch goes back with « Recherche relancée. »', function () {
        $rating = CourseRating::factory()->failed()->create(['course_name' => 'BETHPAGE BLACK', 'tees' => 'Red']);

        $this->from('/courses')
            ->post("/courses/ratings/{$rating->id}/lookup")
            ->assertRedirect('/courses')
            ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Recherche relancée.']);

        expect([$rating->refresh()->status, $rating->note])->toBe([CourseRatingStatus::Pending, null]);
        Queue::assertPushed(LookUpCourseRating::class, 1);
    });

    test('a refusal goes back with the error toast of its message and nothing changes', function (Closure $create, string $action, array $body, string $message) {
        $rating = $create();
        $before = $rating->refresh()->toArray();

        $this->from('/courses')
            ->post("/courses/ratings/{$rating->id}/{$action}", $body)
            ->assertRedirect('/courses')
            ->assertInertiaFlash('toast', ['type' => 'error', 'message' => $message]);

        expect($rating->refresh()->toArray())->toBe($before);
        Queue::assertNothingPushed();
    })->with([
        'confirmation without proposal' => [
            fn () => CourseRating::factory()->notFound()->create(['course_name' => 'BETHPAGE BLACK', 'tees' => 'Red']),
            'confirmation', [], 'Aucune proposition à confirmer',
        ],
        'tee not read' => [fn () => CourseRating::factory()->chooseTee()->create(), 'tee', ['tee' => 'Gold'], 'Tee absent de la carte SGT'],
        'candidate not listed' => [fn () => CourseRating::factory()->chooseCourse()->create(), 'candidate', ['sgtCourseId' => 30], 'Parcours SGT absent des candidats'],
    ]);

    test('an unknown or malformed row answers 404', function (string $id, string $action, array $body) {
        $this->post("/courses/ratings/{$id}/{$action}", $body)->assertNotFound();

        Queue::assertNothingPushed();
    })->with([
        'unknown confirmation' => [UNKNOWN_COURSE_RATING, 'confirmation', []],
        'unknown tee' => [UNKNOWN_COURSE_RATING, 'tee', ['tee' => 'White']],
        'unknown candidate' => [UNKNOWN_COURSE_RATING, 'candidate', ['sgtCourseId' => 477]],
        'unknown lookup' => [UNKNOWN_COURSE_RATING, 'lookup', []],
        'numeric confirmation' => ['12', 'confirmation', []],
        'numeric lookup' => ['12', 'lookup', []],
    ]);

    test('an empty tee or a candidate that is not a number is a validation error', function (Closure $create, string $action, array $body, string $field) {
        $rating = $create();
        $before = $rating->refresh()->toArray();

        $this->from('/courses')
            ->post("/courses/ratings/{$rating->id}/{$action}", $body)
            ->assertRedirect('/courses')
            ->assertSessionHasErrors($field);

        expect($rating->refresh()->toArray())->toBe($before);
        Queue::assertNothingPushed();
    })->with([
        'empty tee' => [fn () => CourseRating::factory()->chooseTee()->create(), 'tee', ['tee' => ''], 'tee'],
        'candidate abc' => [fn () => CourseRating::factory()->chooseCourse()->create(), 'candidate', ['sgtCourseId' => 'abc'], 'sgtCourseId'],
    ]);
});
