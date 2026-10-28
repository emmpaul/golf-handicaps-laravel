<?php

/*
 * Spec rounds « Liste des rounds », « En-tête d'un round », « Bruts d'un round », « Round
 * introuvable », « Saisie entièrement manuelle », « Parcours connus », « Correction d'un round
 * enregistré », « Détection de doublon », « Suppression d'un round »; design D3, D5 and D8.
 *
 * Mutations that turn these tests red:
 * - RoundList sorted without created_at (played_on then id): of the two rounds of 2026-10-17,
 *   the one with the greater id comes first although it was saved earlier;
 * - `today` taken in UTC (now()->toDateString()): at 2026-10-18 03:30 UTC the page gets
 *   2026-10-18 instead of 2026-10-17;
 * - with() removed from RoundList: each round loads its lines and players, the query count of
 *   /rounds with 10 rounds goes up;
 * - the gross listed in the order of the players' names instead of the card: Manu before Gab
 *   on a card where Manu is line 0;
 * - `force` ignored by store: the duplicate saved with force is refused again;
 * - the duplicate looked for without `except` on update: correcting the reference round
 *   unchanged finds itself and comes back with the duplicate flash;
 * - RoundDetail that reads the holes of the latest round on the same course and tees
 *   (Round::query()->where('course_name', …)->where('tees', …)->latest()->first()->holes)
 *   instead of the round's own: the first round reads the par 5 of the second at hole 1.
 */

use App\Domain\Rounds\Actions\CreateRound;
use App\Domain\Rounds\RoundCard;
use App\Models\Player;
use App\Models\Round;
use App\Models\RoundHole;
use App\Models\Score;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

const UNKNOWN_ROUND = '0190a6b2-7c3e-7000-8000-000000000001';

/**
 * Save a card through CreateRound, at the given instant of saving.
 *
 * @param  array<string, mixed>  $card
 */
function storeRoundCard(array $card, string $savedAt = '2026-10-18 10:00:00'): Round
{
    test()->travelTo(CarbonImmutable::parse($savedAt, 'UTC'));

    return app(CreateRound::class)->handle(RoundCard::fromArray($card));
}

test('without a session every round address redirects to /login', function (string $uri) {
    $this->get($uri)->assertRedirect('/login');
})->with([
    'list' => ['/rounds'],
    'new' => ['/rounds/new'],
    'round' => ['/rounds/'.UNKNOWN_ROUND],
    'edit' => ['/rounds/'.UNKNOWN_ROUND.'/edit'],
    'photo' => ['/rounds/'.UNKNOWN_ROUND.'/photo'],
]);

describe('with a session', function () {
    beforeEach(function () {
        $this->withPasscodeSession();
        $this->gab = Player::factory()->create(['name' => 'Gab']);
        $this->manu = Player::factory()->create(['name' => 'Manu']);
    });

    test('a round address that is not a UUID or names no round answers 404', function (string $uri) {
        $this->get($uri)->assertNotFound();
    })->with([
        'numeric' => ['/rounds/12'],
        'numeric edit' => ['/rounds/12/edit'],
        'numeric photo' => ['/rounds/12/photo'],
        'unknown' => ['/rounds/'.UNKNOWN_ROUND],
        'unknown edit' => ['/rounds/'.UNKNOWN_ROUND.'/edit'],
        'unknown photo' => ['/rounds/'.UNKNOWN_ROUND.'/photo'],
        'other sub-address' => ['/rounds/xyz'],
    ]);

    test('a write to an unknown round answers 404', function () {
        $this->put('/rounds/'.UNKNOWN_ROUND, referenceCardData($this->gab->id, $this->manu->id))->assertNotFound();
        $this->delete('/rounds/'.UNKNOWN_ROUND)->assertNotFound();
    });

    test('the list goes from the latest round to the oldest, by date of play then of saving', function () {
        $older = referenceCardData($this->gab->id, $this->manu->id);
        $older['playedOn'] = '2026-10-10';
        // Saved first, so with the smaller id, but with the later date of saving.
        $savedLater = storeRoundCard(referenceCardData($this->gab->id, $this->manu->id), '2026-10-18 10:00:00');
        $savedEarlier = storeRoundCard(referenceCardData($this->gab->id, $this->manu->id), '2026-10-18 11:00:00');
        $savedEarlier->forceFill(['created_at' => '2026-10-18 09:00:00'])->save();
        storeRoundCard($older, '2026-10-19 10:00:00');

        $response = $this->get('/rounds')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('rounds/index'));

        $rounds = $response->inertiaProps('rounds');

        expect(array_column($rounds, 'playedOn'))->toBe(['2026-10-17', '2026-10-17', '2026-10-10'])
            ->and($rounds[0]['id'])->toBe($savedLater->id)
            ->and($rounds[1]['id'])->toBe($savedEarlier->id)
            ->and($rounds[0])->toBe([
                'id' => $savedLater->id,
                'playedOn' => '2026-10-17',
                'courseName' => 'BETHPAGE BLACK',
                'tees' => 'Red',
                'players' => [['name' => 'Gab', 'gross' => 72], ['name' => 'Manu', 'gross' => 88]],
            ]);
    });

    test('the gross of the list follow the order of the card', function () {
        $card = referenceCardData($this->manu->id, $this->gab->id);
        storeRoundCard($card);

        $this->get('/rounds')->assertInertia(fn (Assert $page) => $page
            ->where('rounds.0.players', [['name' => 'Manu', 'gross' => 72], ['name' => 'Gab', 'gross' => 88]]));
    });

    test('without round the list is empty', function () {
        $this->get('/rounds')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('rounds/index')->where('rounds', []));
    });

    test('the list makes the same number of queries whatever the number of rounds', function () {
        foreach (range(1, 10) as $day) {
            $card = referenceCardData($this->gab->id, $this->manu->id);
            $card['playedOn'] = sprintf('2026-10-%02d', $day);
            storeRoundCard($card);
        }

        $queries = 0;
        DB::listen(function () use (&$queries) {
            $queries++;
        });

        $this->get('/rounds')->assertOk()->assertInertia(fn (Assert $page) => $page->has('rounds', 10));

        // The rounds, their lines, the players of the lines, the gross per line.
        expect($queries)->toBe(4);
    });

    test('the manual entry gets the day in Montreal and the known courses', function () {
        // A course of its own, so that the expected card is not the one of the demo seeder.
        $card = referenceCardData($this->gab->id, $this->manu->id);
        $card['courseName'] = 'LE GÉANT';
        $card['tees'] = 'Blue';
        $card['holes'][0] = ['par' => 4, 'strokeIndex' => 16, 'distance' => 401];
        $card['holes'][1] = ['par' => 4, 'strokeIndex' => 8, 'distance' => 330];
        storeRoundCard($card);
        $this->travelTo(CarbonImmutable::parse('2026-10-18 03:30:00', 'UTC'));

        $this->get('/rounds/new')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('rounds/create')
                ->where('today', '2026-10-17')
                ->where('players', [
                    ['id' => $this->gab->id, 'name' => 'Gab'],
                    ['id' => $this->manu->id, 'name' => 'Manu'],
                ])
                ->where('courses', [[
                    'courseName' => 'LE GÉANT',
                    'tees' => 'Blue',
                    'pars' => [4, 4, 3, 5, 4, 4, 5, 3, 4, 4, 4, 4, 5, 3, 4, 4, 3, 4],
                    'strokeIndexes' => [16, 8, 18, 2, 4, 10, 6, 14, 12, 9, 11, 7, 3, 17, 1, 5, 13, 15],
                    'distances' => [401, 330, 128, 444, 398, 377, 480, 155, 392, 353, 405, 392, 470, 149, 401, 423, 173, 337],
                ]]));
    });

    test('the players offered to the lines are sorted by name whatever the case', function () {
        $alex = Player::factory()->create(['name' => 'alex']);

        $this->get('/rounds/new')->assertInertia(fn (Assert $page) => $page
            ->where('players', [
                ['id' => $alex->id, 'name' => 'alex'],
                ['id' => $this->gab->id, 'name' => 'Gab'],
                ['id' => $this->manu->id, 'name' => 'Manu'],
            ]));
    });

    test('the page of a round gets its header, gross, card and photo flag', function () {
        $round = storeRoundCard(referenceCardData($this->gab->id, $this->manu->id));

        $response = $this->get("/rounds/{$round->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('rounds/show')
                ->where('round.id', $round->id)
                ->where('round.courseName', 'BETHPAGE BLACK')
                ->where('round.tees', 'Red')
                ->where('round.playedOn', '2026-10-17')
                ->where('round.par', 71)
                ->where('round.hasPhoto', false)
                ->where('round.players', [['name' => 'Gab', 'gross' => 72], ['name' => 'Manu', 'gross' => 88]]));

        $card = $response->inertiaProps('round.card');

        expect(array_column($card['holes'], 'par'))->toBe([4, 4, 3, 5, 4, 4, 5, 3, 4, 4, 4, 4, 5, 3, 4, 4, 3, 4])
            ->and(array_column($card['holes'], 'strokeIndex'))->toBe([8, 16, 18, 2, 4, 10, 6, 14, 12, 9, 11, 7, 3, 17, 1, 5, 13, 15])
            ->and($card['holes'][0]['distance'])->toBe(420)
            ->and($card['lines'])->toBe([
                ['playerId' => $this->gab->id, 'name' => 'Gab', 'strokes' => [5, 5, 3, 5, 4, 4, 4, 3, 5, 4, 4, 4, 4, 3, 4, 5, 3, 3]],
                ['playerId' => $this->manu->id, 'name' => 'Manu', 'strokes' => [7, 5, 3, 7, 6, 5, 5, 3, 5, 3, 6, 5, 7, 4, 5, 4, 3, 5]],
            ]);
    });

    test('a round keeps its own pars when a later round on the same course has others', function () {
        $first = storeRoundCard(referenceCardData($this->gab->id, $this->manu->id), '2026-10-18 10:00:00');
        $second = referenceCardData($this->gab->id, $this->manu->id);
        $second['courseName'] = 'Bethpage Black';
        $second['playedOn'] = '2026-10-24';
        $second['holes'][0]['par'] = 5;
        storeRoundCard($second, '2026-10-25 10:00:00');

        $this->get("/rounds/{$first->id}")->assertInertia(fn (Assert $page) => $page
            ->where('round.card.holes.0.par', 4)
            ->where('round.par', 71));
    });

    test('the page of a round with a photo says so', function () {
        $round = storeRoundCard(referenceCardData($this->gab->id, $this->manu->id));
        $round->update(['photo_path' => 'rounds/img3064.webp']);

        $this->get("/rounds/{$round->id}")->assertInertia(fn (Assert $page) => $page->where('round.hasPhoto', true));
    });

    test('the correction gets the card of the round, the players and the known courses', function () {
        $round = storeRoundCard(referenceCardData($this->gab->id, $this->manu->id));

        $this->get("/rounds/{$round->id}/edit")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('rounds/edit')
                ->where('round', ['id' => $round->id, ...referenceCardData($this->gab->id, $this->manu->id)])
                ->where('players', [
                    ['id' => $this->gab->id, 'name' => 'Gab'],
                    ['id' => $this->manu->id, 'name' => 'Manu'],
                ])
                ->has('courses', 1));
    });

    test('a coherent card is saved and the list confirms it', function () {
        $this->from('/rounds/new')
            ->post('/rounds', referenceCardData($this->gab->id, $this->manu->id))
            ->assertRedirect('/rounds')
            ->assertSessionHasNoErrors()
            ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Round enregistré.']);

        $round = Round::query()->sole();

        expect($round->course_name)->toBe('BETHPAGE BLACK')
            ->and($round->played_on->toDateString())->toBe('2026-10-17')
            ->and(Score::query()->where('player_id', $this->manu->id)->sum('strokes'))->toBe(88);
    });

    test('an incoherent card goes back with the problem count and writes nothing', function () {
        $card = referenceCardData($this->gab->id, $this->manu->id);
        $card['holes'][2]['par'] = 6;

        $this->from('/rounds/new')
            ->post('/rounds', $card)
            ->assertRedirect('/rounds/new')
            ->assertInertiaFlash('toast', ['type' => 'error', 'message' => '1 problème à corriger']);

        expect(Round::query()->count())->toBe(0)
            ->and(RoundHole::query()->count())->toBe(0);
    });

    test('a card of another shape is a validation error', function (Closure $reshape, string $field) {
        $card = $reshape(referenceCardData($this->gab->id, $this->manu->id));

        $this->from('/rounds/new')
            ->post('/rounds', $card)
            ->assertRedirect('/rounds/new')
            ->assertSessionHasErrors($field);

        expect(Round::query()->count())->toBe(0);
    })->with([
        '17 holes' => [fn (array $card): array => [...$card, 'holes' => array_slice($card['holes'], 0, 17)], 'holes'],
        '17 strokes' => [function (array $card): array {
            $card['lines'][0]['strokes'] = array_slice($card['lines'][0]['strokes'], 0, 17);

            return $card;
        }, 'lines.0.strokes'],
        'no line' => [fn (array $card): array => [...$card, 'lines' => []], 'lines'],
        '5 lines' => [fn (array $card): array => [...$card, 'lines' => array_fill(0, 5, $card['lines'][0])], 'lines'],
        'unknown player' => [function (array $card): array {
            $card['lines'][1]['playerId'] = UNKNOWN_ROUND;

            return $card;
        }, 'lines.1.playerId'],
        'par not a number' => [function (array $card): array {
            $card['holes'][0]['par'] = 'quatre';

            return $card;
        }, 'holes.0.par'],
        'date of another format' => [fn (array $card): array => [...$card, 'playedOn' => '17/10/2026'], 'playedOn'],
        'course of 101 characters' => [fn (array $card): array => [...$card, 'courseName' => str_repeat('a', 101)], 'courseName'],
    ]);

    test('a duplicate goes back with the round it repeats and writes nothing', function () {
        $existing = storeRoundCard(referenceCardData($this->gab->id, $this->manu->id));

        $this->from('/rounds/new')
            ->post('/rounds', referenceCardData($this->gab->id, $this->manu->id))
            ->assertRedirect('/rounds/new')
            ->assertInertiaFlash('duplicate', ['roundId' => $existing->id])
            ->assertInertiaFlashMissing('toast');

        expect(Round::query()->count())->toBe(1);
    });

    test('a duplicate saved anyway is written', function () {
        storeRoundCard(referenceCardData($this->gab->id, $this->manu->id));

        $this->from('/rounds/new')
            ->post('/rounds', [...referenceCardData($this->gab->id, $this->manu->id), 'force' => true])
            ->assertRedirect('/rounds')
            ->assertInertiaFlashMissing('duplicate')
            ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Round enregistré.']);

        expect(Round::query()->count())->toBe(2);
    });

    test('a correction opens the round page and is not its own duplicate', function () {
        $round = storeRoundCard(referenceCardData($this->gab->id, $this->manu->id));

        $this->from("/rounds/{$round->id}/edit")
            ->put("/rounds/{$round->id}", referenceCardData($this->gab->id, $this->manu->id))
            ->assertRedirect("/rounds/{$round->id}")
            ->assertInertiaFlashMissing('duplicate')
            ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Round enregistré.']);
    });

    test('a correction changes the gross of the round', function () {
        $round = storeRoundCard(referenceCardData($this->gab->id, $this->manu->id));
        $card = referenceCardData($this->gab->id, $this->manu->id);
        $card['lines'][1]['strokes'][0] = 6;

        $this->from("/rounds/{$round->id}/edit")
            ->put("/rounds/{$round->id}", $card)
            ->assertRedirect("/rounds/{$round->id}");

        expect(Round::query()->sole()->id)->toBe($round->id)
            ->and(Score::query()->where('player_id', $this->manu->id)->sum('strokes'))->toBe(87);
    });

    test('a correction that repeats another round goes back with that round', function () {
        $other = storeRoundCard(referenceCardData($this->gab->id, $this->manu->id));
        $card = referenceCardData($this->gab->id, $this->manu->id);
        $card['playedOn'] = '2026-10-10';
        $round = storeRoundCard($card);

        $this->from("/rounds/{$round->id}/edit")
            ->put("/rounds/{$round->id}", referenceCardData($this->gab->id, $this->manu->id))
            ->assertRedirect("/rounds/{$round->id}/edit")
            ->assertInertiaFlash('duplicate', ['roundId' => $other->id]);

        expect($round->fresh()->played_on->toDateString())->toBe('2026-10-10');
    });

    test('a deleted round leaves the list, which confirms it', function () {
        $round = storeRoundCard(referenceCardData($this->gab->id, $this->manu->id));

        $this->from("/rounds/{$round->id}")
            ->delete("/rounds/{$round->id}")
            ->assertRedirect('/rounds')
            ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Round supprimé.']);

        expect(Round::query()->count())->toBe(0)
            ->and(Player::query()->count())->toBe(2);

        $this->get("/rounds/{$round->id}")->assertNotFound();
    });
});
