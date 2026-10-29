<?php

/*
 * Spec home-dashboard, « Accueil protégé », « Index des joueurs », « Dernier round », « Imports à
 * vérifier »; design D6 and D8.
 *
 * Mutations that turn these tests red:
 * - in App\Support\AppBuild::commit(), accept 7 characters ('/^[0-9a-f]{7,40}$/'): the short
 *   sha 4794e1b then reaches the page;
 * - latestRound taken on the latest round saved (Round::query()->latest()->first()): the round
 *   of 2026-10-10, saved after the reference card, comes back instead of BETHPAGE BLACK of
 *   2026-10-17;
 * - the home route served without HomeController (Route::inertia('/', 'home') again): the page
 *   receives no players, latestRound nor pendingImports.
 */

use App\Models\Import;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

test('without a session the home page redirects to /login and shows no player', function () {
    createPlayersOnTest('Gab');

    $this->get('/')
        ->assertRedirect('/login')
        ->assertDontSee('Gab');
});

describe('with a session', function () {
    beforeEach(function () {
        $this->withPasscodeSession();
    });

    test('the home page responds', function () {
        $this->get('/')->assertOk();
    });

    test('the home page receives the version and the full commit sha', function () {
        config([
            'app.version' => '0.6.0',
            'app.commit' => '4794e1b0c0ffee00000000000000000000000000',
        ]);

        $this->get('/')->assertInertia(fn (Assert $page) => $page
            ->where('app', [
                'version' => '0.6.0',
                'commit' => '4794e1b0c0ffee00000000000000000000000000',
            ]));
    });

    test('the home page receives a null commit when the sha is malformed', function () {
        config([
            'app.version' => '0.6.0',
            'app.commit' => '4794e1b',
        ]);

        $this->get('/')->assertInertia(fn (Assert $page) => $page
            ->where('app', [
                'version' => '0.6.0',
                'commit' => null,
            ]));
    });

    test('the home page receives the indexes, the latest round played and the imports to check', function () {
        createPlayersOnTest('Gab', 'Manu', 'Sean');
        // The reference card is saved first, the round of 2026-10-10 an hour later: the latest
        // round played is not the latest saved.
        $this->travelTo(CarbonImmutable::parse('2026-10-18 10:00:00', 'UTC'));
        $reference = createRoundCard(referenceCardData($this->gab->id, $this->manu->id));
        $this->travelTo(CarbonImmutable::parse('2026-10-18 11:00:00', 'UTC'));
        createRoundCard(referenceCardOn('2026-10-10', [
            [$this->gab->id, strokesOverPar(1)],
            [$this->manu->id, strokesOverPar(17)],
        ]));
        $import = Import::factory()->ready()->create();

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('home')
                ->where('players', [
                    ['id' => $this->gab->id, 'name' => 'Gab', 'indexT' => 10, 'provisional' => true, 'deltaT' => 0],
                    ['id' => $this->manu->id, 'name' => 'Manu', 'indexT' => 170, 'provisional' => true, 'deltaT' => 0],
                    ['id' => $this->sean->id, 'name' => 'Sean', 'indexT' => null, 'provisional' => false, 'deltaT' => null],
                ])
                ->where('latestRound', [
                    'id' => $reference->id,
                    'playedOn' => '2026-10-17',
                    'courseName' => 'BETHPAGE BLACK',
                    'tees' => 'Red',
                    'players' => [['name' => 'Gab', 'gross' => 72], ['name' => 'Manu', 'gross' => 88]],
                    'netWinners' => ['Gab', 'Manu'],
                ])
                ->where('pendingImports', ['count' => 1, 'latestId' => $import->id]));
    });

    test('without round the latest round is null and nothing is to check', function () {
        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('home')
                ->where('players', [])
                ->where('latestRound', null)
                ->where('pendingImports', ['count' => 0, 'latestId' => null]));
    });

    test('the home page makes the same number of queries whatever the number of rounds', function (int $count) {
        createPlayersOnTest('Gab', 'Manu', 'Sean');
        createDailyRoundsOfThree($count);

        $queries = queriesDuring(fn () => $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('latestRound')));

        // The players and their current statistics (2), the latest round with its lines, their
        // players, their gross and their statistics (5), the imports to check (1).
        expect($queries)->toBe(8);
    })->with([10, 30]);
});
