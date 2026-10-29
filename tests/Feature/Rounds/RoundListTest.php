<?php

/*
 * Spec home-dashboard, « Dernier round »: « Dernier round affiché », « Même jour », « Gagnants
 * ex æquo »; design D2 and D8.
 *
 * Mutations that turn these tests red:
 * - the limit ignored by RoundList::get(): get(1) gives two rounds instead of one;
 * - RoundList sorted without created_at (played_on then id): of the two rounds of 2026-10-17,
 *   BETHPAGE BLACK, with the greater id but the earlier date of saving, comes back instead of
 *   PAYNES VALLEY.
 */

use App\Domain\Rounds\Queries\RoundList;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    createPlayersOnTest('Gab', 'Manu');
});

test('the latest round of a day is the one saved last', function () {
    $paynes = referenceCardData($this->gab->id, $this->manu->id);
    $paynes['courseName'] = 'PAYNES VALLEY';
    $paynes['tees'] = 'Blue';
    // PAYNES VALLEY first, so with the smaller id, then moved after BETHPAGE BLACK by its date
    // of saving.
    $this->travelTo(CarbonImmutable::parse('2026-10-18 10:00:00', 'UTC'));
    $saved = createRoundCard($paynes);
    $this->travelTo(CarbonImmutable::parse('2026-10-18 11:00:00', 'UTC'));
    createRoundCard(referenceCardData($this->gab->id, $this->manu->id));
    $saved->forceFill(['created_at' => '2026-10-18 12:00:00'])->save();

    $rounds = app(RoundList::class)->get(1);

    expect($rounds)->toHaveCount(1)
        ->and($rounds[0]['courseName'])->toBe('PAYNES VALLEY');
});

test('the latest round names its gross and its net winners as the list of rounds', function () {
    createRoundCard(referenceCardOn('2026-10-10', [
        [$this->gab->id, strokesOverPar(1)],
        [$this->manu->id, strokesOverPar(17)],
    ]));
    $reference = createRoundCard(referenceCardData($this->gab->id, $this->manu->id));

    $latest = app(RoundList::class)->get(1);

    expect($latest)->toBe([[
        'id' => $reference->id,
        'playedOn' => '2026-10-17',
        'courseName' => 'BETHPAGE BLACK',
        'tees' => 'Red',
        'players' => [['name' => 'Gab', 'gross' => 72], ['name' => 'Manu', 'gross' => 88]],
        'netWinners' => ['Gab', 'Manu'],
    ]])->and($latest[0])->toBe(app(RoundList::class)->get()[0]);
});

test('the latest round makes the same number of queries whatever the number of rounds', function (int $count) {
    foreach (range(1, $count) as $day) {
        $card = referenceCardData($this->gab->id, $this->manu->id);
        $card['playedOn'] = CarbonImmutable::parse('2026-09-01')->addDays($day)->toDateString();
        createRoundCard($card);
    }

    $queries = 0;
    DB::listen(function () use (&$queries) {
        $queries++;
    });

    expect(app(RoundList::class)->get(1))->toHaveCount(1);

    // The round, its lines, the players of the lines, the gross of its lines, the statistics
    // of its lines with its rating.
    expect($queries)->toBe(5);
})->with([10, 30]);
