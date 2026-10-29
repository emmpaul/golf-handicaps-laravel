<?php

/*
 * Spec match-strokes, « Page Coups protégée et sans écriture »: « Sans session », « Aucune requête
 * au choix », « Sous-adresse inconnue »; « Préparation d'une partie »: « Joueur sans index »;
 * « Rating du parcours choisi »; design D5 and D8.
 *
 * Mutation that turns these tests red: the players without index left out of the props
 * (array_filter on indexT !== null in StrokesController): Sean is missing although the page
 * lists him greyed out.
 */

use App\Models\CourseRating;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

test('without a session /strokes redirects to /login and shows no player nor course', function () {
    createPlayersOnTest('Gab');
    createRoundCard(referenceCardOn('2026-10-17', [[$this->gab->id, strokesOverPar(1)]]));

    $this->get('/strokes')
        ->assertRedirect('/login')
        ->assertDontSee('Gab')
        ->assertDontSee('BETHPAGE BLACK');
});

describe('with a session', function () {
    beforeEach(function () {
        $this->withPasscodeSession();
        createPlayersOnTest('Gab', 'Manu', 'Sean');
    });

    test('a sub-address of the Strokes page answers 404', function () {
        $this->get('/strokes/xyz')->assertNotFound();
    });

    test('the page receives every player with his index and the known courses with their rating and stroke indexes', function () {
        // Gab: 72 on PAYNES VALLEY (par 72), differential 0,0, then 72 on the reference card
        // (par 71), 1,0: a provisional index of 0,5.
        $paynes = referenceCardOn('2026-10-10', [[$this->gab->id, strokesOverPar(1)]]);
        $paynes['courseName'] = 'PAYNES VALLEY';
        $paynes['tees'] = 'Blue';
        $paynes['holes'][0]['par'] = 5;
        createRoundCard($paynes);
        createRoundCard(referenceCardData($this->gab->id, $this->manu->id));
        // The rows of the lookups of both rounds (SGT_LOOKUP off) give way to the rating alone.
        CourseRating::query()->delete();
        CourseRating::factory()->bethpageRed()->create();

        $writes = [];
        DB::listen(function (QueryExecuted $query) use (&$writes): void {
            if (preg_match('/^\s*(insert|update|delete)\b/i', $query->sql) === 1) {
                $writes[] = $query->sql;
            }
        });

        $this->get('/strokes')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('strokes/index')
                ->where('players', [
                    ['id' => $this->gab->id, 'name' => 'Gab', 'indexT' => 5],
                    ['id' => $this->manu->id, 'name' => 'Manu', 'indexT' => 170],
                    ['id' => $this->sean->id, 'name' => 'Sean', 'indexT' => null],
                ])
                ->where('courses', [
                    [
                        'courseName' => 'BETHPAGE BLACK',
                        'tees' => 'Red',
                        'par' => 71,
                        'ratingT' => 714,
                        'slope' => 144,
                        'strokeIndexes' => [8, 16, 18, 2, 4, 10, 6, 14, 12, 9, 11, 7, 3, 17, 1, 5, 13, 15],
                    ],
                    [
                        'courseName' => 'PAYNES VALLEY',
                        'tees' => 'Blue',
                        'par' => 72,
                        'ratingT' => null,
                        'slope' => null,
                        'strokeIndexes' => [8, 16, 18, 2, 4, 10, 6, 14, 12, 9, 11, 7, 3, 17, 1, 5, 13, 15],
                    ],
                ]));

        expect($writes)->toBe([]);
    });

    test('a rating typed on /courses between two visits reaches the next visit of /strokes', function () {
        // Spec match-strokes, « Rating du parcours choisi »: « Rating saisie entre deux visites ».
        // Mutation that turns this test red: the courses of the Strokes page kept from one
        // visit to the next (Cache::rememberForever around $courses->get() in
        // StrokesController): the second visit still reads BETHPAGE BLACK / Red without rating.
        createRoundCard(referenceCardData($this->gab->id, $this->manu->id));

        $this->get('/strokes')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('courses.0.courseName', 'BETHPAGE BLACK')
                ->where('courses.0.ratingT', null)
                ->where('courses.0.slope', null));

        $this->put('/courses/rating', ['courseName' => 'BETHPAGE BLACK', 'tees' => 'Red', 'rating' => '71,4', 'slope' => '144'])
            ->assertRedirect();

        $this->get('/strokes')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('courses', 1)
                ->where('courses.0.courseName', 'BETHPAGE BLACK')
                ->where('courses.0.tees', 'Red')
                ->where('courses.0.ratingT', 714)
                ->where('courses.0.slope', 144));
    });

    test('without round the page receives no course', function () {
        $this->get('/strokes')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('courses', [])->has('players', 3));
    });

    test('the page makes the same number of queries whatever the number of rounds', function (int $count) {
        createDailyRoundsOfThree($count);

        $queries = queriesDuring(fn () => $this->get('/strokes')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('courses', 1)));

        // The players and their current statistics (2), the latest round of each course with its
        // rating, and their holes (2).
        expect($queries)->toBe(4);
    })->with([10, 30]);
});
