<?php

/*
 * Spec players « Index sur la page Joueurs »: « Index établi », « Index provisoire », « Joueur
 * sans round », « Round ajouté avant les autres »; design D5.
 *
 * Mutations that turn these tests red:
 * - the index of the latest round saved instead of the latest played (order by created_at
 *   instead of played_on): Gab's round of 2026-10-01, saved last, gives his index after it,
 *   200, instead of 100;
 * - provisional below 2 rounds instead of 3: Manu, with two rounds, reads provisional false.
 */

use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withPasscodeSession();
    createPlayersOnTest('Gab', 'Manu', 'Sean');
});

/**
 * The index of each player on /players, keyed by name: [indexT, provisional].
 *
 * @return array<string, array{int|null, bool}>
 */
function indexesOnPlayersPage(): array
{
    $response = test()->get('/players')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('players/index'));

    return collect($response->inertiaProps('players'))
        ->mapWithKeys(fn (array $player): array => [$player['name'] => [$player['indexT'], $player['provisional']]])
        ->all();
}

test('three rounds give an index, two a provisional one, none no index', function () {
    createRoundCard(referenceCardOn('2026-10-10', [[$this->gab->id, strokesOverPar(17)], [$this->manu->id, strokesOverPar(17)]]));
    createRoundCard(referenceCardOn('2026-10-17', [[$this->gab->id, strokesOverPar(12)], [$this->manu->id, strokesOverPar(12)]]));
    createRoundCard(referenceCardOn('2026-10-24', [[$this->gab->id, strokesOverPar(20)]]));

    expect(indexesOnPlayersPage())->toBe([
        'Gab' => [100, false],
        'Manu' => [145, true],
        'Sean' => [null, false],
    ]);
});

test('a round saved after the others but played before them keeps the current index', function () {
    createRoundCard(referenceCardOn('2026-10-10', [[$this->gab->id, strokesOverPar(17)]]));
    createRoundCard(referenceCardOn('2026-10-17', [[$this->gab->id, strokesOverPar(12)]]));
    $this->travel(1)->hours();
    createRoundCard(referenceCardOn('2026-10-01', [[$this->gab->id, strokesOverPar(20)]]));

    expect(indexesOnPlayersPage()['Gab'])->toBe([100, false]);
});
