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
 *   receives no players nor latestRound.
 *
 * Visual-redesign (home-dashboard MODIFIED, DO2, DO6, DO17, DO21):
 * - the players kept by name (PlayerStandings::get() instead of ranked()): Bob, Gab, Manu…
 *   instead of Bob, Manu, Gab…;
 * - the players without index sorted first (null as 0): alice and Sean before Bob;
 * - the latest round without its net leaderboard (RoundList again): no playingHandicap, net nor
 *   rank on Gab and Manu;
 * - the home page that still sends its own pendingImports: the prop is present on the first load
 *   (the shared one is deferred).
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
        Import::factory()->ready()->create();

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('home')
                ->where('players', [
                    ['id' => $this->gab->id, 'name' => 'Gab', 'indexT' => 10, 'provisional' => true, 'deltaT' => 0, 'rounds' => 2, 'colorSlot' => 0],
                    ['id' => $this->manu->id, 'name' => 'Manu', 'indexT' => 170, 'provisional' => true, 'deltaT' => 0, 'rounds' => 2, 'colorSlot' => 1],
                    ['id' => $this->sean->id, 'name' => 'Sean', 'indexT' => null, 'provisional' => false, 'deltaT' => null, 'rounds' => 0, 'colorSlot' => 2],
                ])
                ->where('latestRound', [
                    'id' => $reference->id,
                    'playedOn' => '2026-10-17',
                    'courseName' => 'BETHPAGE BLACK',
                    'tees' => 'Red',
                    'leaderboard' => [
                        ['playerId' => $this->gab->id, 'name' => 'Gab', 'colorSlot' => 0, 'gross' => 72, 'indexBeforeT' => 10, 'playingHandicap' => 1, 'net' => 71, 'rank' => 1],
                        ['playerId' => $this->manu->id, 'name' => 'Manu', 'colorSlot' => 1, 'gross' => 88, 'indexBeforeT' => 170, 'playingHandicap' => 17, 'net' => 71, 'rank' => 1],
                    ],
                ])
                ->missing('pendingImports'));
    });

    test('the players come by index, by name whatever the case at an equal index, those without index last', function () {
        createPlayersOnTest('Gab', 'Manu', 'Sean', 'alice', 'Bob');
        createRoundCard(referenceCardOn('2026-10-17', [
            [$this->manu->id, strokesOverPar(1)],
            [$this->gab->id, strokesOverPar(17)],
            [$this->bob->id, strokesOverPar(1)],
        ]));

        $players = $this->get('/')->inertiaProps('players');

        expect(array_map(fn (array $player): array => [$player['name'], $player['indexT'], $player['rounds']], $players))->toBe([
            ['Bob', 10, 1],
            ['Manu', 10, 1],
            ['Gab', 170, 1],
            ['alice', null, 0],
            ['Sean', null, 0],
        ]);
    });

    test('without round the latest round is null and nothing is to check', function () {
        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('home')
                ->where('players', [])
                ->where('latestRound', null));
    });

    test('the home page makes the same number of queries whatever the number of rounds', function (int $count) {
        createPlayersOnTest('Gab', 'Manu', 'Sean');
        createDailyRoundsOfThree($count);

        $queries = queriesDuring(fn () => $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('latestRound')));

        // The players with their colour slot and their current statistics (2), the latest round,
        // its rating, its lines with their gross and index before, and its holes (4). The imports
        // to check are a deferred shared prop, not read on this first load.
        expect($queries)->toBe(6);
    })->with([10, 30]);
});
