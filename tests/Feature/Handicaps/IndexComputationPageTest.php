<?php

/*
 * Spec handicap-index, « Page « Calcul de l'index » »: « Ouverture », « Choix d'un joueur »,
 * « Joueur inconnu », « Sans session », « Aucun joueur »; design D1 and D4 of
 * index-computation-page.
 *
 * Mutations that turn these tests red:
 * - an unknown player identifier that answers 404 (abort_unless(in_array(...), 404)): ?joueur=
 *   0190a6b2-7c3e-7000-8000-000000000099 and ?joueur=12 answer 404 instead of 200 with Gab;
 * - the computation of the first player by name instead of the selected one: ?joueur=<Manu>
 *   reads Gab's 72 instead of Manu's 88;
 * - the route left out of the passcode group (withoutMiddleware(RequirePasscode::class)): the
 *   page answers 200 without a session, with Gab's name;
 * - the computation read once per round: the number of queries grows with the rounds.
 */

use Inertia\Testing\AssertableInertia as Assert;

test('without a session /handicaps/calcul redirects to /login and shows no player nor score', function () {
    createPlayersOnTest('Gab', 'Manu');
    createRoundCard(referenceCardData($this->gab->id, $this->manu->id));

    $this->get('/handicaps/calcul')
        ->assertRedirect('/login')
        ->assertDontSee('Gab')
        ->assertDontSee('Manu')
        ->assertDontSee('88');
});

describe('with a session', function () {
    beforeEach(function () {
        $this->withPasscodeSession();
    });

    test('without player the page has no selection and no computation', function () {
        $this->get('/handicaps/calcul')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('handicaps/computation')
                ->where('players', [])
                ->where('selectedPlayerId', null)
                ->where('computation', null));
    });

    test('a sub-address of the Handicaps page still answers 404', function () {
        $this->get('/handicaps/0190a6b2-7c3e-7000-8000-000000000001')->assertNotFound();
    });

    describe('with Gab, Manu and Sean and the reference card', function () {
        beforeEach(function () {
            $this->reference = createReferenceRoundOfThree();
        });

        test('the page opens on the first player by name with the computation of that player', function () {
            $this->get('/handicaps/calcul')
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->component('handicaps/computation')
                    ->where('players.0.name', 'Gab')
                    ->where('players.1.name', 'Manu')
                    ->where('players.2.name', 'Sean')
                    ->has('players', 3)
                    ->where('selectedPlayerId', $this->gab->id)
                    ->where('computation', [
                        'rounds' => [[
                            'roundId' => $this->reference->id,
                            'playedOn' => '2026-10-17',
                            'courseName' => 'BETHPAGE BLACK',
                            'gross' => 72,
                            'adjusted' => 72,
                            'parTotal' => 71,
                            'ratingT' => null,
                            'slope' => null,
                            'differentialT' => 10,
                            'countsInCurrent' => true,
                        ]],
                        'cappedHole' => null,
                        'meanT' => 10,
                        'adjustmentT' => 0,
                    ]));
        });

        test('the player named in the address is selected', function () {
            $this->get('/handicaps/calcul?joueur='.$this->manu->id)
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->where('selectedPlayerId', $this->manu->id)
                    ->where('computation.rounds.0.gross', 88)
                    ->where('computation.rounds.0.differentialT', 170)
                    ->has('computation.rounds', 1));
        });

        test('a player without round has an empty computation', function () {
            $this->get('/handicaps/calcul?joueur='.$this->sean->id)
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->where('selectedPlayerId', $this->sean->id)
                    ->where('computation', ['rounds' => [], 'cappedHole' => null, 'meanT' => null, 'adjustmentT' => 0]));
        });

        test('an unknown or malformed player opens the first player', function (string $joueur) {
            $this->get('/handicaps/calcul?joueur='.$joueur)
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->where('selectedPlayerId', $this->gab->id)
                    ->where('computation.rounds.0.roundId', $this->reference->id));
        })->with([
            'unknown' => ['0190a6b2-7c3e-7000-8000-000000000099'],
            'malformed' => ['12'],
        ]);
    });

    test('the page makes the same number of queries whatever the number of rounds', function (int $count) {
        createPlayersOnTest('Gab', 'Manu', 'Sean');
        createDailyRoundsOfThree($count);

        $queries = queriesDuring(fn () => $this->get('/handicaps/calcul?joueur='.$this->manu->id)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('computation.rounds', min($count, 20))));

        // The players and their current statistics (2), the 20 latest rounds (1), the capped
        // hole (1), the count of the imports to check shared with every page (1, DO35).
        expect($queries)->toBe(5);
    })->with([10, 30]);
});
