<?php

/*
 * Spec course-ratings, « Page Parcours », « Saisie et correction à la main »; design D6.
 *
 * Mutations that turn these tests red:
 * - the upper slope bound below 155 (> 154 refused): « 90.0 » / « 155 » is refused instead of
 *   stored as 900 / 155;
 * - the lower slope bound above 55 (`(int) $slope < 56` in slopeInRange): « 40,0 » / « 55 » is
 *   refused instead of stored as 400 / 55 with the « Rating enregistrée. » toast;
 * - the slope message put under `rating` (the slope rules keyed as rating in the error bag):
 *   « 160 » and « 144,5 » no longer give the `slope` error;
 * - CourseOverview that sorts its rows by courseName in PHP (sortBy, byte order): « CALA NOVA »
 *   comes before « bethpage black »;
 * - the rating left out of CourseOverview (ratingT and slope always null): BETHPAGE BLACK / Red
 *   reads null / null instead of 714 / 144;
 * - CourseOverview that zips KnownCourses with a second query of the ratings by position: the
 *   round saved on « AAA COURSE » between the two reads gives a third rating row, the courses
 *   take the rating of the row before them (TypeError on the row without course);
 * - the stroke indexes taken from the oldest round of the pair (a second query of the holes of
 *   the first round played on it): BETHPAGE BLACK / Red reads 16, 8, … instead of 8, 16, ….
 */

use App\Domain\Courses\Queries\CourseOverview;
use App\Models\CourseRating;
use App\Models\Player;
use App\Models\Round;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

const BETHPAGE_RED_STROKE_INDEXES = [8, 16, 18, 2, 4, 10, 6, 14, 12, 9, 11, 7, 3, 17, 1, 5, 13, 15];

/**
 * The reference card on another course and tees, its first hole a par $par1.
 *
 * @return array<string, mixed>
 */
function referenceCardOnCourse(string $playerId, string $courseName, string $tees, string $playedOn, int $par1 = 4): array
{
    $card = referenceCardOn($playedOn, [[$playerId, referenceCardData(null, null)['lines'][0]['strokes']]]);
    $card['courseName'] = $courseName;
    $card['tees'] = $tees;
    $card['holes'][0]['par'] = $par1;

    return $card;
}

test('without a session /courses redirects to /login and a typed rating writes nothing', function () {
    $gab = Player::factory()->create(['name' => 'Gab']);
    createRoundCard(referenceCardOnCourse($gab->id, 'BETHPAGE BLACK', 'Red', '2026-10-17'));

    $this->get('/courses')->assertRedirect('/login');
    // An Inertia visit, as the form of the page sends it.
    $this->withHeader('X-Inertia', 'true')
        ->put('/courses/rating', ['courseName' => 'BETHPAGE BLACK', 'tees' => 'Red', 'rating' => '71,4', 'slope' => '144'])
        ->assertRedirect('/login');

    expect(CourseRating::query()->count())->toBe(0);
});

describe('with a session', function () {
    beforeEach(function () {
        $this->withPasscodeSession();
        createPlayersOnTest('Gab', 'Manu');
    });

    test('without round the page lists no course', function () {
        $this->get('/courses')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('courses/index')->where('courses', []));
    });

    describe('with PAYNES VALLEY / Blue and the reference card rated 71,4 / 144', function () {
        beforeEach(function () {
            createRoundCard(referenceCardOnCourse($this->gab->id, 'PAYNES VALLEY', 'Blue', '2026-10-24', par1: 5));
            createRoundCard(referenceCardData($this->gab->id, $this->manu->id));
            CourseRating::factory()->bethpageRed()->create();
        });

        test('the page lists each course with the par of its latest round and its rating', function () {
            $this->get('/courses')
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->component('courses/index')
                    ->where('courses', [
                        ['courseName' => 'BETHPAGE BLACK', 'tees' => 'Red', 'par' => 71, 'ratingT' => 714, 'slope' => 144, 'strokeIndexes' => BETHPAGE_RED_STROKE_INDEXES],
                        ['courseName' => 'PAYNES VALLEY', 'tees' => 'Blue', 'par' => 72, 'ratingT' => null, 'slope' => null, 'strokeIndexes' => BETHPAGE_RED_STROKE_INDEXES],
                    ]));
        });

        test('a round saved on a new course while the courses are read moves no rating', function () {
            // A round on « AAA COURSE », first in the order, saved right after the first read of the
            // courses: a second read zipped by position with the first would see three courses.
            $saved = false;
            DB::listen(function (QueryExecuted $query) use (&$saved): void {
                if (! $saved && str_contains($query->sql, 'distinct on')) {
                    $saved = true;
                    Round::factory()->create(['course_name' => 'AAA COURSE', 'tees' => 'White']);
                }
            });

            expect(app(CourseOverview::class)->get())->toBe([
                ['courseName' => 'BETHPAGE BLACK', 'tees' => 'Red', 'par' => 71, 'ratingT' => 714, 'slope' => 144, 'strokeIndexes' => BETHPAGE_RED_STROKE_INDEXES],
                ['courseName' => 'PAYNES VALLEY', 'tees' => 'Blue', 'par' => 72, 'ratingT' => null, 'slope' => null, 'strokeIndexes' => BETHPAGE_RED_STROKE_INDEXES],
            ]);
        });
    });

    test('the stroke indexes of a course are those of its latest round', function () {
        $older = referenceCardOn('2026-10-10', [[$this->gab->id, strokesOverPar(10)]]);
        $older['holes'][0]['strokeIndex'] = 16;
        $older['holes'][1]['strokeIndex'] = 8;
        createRoundCard($older);
        createRoundCard(referenceCardData($this->gab->id, $this->manu->id));

        $this->get('/courses')->assertInertia(fn (Assert $page) => $page
            ->where('courses.0.strokeIndexes', [8, 16, 18, 2, 4, 10, 6, 14, 12, 9, 11, 7, 3, 17, 1, 5, 13, 15]));
    });

    test('the courses are ordered by name whatever the case', function () {
        createRoundCard(referenceCardOnCourse($this->gab->id, 'CALA NOVA', 'White', '2026-10-01'));
        createRoundCard(referenceCardOnCourse($this->gab->id, 'bethpage black', 'Red', '2026-10-02'));

        $courses = $this->get('/courses')->inertiaProps('courses');

        expect(array_column($courses, 'courseName'))->toBe(['bethpage black', 'CALA NOVA']);
    });

    describe('when the reference card is saved', function () {
        beforeEach(function () {
            createRoundCard(referenceCardData($this->gab->id, $this->manu->id));
        });

        test('a rating typed with a comma goes back with its toast', function () {
            $this->from('/courses')
                ->put('/courses/rating', ['courseName' => 'BETHPAGE BLACK', 'tees' => 'Red', 'rating' => '71,4', 'slope' => '144'])
                ->assertRedirect('/courses')
                ->assertSessionHasNoErrors()
                ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Rating enregistrée.']);

            expect(CourseRating::query()->get(['course_name', 'tees', 'rating_t', 'slope'])->toArray())
                ->toBe([['course_name' => 'BETHPAGE BLACK', 'tees' => 'Red', 'rating_t' => 714, 'slope' => 144]]);
        });

        test('a rating typed with a point and the upper bounds are stored', function () {
            $this->from('/courses')
                ->put('/courses/rating', ['courseName' => 'BETHPAGE BLACK', 'tees' => 'Red', 'rating' => '90.0', 'slope' => '155'])
                ->assertRedirect('/courses')
                ->assertSessionHasNoErrors();

            expect(CourseRating::query()->get(['rating_t', 'slope'])->toArray())
                ->toBe([['rating_t' => 900, 'slope' => 155]]);
        });

        test('the lower bounds are stored with their toast', function () {
            $this->from('/courses')
                ->put('/courses/rating', ['courseName' => 'BETHPAGE BLACK', 'tees' => 'Red', 'rating' => '40,0', 'slope' => '55'])
                ->assertRedirect('/courses')
                ->assertSessionHasNoErrors()
                ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Rating enregistrée.']);

            expect(CourseRating::query()->get(['rating_t', 'slope'])->toArray())
                ->toBe([['rating_t' => 400, 'slope' => 55]]);
        });

        test('a slope out of bounds or not whole gives the slope error and writes nothing', function (string $slope) {
            $this->from('/courses')
                ->put('/courses/rating', ['courseName' => 'BETHPAGE BLACK', 'tees' => 'Red', 'rating' => '71,4', 'slope' => $slope])
                ->assertRedirect('/courses')
                ->assertSessionHasErrors(['slope' => 'Slope entre 55 et 155'])
                ->assertSessionDoesntHaveErrors('rating');

            expect(CourseRating::query()->count())->toBe(0);
        })->with(['above 155' => ['160'], 'not whole' => ['144,5']]);

        test('a badly formed or empty rating gives the rating error and writes nothing', function (string $rating) {
            $this->from('/courses')
                ->put('/courses/rating', ['courseName' => 'BETHPAGE BLACK', 'tees' => 'Red', 'rating' => $rating, 'slope' => '144'])
                ->assertRedirect('/courses')
                ->assertSessionHasErrors(['rating' => 'Rating entre 40,0 et 90,0'])
                ->assertSessionDoesntHaveErrors('slope');

            expect(CourseRating::query()->count())->toBe(0);
        })->with(['two decimals' => ['71,45'], 'empty' => ['']]);

        test('a course without round goes back with the error toast and writes nothing', function () {
            $this->from('/courses')
                ->put('/courses/rating', ['courseName' => 'PAYNES VALLEY', 'tees' => 'Blue', 'rating' => '71,4', 'slope' => '144'])
                ->assertRedirect('/courses')
                ->assertInertiaFlash('toast', ['type' => 'error', 'message' => 'Parcours introuvable']);

            expect(CourseRating::query()->count())->toBe(0);
        });
    });
});
