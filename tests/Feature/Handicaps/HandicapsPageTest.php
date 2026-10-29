<?php

/*
 * Spec handicap-index, « Un joueur à la fois sur la page Handicaps »: « Ouverture », « Choix d'un
 * joueur », « Joueur inconnu », « Aucun joueur », « Sans session », « Sous-adresse inconnue »;
 * design D4 and D8.
 *
 * Mutations that turn these tests red:
 * - an unknown player identifier that answers 404 (abort_unless(in_array(...), 404) in
 *   HandicapController): ?joueur=0190a6b2-7c3e-7000-8000-000000000099 and ?joueur=12 answer
 *   404 instead of 200 with Gab;
 * - the history of every player loaded (the player filter removed from PlayerHandicapHistory):
 *   Gab's history holds Manu's line of the reference card too.
 */

use Inertia\Testing\AssertableInertia as Assert;

test('without a session /handicaps redirects to /login and shows no player', function () {
    createPlayersOnTest('Gab');

    $this->get('/handicaps')
        ->assertRedirect('/login')
        ->assertDontSee('Gab');
});

describe('with a session', function () {
    beforeEach(function () {
        $this->withPasscodeSession();
    });

    test('without player the page has no selection and no history', function () {
        $this->get('/handicaps')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('handicaps/index')
                ->where('players', [])
                ->where('selectedPlayerId', null)
                ->where('history', []));
    });

    test('a sub-address of the Handicaps page answers 404', function () {
        $this->get('/handicaps/0190a6b2-7c3e-7000-8000-000000000001')->assertNotFound();
    });

    describe('with Gab, Manu and Sean and the reference card', function () {
        beforeEach(function () {
            createPlayersOnTest('Gab', 'Manu', 'Sean');
            $this->reference = createRoundCard(referenceCardData($this->gab->id, $this->manu->id));
        });

        test('the page opens on the first player by name with his history', function () {
            $this->get('/handicaps')
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->component('handicaps/index')
                    ->where('players', [
                        ['id' => $this->gab->id, 'name' => 'Gab', 'indexT' => 10, 'provisional' => true, 'deltaT' => null, 'rounds' => 1, 'colorSlot' => 0],
                        ['id' => $this->manu->id, 'name' => 'Manu', 'indexT' => 170, 'provisional' => true, 'deltaT' => null, 'rounds' => 1, 'colorSlot' => 1],
                        ['id' => $this->sean->id, 'name' => 'Sean', 'indexT' => null, 'provisional' => false, 'deltaT' => null, 'rounds' => 0, 'colorSlot' => 2],
                    ])
                    ->where('selectedPlayerId', $this->gab->id)
                    ->where('history', [[
                        'roundId' => $this->reference->id,
                        'playedOn' => '2026-10-17',
                        'courseName' => 'BETHPAGE BLACK',
                        'tees' => 'Red',
                        'differentialT' => 10,
                        'indexAfterT' => 10,
                        'countsInCurrent' => true,
                    ]]));
        });

        test('the player named in the address is selected', function () {
            $this->get('/handicaps?joueur='.$this->manu->id)
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->where('selectedPlayerId', $this->manu->id)
                    ->where('history.0.differentialT', 170)
                    ->has('history', 1));
        });

        test('an unknown or malformed player opens the first player', function (string $joueur) {
            $this->get('/handicaps?joueur='.$joueur)
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->where('selectedPlayerId', $this->gab->id)
                    ->where('history.0.roundId', $this->reference->id));
        })->with([
            'unknown' => ['0190a6b2-7c3e-7000-8000-000000000099'],
            'malformed' => ['12'],
        ]);
    });

    test('a player with 21 rounds lists them all and his oldest round does not count', function () {
        // Spec handicap-index, « Liste des rounds d'un joueur »: « Plus de 20 rounds ». The
        // oldest round, +0, is Manu's best differential; the 20 others are at +17.
        // Mutation that turns this test red: the index computed on every round (the
        // array_slice to the 20 latest removed from Whs::computeIndex): the round of
        // 2026-09-01 counts.
        createPlayersOnTest('Manu');
        createRoundCard(referenceCardOn('2026-09-01', [[$this->manu->id, strokesOverPar(0)]]));
        foreach (range(2, 21) as $day) {
            createRoundCard(referenceCardOn(sprintf('2026-09-%02d', $day), [[$this->manu->id, strokesOverPar(17)]]));
        }

        $this->get('/handicaps?joueur='.$this->manu->id)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('history', 21)
                ->where('history.20.playedOn', '2026-09-01')
                ->where('history.20.differentialT', 0)
                ->where('history.20.countsInCurrent', false)
                ->where('history.0.playedOn', '2026-09-21')
                ->where('history.0.indexAfterT', 170));
    });

    test('the page makes the same number of queries whatever the number of rounds', function (int $count) {
        createPlayersOnTest('Gab', 'Manu', 'Sean');
        createDailyRoundsOfThree($count);

        $queries = queriesDuring(fn () => $this->get('/handicaps?joueur='.$this->manu->id)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('history', $count)));

        // The players and their current statistics (2), the history of the selected player (1).
        expect($queries)->toBe(3);
    })->with([10, 30]);
});
