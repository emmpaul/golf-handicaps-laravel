<?php

/*
 * Spec course-ratings, « Retrait d'une rating », « Retrait refusé »; design D6. Spec
 * course-ratings of course-rating-sgt, « Recherche gardée après un retrait »; its design D7.
 *
 * Mutations that turn these tests red:
 * - no recompute after the delete: Gab stays at 5;
 * - the recompute run before the delete: it still reads 71,4 / 144, Gab stays at 5;
 * - the pair compared with its case (course_name = ? and tees = ?): « bethpage black / RED »
 *   matches no row, the row stays and CourseHasNoRating is raised;
 * - the CourseHasNoRating refusal removed: the second removal answers « Rating retirée. »;
 * - the removal that deletes the row (course-rating-sgt): the row, and what SGT read, is gone;
 * - the order of the states of recovery reversed (tees read checked before the proposal): the
 *   confirmed proposal of the tee Red comes back as « Tee à choisir »;
 * - the candidates never checked: the confirmed choice among candidates comes back as
 *   « Saisie manuelle »;
 * - a row without confirmed rating not refused: removing a proposal raises no refusal.
 */

use App\Domain\Courses\Actions\RemoveCourseRating;
use App\Domain\Courses\Actions\SaveCourseRating;
use App\Domain\Courses\CourseRatingStatus;
use App\Domain\Courses\Exceptions\CourseHasNoRating;
use App\Domain\Courses\Exceptions\UnknownCourse;
use App\Models\CourseRating;
use App\Models\Player;

beforeEach(function () {
    $gab = Player::factory()->create(['name' => 'Gab']);
    $manu = Player::factory()->create(['name' => 'Manu']);
    createRoundCard(referenceCardData($gab->id, $manu->id));
    createManuStrokesInAnotherCase(Player::factory()->create(['name' => 'Sean']));
    app(SaveCourseRating::class)->handle('BETHPAGE BLACK', 'Red', 714, 144);
});

test('the reference rating gives Gab 0,5, Manu and Sean 13,0', function () {
    expect(differentialsByName())->toBe(['Gab' => 5, 'Manu' => 130, 'Sean' => 130]);
});

/**
 * What the removal leaves on the only row: [rating_t, slope, source, status, note, proposed_tee].
 *
 * @return list<mixed>
 */
function removedRow(): array
{
    $rating = CourseRating::query()->sole();

    return [$rating->rating_t, $rating->slope, $rating->source, $rating->status, $rating->note, $rating->proposed_tee];
}

test('removing the rating whatever the case recomputes the players on the par and 113', function () {
    app(RemoveCourseRating::class)->handle('bethpage black', 'RED');

    expect(differentialsByName())->toBe(['Gab' => 10, 'Manu' => 170, 'Sean' => 170]);
});

test('removing a rating typed without lookup leaves the row to a manual rating', function () {
    app(RemoveCourseRating::class)->handle('BETHPAGE BLACK', 'Red');

    expect(removedRow())->toBe([null, null, null, CourseRatingStatus::Manual, null, null]);
});

describe('on a row of the lookup', function () {
    beforeEach(function () {
        CourseRating::query()->delete();
    });

    test('removing a confirmed proposal brings the proposal of the tee Red back', function () {
        CourseRating::factory()->confirmedFromSgt()->create();

        app(RemoveCourseRating::class)->handle('BETHPAGE BLACK', 'Red');

        expect(removedRow())->toBe([null, null, null, CourseRatingStatus::Proposed, null, 'Red'])
            ->and(array_column(CourseRating::query()->sole()->tees_read ?? [], 'name'))->toBe(['Blue', 'White', 'Red', 'Junior', 'PAR3'])
            ->and(differentialsByName())->toBe(['Gab' => 10, 'Manu' => 170, 'Sean' => 170]);
    });

    test('removing a rating typed on tees to choose brings the choice of the tee back', function () {
        CourseRating::factory()->chooseTee()->create([
            'tees' => 'Red', 'rating_t' => 714, 'slope' => 144, 'source' => 'manual',
            'status' => CourseRatingStatus::Confirmed, 'note' => 'Ancienne note',
        ]);

        app(RemoveCourseRating::class)->handle('BETHPAGE BLACK', 'Red');

        expect(removedRow())->toBe([null, null, null, CourseRatingStatus::ChooseTee, null, null]);
    });

    test('removing a rating typed on candidates brings the choice of the SGT course back', function () {
        CourseRating::factory()->chooseCourse()->create([
            'course_name' => 'BETHPAGE BLACK', 'tees' => 'Red', 'rating_t' => 714, 'slope' => 144,
            'source' => 'manual', 'status' => CourseRatingStatus::Confirmed,
        ]);

        app(RemoveCourseRating::class)->handle('BETHPAGE BLACK', 'Red');

        expect(removedRow())->toBe([null, null, null, CourseRatingStatus::ChooseCourse, null, null])
            ->and(array_column(CourseRating::query()->sole()->candidates ?? [], 'id'))->toBe([477, 1951]);
    });

    test('removing a proposal not confirmed is refused and nothing changes', function () {
        CourseRating::factory()->proposed()->create();
        $stats = allStats();

        expect(fn () => app(RemoveCourseRating::class)->handle('BETHPAGE BLACK', 'Red'))
            ->toThrow(CourseHasNoRating::class, 'Parcours déjà sans rating');

        expect(removedRow())->toBe([null, null, null, CourseRatingStatus::Proposed, null, 'Red'])
            ->and(allStats())->toBe($stats);
    });
});

test('a second removal is refused and leaves the history unchanged', function () {
    app(RemoveCourseRating::class)->handle('bethpage black', 'RED');
    $stats = allStats();

    expect(fn () => app(RemoveCourseRating::class)->handle('BETHPAGE BLACK', 'Red'))
        ->toThrow(CourseHasNoRating::class, 'Parcours déjà sans rating');

    expect(allStats())->toBe($stats);
});

test('a course and tees without round are refused and nothing is erased', function () {
    expect(fn () => app(RemoveCourseRating::class)->handle('PAYNES VALLEY', 'Blue'))
        ->toThrow(UnknownCourse::class, 'Parcours introuvable');

    expect(CourseRating::query()->count())->toBe(1)
        ->and(differentialsByName())->toBe(['Gab' => 5, 'Manu' => 130, 'Sean' => 130]);
});

test('without a session the removal redirects to /login and the row stays', function () {
    // An Inertia visit, as the form of the page sends it.
    $this->withHeader('X-Inertia', 'true')
        ->delete('/courses/rating', ['courseName' => 'BETHPAGE BLACK', 'tees' => 'Red'])
        ->assertRedirect('/login');

    expect(CourseRating::query()->count())->toBe(1);
});

describe('with a session', function () {
    beforeEach(function () {
        $this->withPasscodeSession();
    });

    test('the removal goes back with its toast', function () {
        $this->from('/courses')
            ->delete('/courses/rating', ['courseName' => 'BETHPAGE BLACK', 'tees' => 'Red'])
            ->assertRedirect('/courses')
            ->assertSessionHasNoErrors()
            ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Rating retirée.']);

        expect(CourseRating::query()->pluck('rating_t')->all())->toBe([null]);
    });

    test('a course already without rating goes back with the error toast', function () {
        CourseRating::query()->delete();

        $this->from('/courses')
            ->delete('/courses/rating', ['courseName' => 'BETHPAGE BLACK', 'tees' => 'Red'])
            ->assertRedirect('/courses')
            ->assertInertiaFlash('toast', ['type' => 'error', 'message' => 'Parcours déjà sans rating']);
    });

    test('an empty course name is a validation error and nothing is erased', function () {
        $this->from('/courses')
            ->delete('/courses/rating', ['courseName' => '', 'tees' => 'Red'])
            ->assertRedirect('/courses')
            ->assertSessionHasErrors('courseName');

        expect(CourseRating::query()->count())->toBe(1);
    });
});
