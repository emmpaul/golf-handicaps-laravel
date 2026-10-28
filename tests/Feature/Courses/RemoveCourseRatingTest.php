<?php

/*
 * Spec course-ratings, « Retrait d'une rating », « Retrait refusé »; design D6.
 *
 * Mutations that turn these tests red:
 * - no recompute after the delete: Gab stays at 5;
 * - the recompute run before the delete: it still reads 71,4 / 144, Gab stays at 5;
 * - the pair compared with its case (course_name = ? and tees = ?): « bethpage black / RED »
 *   matches no row, the row stays and CourseHasNoRating is raised;
 * - the CourseHasNoRating refusal removed: the second removal answers « Rating retirée. ».
 */

use App\Domain\Courses\Actions\RemoveCourseRating;
use App\Domain\Courses\Actions\SaveCourseRating;
use App\Domain\Courses\Exceptions\CourseHasNoRating;
use App\Domain\Courses\Exceptions\UnknownCourse;
use App\Models\CourseRating;
use App\Models\Player;
use App\Models\PlayerRoundStat;

beforeEach(function () {
    $gab = Player::factory()->create(['name' => 'Gab']);
    $manu = Player::factory()->create(['name' => 'Manu']);
    createRoundCard(referenceCardData($gab->id, $manu->id));
    createManuStrokesInAnotherCase(Player::factory()->create(['name' => 'Sean']));
    app(SaveCourseRating::class)->handle('BETHPAGE BLACK', 'Red', 714, 144);
});

/**
 * Every row of player_round_stats, by value.
 *
 * @return list<array<string, mixed>>
 */
function allStats(): array
{
    return PlayerRoundStat::query()->orderBy('id')->get()->toArray();
}

test('the reference rating gives Gab 0,5, Manu and Sean 13,0', function () {
    expect(differentialsByName())->toBe(['Gab' => 5, 'Manu' => 130, 'Sean' => 130]);
});

test('removing the rating whatever the case recomputes the players on the par and 113', function () {
    app(RemoveCourseRating::class)->handle('bethpage black', 'RED');

    expect(CourseRating::query()->count())->toBe(0)
        ->and(differentialsByName())->toBe(['Gab' => 10, 'Manu' => 170, 'Sean' => 170]);
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

        expect(CourseRating::query()->count())->toBe(0);
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
