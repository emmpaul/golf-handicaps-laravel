<?php

/*
 * Spec virtual-match, « Suggestion sur la page d'un round »; design D3.
 *
 * Mutations that turn these tests red:
 * - `< 7` instead of `<= 7` days: Gab's round of 2026-11-12 is not suggested;
 * - the latest round kept instead of the closest: Gab reads 2026-11-26 instead of 2026-11-21;
 * - the earliest round kept at an equal gap: Gab reads 2026-11-17 instead of 2026-11-21;
 * - the date of saving left out of the tie-break: of two rounds of Gab on 2026-11-18, the one
 *   saved first, of the greater id, is kept;
 * - the players of the card not left out: Sean of 2026-11-18 is suggested;
 * - the tees compared without lower(trim()), or left out: Gab on « Blue » is suggested, or Gab
 *   on « red » is not;
 * - the suggestions sorted by name only: Sean of 2026-11-17 comes after Gab of 2026-11-18.
 */

use App\Domain\Rounds\Queries\VirtualMatchSuggestion;
use App\Models\Round;
use Illuminate\Support\Facades\DB;

/** A round of these players, in this order, on BETHPAGE BLACK / $tees. */
function suggestionRound(string $playedOn, array $players, string $tees = 'Red', string $courseName = 'BETHPAGE BLACK'): Round
{
    return Round::factory()
        ->withPlayers(...$players)
        ->create(['course_name' => $courseName, 'tees' => $tees, 'played_on' => $playedOn]);
}

/**
 * Each suggestion reduced to [name, played on].
 *
 * @return list<array{string, string}>
 */
function suggested(Round $round): array
{
    return array_map(
        fn (array $line): array => [$line['name'], $line['playedOn']],
        app(VirtualMatchSuggestion::class)->for($round),
    );
}

beforeEach(function () {
    createPlayersOnTest('Gab', 'Manu', 'Sean');
});

test('a friend alone the day before is suggested with his round', function () {
    $gabRound = suggestionRound('2026-11-18', [$this->gab]);
    $round = suggestionRound('2026-11-19', [$this->manu, $this->sean]);

    expect(app(VirtualMatchSuggestion::class)->for($round))->toBe([
        ['roundId' => $gabRound->id, 'playerId' => $this->gab->id, 'name' => 'Gab', 'colorSlot' => 0, 'playedOn' => '2026-11-18'],
    ]);
});

test('several players are sorted by date of play', function () {
    $round = suggestionRound('2026-11-19', [$this->manu]);
    suggestionRound('2026-11-20', [$this->sean]);
    suggestionRound('2026-11-18', [$this->gab]);

    expect(suggested($round))->toBe([['Gab', '2026-11-18'], ['Sean', '2026-11-20']]);
});

test('the players of the same date are sorted by name', function () {
    $round = suggestionRound('2026-11-19', [$this->manu]);
    suggestionRound('2026-11-18', [$this->sean, $this->gab]);

    expect(suggested($round))->toBe([['Gab', '2026-11-18'], ['Sean', '2026-11-18']]);
});

test('the date of play comes before the name', function () {
    $round = suggestionRound('2026-11-19', [$this->manu]);
    suggestionRound('2026-11-18', [$this->gab]);
    suggestionRound('2026-11-17', [$this->sean]);

    expect(suggested($round))->toBe([['Sean', '2026-11-17'], ['Gab', '2026-11-18']]);
});

test('the closest round of a player is kept', function () {
    $round = suggestionRound('2026-11-19', [$this->manu]);
    suggestionRound('2026-11-14', [$this->gab]);
    suggestionRound('2026-11-21', [$this->gab]);
    suggestionRound('2026-11-26', [$this->gab]);

    expect(suggested($round))->toBe([['Gab', '2026-11-21']]);
});

test('at an equal gap the latest round is kept', function () {
    $round = suggestionRound('2026-11-19', [$this->manu]);
    suggestionRound('2026-11-17', [$this->gab]);
    suggestionRound('2026-11-21', [$this->gab]);

    expect(suggested($round))->toBe([['Gab', '2026-11-21']]);
});

test('at an equal gap and on the same date the round saved last is kept', function () {
    $round = suggestionRound('2026-11-19', [$this->manu]);
    // The round saved last holds the smaller id, so that the id alone would keep the other one.
    $savedLast = Round::factory()->withPlayers($this->gab)->create([
        'id' => '00000000-0000-4000-8000-000000000001',
        'course_name' => 'BETHPAGE BLACK',
        'tees' => 'Red',
        'played_on' => '2026-11-18',
        'created_at' => '2026-11-18 21:00:00',
    ]);
    Round::factory()->withPlayers($this->gab)->create([
        'id' => 'ffffffff-ffff-4fff-bfff-ffffffffffff',
        'course_name' => 'BETHPAGE BLACK',
        'tees' => 'Red',
        'played_on' => '2026-11-18',
        'created_at' => '2026-11-18 09:00:00',
    ]);

    expect(array_column(app(VirtualMatchSuggestion::class)->for($round), 'roundId'))->toBe([$savedLast->id]);
});

test('a round 8 days away is out of the window', function () {
    $round = suggestionRound('2026-11-19', [$this->manu]);
    suggestionRound('2026-11-11', [$this->gab]);
    suggestionRound('2026-11-27', [$this->sean]);

    expect(suggested($round))->toBe([]);
});

test('a round 7 days away is in the window', function () {
    $round = suggestionRound('2026-11-19', [$this->manu]);
    suggestionRound('2026-11-12', [$this->gab]);
    suggestionRound('2026-11-26', [$this->sean]);

    expect(suggested($round))->toBe([['Gab', '2026-11-12'], ['Sean', '2026-11-26']]);
});

test('a player already on the card is not suggested', function () {
    $round = suggestionRound('2026-11-19', [$this->manu, $this->sean]);
    suggestionRound('2026-11-18', [$this->sean]);

    expect(suggested($round))->toBe([]);
});

test('a round on other tees or another course is not suggested', function () {
    $round = suggestionRound('2026-11-19', [$this->manu]);
    suggestionRound('2026-11-18', [$this->gab], tees: 'Blue');
    suggestionRound('2026-11-18', [$this->sean], courseName: 'PAYNES VALLEY');

    expect(suggested($round))->toBe([]);
});

test('course and tees are compared without case nor surrounding spaces', function () {
    $round = suggestionRound('2026-11-19', [$this->manu]);
    suggestionRound('2026-11-18', [$this->gab], tees: ' red ', courseName: 'Bethpage Black');

    expect(suggested($round))->toBe([['Gab', '2026-11-18']]);
});

test('the suggestion is one query', function () {
    $round = suggestionRound('2026-11-19', [$this->manu]);
    suggestionRound('2026-11-18', [$this->gab]);
    suggestionRound('2026-11-20', [$this->sean]);

    DB::enableQueryLog();
    app(VirtualMatchSuggestion::class)->for($round);

    expect(DB::getQueryLog())->toHaveCount(1);
});
