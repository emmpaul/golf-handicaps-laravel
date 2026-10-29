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
 *
 * Course-rating-sgt, design D8:
 * - the The Course View link taken from the name of the round (« THE SANCTUARY ») instead of the
 *   SGT name (« Sanctuary »): the-sanctuary instead of sanctuary;
 * - lookupPending always true: the page without pending row reads true;
 * - the note exposed on a confirmed row (decision Q18): « Ancienne note » instead of null;
 * - the proposal read from proposed_tee alone, without the tees read: the proposal of the tee
 *   Red reads no rating;
 * - the candidates without their scorecard link: no scorecardUrl on « Sanctuary ».
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
 * The lookup part of a course of the page without row of course_ratings (design D8).
 *
 * @return array<string, mixed>
 */
function withoutLookup(): array
{
    return [
        'id' => null, 'status' => null, 'label' => null, 'note' => null, 'source' => null,
        'sgtCourseName' => null, 'sgtCourseLocation' => null, 'proposal' => null,
        'teesRead' => [], 'candidates' => [], 'scorecardUrl' => null, 'courseViewUrl' => null,
    ];
}

/**
 * The five tees read on scorecard_30.jpg, as the page receives them.
 *
 * @return list<array{name: string, ratingT: int, slope: int}>
 */
function bethpageTeesRead(): array
{
    return array_map(
        fn (array $tee): array => ['name' => $tee[0], 'ratingT' => $tee[1], 'slope' => $tee[2]],
        [['Blue', 775, 155], ['White', 742, 148], ['Red', 714, 144], ['Junior', 710, 120], ['PAR3', 540, 120]],
    );
}

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

    // Only the row of the lookup of the round (SGT_LOOKUP off), without rating.
    expect(CourseRating::query()->pluck('rating_t')->all())->toBe([null]);
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
            // The rows of the lookups of both rounds (SGT_LOOKUP off) give way to the rating alone.
            CourseRating::query()->delete();
            $this->rating = CourseRating::factory()->bethpageRed()->create();
        });

        test('the page lists each course with the par of its latest round and its rating', function () {
            $this->get('/courses')
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->component('courses/index')
                    ->where('courses', [
                        [
                            'courseName' => 'BETHPAGE BLACK', 'tees' => 'Red', 'par' => 71, 'ratingT' => 714, 'slope' => 144, 'strokeIndexes' => BETHPAGE_RED_STROKE_INDEXES,
                            ...withoutLookup(),
                            'id' => $this->rating->id, 'status' => 'confirmed', 'label' => 'Confirmé', 'source' => 'manual',
                        ],
                        ['courseName' => 'PAYNES VALLEY', 'tees' => 'Blue', 'par' => 72, 'ratingT' => null, 'slope' => null, 'strokeIndexes' => BETHPAGE_RED_STROKE_INDEXES, ...withoutLookup()],
                    ])
                    ->where('lookupPending', false));
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

            $courses = app(CourseOverview::class)->get();

            expect(array_map(fn (array $course): array => [$course['courseName'], $course['tees'], $course['ratingT'], $course['slope'], $course['id']], $courses))->toBe([
                ['BETHPAGE BLACK', 'Red', 714, 144, $this->rating->id],
                ['PAYNES VALLEY', 'Blue', null, null, null],
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

            expect(CourseRating::query()->pluck('rating_t')->all())->toBe([null]);
        })->with(['above 155' => ['160'], 'not whole' => ['144,5']]);

        test('a badly formed or empty rating gives the rating error and writes nothing', function (string $rating) {
            $this->from('/courses')
                ->put('/courses/rating', ['courseName' => 'BETHPAGE BLACK', 'tees' => 'Red', 'rating' => $rating, 'slope' => '144'])
                ->assertRedirect('/courses')
                ->assertSessionHasErrors(['rating' => 'Rating entre 40,0 et 90,0'])
                ->assertSessionDoesntHaveErrors('slope');

            expect(CourseRating::query()->pluck('rating_t')->all())->toBe([null]);
        })->with(['two decimals' => ['71,45'], 'empty' => ['']]);

        test('a course without round goes back with the error toast and writes nothing', function () {
            $this->from('/courses')
                ->put('/courses/rating', ['courseName' => 'PAYNES VALLEY', 'tees' => 'Blue', 'rating' => '71,4', 'slope' => '144'])
                ->assertRedirect('/courses')
                ->assertInertiaFlash('toast', ['type' => 'error', 'message' => 'Parcours introuvable']);

            expect(CourseRating::query()->pluck('rating_t')->all())->toBe([null]);
        });
    });

    describe('with the reference card and a row of its lookup', function () {
        beforeEach(function () {
            createRoundCard(referenceCardData($this->gab->id, $this->manu->id));
            // The row of the lookup of the card (SGT_LOOKUP off) gives way to the state under test.
            CourseRating::query()->delete();
        });

        test('a proposal gives its SGT course, its proposed tee and its links', function () {
            $rating = CourseRating::factory()->proposed()->create();

            $this->get('/courses')->assertInertia(fn (Assert $page) => $page
                ->where('courses', [[
                    'courseName' => 'BETHPAGE BLACK', 'tees' => 'Red', 'par' => 71, 'ratingT' => null, 'slope' => null,
                    'strokeIndexes' => BETHPAGE_RED_STROKE_INDEXES,
                    'id' => $rating->id, 'status' => 'proposed', 'label' => 'Proposition à confirmer', 'note' => null, 'source' => null,
                    'sgtCourseName' => 'Bethpage Black', 'sgtCourseLocation' => 'Farmingdale, NY',
                    'proposal' => ['name' => 'Red', 'ratingT' => 714, 'slope' => 144],
                    'teesRead' => bethpageTeesRead(), 'candidates' => [],
                    'scorecardUrl' => 'https://simulatorgolftour.com/public/assets/courseImages/scorecards/scorecard_30.jpg',
                    'courseViewUrl' => 'https://thecourseview.com/courses/bethpage-black',
                ]])
                ->where('lookupPending', false));
        });

        test('the candidates come each with its scorecard link', function () {
            CourseRating::factory()->chooseCourse()->create(['course_name' => 'BETHPAGE BLACK', 'tees' => 'Red']);

            $course = $this->get('/courses')->inertiaProps('courses.0');

            expect([$course['status'], $course['label'], $course['candidates'], $course['scorecardUrl'], $course['courseViewUrl']])->toBe([
                'choose_course', 'Parcours SGT à choisir',
                [
                    ['id' => 477, 'name' => 'Sanctuary', 'location' => 'Sedalia, CO', 'par' => 72, 'scorecardUrl' => 'https://simulatorgolftour.com/public/assets/courseImages/scorecards/scorecard_477.jpg'],
                    ['id' => 1951, 'name' => 'The Pines Sanctuary Cove', 'location' => 'Hope Island, Queensland, Australia', 'par' => 72, 'scorecardUrl' => 'https://simulatorgolftour.com/public/assets/courseImages/scorecards/scorecard_1951.jpg'],
                ],
                null, null,
            ]);
        });

        test('the The Course View link is the slug of the SGT name, not of the name of the round', function () {
            $card = referenceCardOnCourse($this->gab->id, 'THE SANCTUARY', 'White', '2026-10-18');
            createRoundCard($card);
            CourseRating::query()->delete();
            CourseRating::factory()->proposed()->create([
                'course_name' => 'THE SANCTUARY', 'tees' => 'White', 'proposed_tee' => 'White',
                'sgt_course_id' => 477, 'sgt_course_name' => 'Sanctuary', 'sgt_course_location' => 'Sedalia, CO',
            ]);

            $course = $this->get('/courses')->inertiaProps('courses.1');

            expect([$course['courseName'], $course['courseViewUrl'], $course['proposal']])->toBe([
                'THE SANCTUARY', 'https://thecourseview.com/courses/sanctuary', ['name' => 'White', 'ratingT' => 742, 'slope' => 148],
            ]);
        });

        test('a confirmed row exposes no note', function () {
            CourseRating::factory()->confirmedFromSgt()->create(['note' => 'Ancienne note']);

            $course = $this->get('/courses')->inertiaProps('courses.0');

            expect([$course['status'], $course['label'], $course['note'], $course['source'], $course['ratingT'], $course['slope']])
                ->toBe(['confirmed', 'Confirmé', null, 'sgt', 714, 144]);
        });

        test('a row in failure exposes its note', function () {
            CourseRating::factory()->failed()->create(['course_name' => 'BETHPAGE BLACK', 'tees' => 'Red']);

            $course = $this->get('/courses')->inertiaProps('courses.0');

            expect([$course['status'], $course['label'], $course['note']])->toBe(['failed', 'Recherche échouée', 'SGT injoignable']);
        });

        test('a pending row makes the lookup pending', function () {
            CourseRating::factory()->pending()->create(['course_name' => 'BETHPAGE BLACK', 'tees' => 'Red']);

            $this->get('/courses')->assertInertia(fn (Assert $page) => $page
                ->where('courses.0.status', 'pending')
                ->where('courses.0.label', 'Recherche en cours')
                ->where('lookupPending', true));
        });

        test('a pair without row has no id nor status and no lookup pending', function () {
            $this->get('/courses')->assertInertia(fn (Assert $page) => $page
                ->where('courses.0.id', null)
                ->where('courses.0.status', null)
                ->where('lookupPending', false));
        });
    });
});
