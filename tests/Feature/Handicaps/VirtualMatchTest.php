<?php

/*
 * Spec virtual-match, « Joueurs-rounds d'un parcours », « Un seul round par joueur », « Choix
 * gardé dans l'adresse », « Net d'un joueur-round », « Classement de la partie virtuelle »,
 * « Joueur sans index dans la partie virtuelle », « Pars et index de la carte combinée »; design
 * D2, D3.
 *
 * Mutations that turn these tests red:
 * - the index of the latest round of the player (index_after_t) instead of index_before_t: after
 *   a new round that brings Gab to 5,0, Gab reads « 74 − 7 » and 67 net instead of « 74 − 2 » and
 *   72;
 * - the ties broken by name or by date instead of the order of the picks: Manu picked before Gab
 *   comes after him;
 * - the lines without index ranked in the order of the picks: Sean, picked first, comes first;
 * - the holes of the oldest round picked instead of the latest: the hole 1 reads par 4;
 * - the par of the latest round picked for every line: Gab on the par 71 reads « 74 − 1 »
 *   instead of « 74 − 2 »;
 * - no rating read as 0 instead of par × 10: Manu without a rating reads a negative handicap
 *   instead of 17;
 * - the tees compared without lower(trim()): the round on « Bethpage Black / red » is left out;
 * - the scores read per pick (one query per player-round): 7 queries instead of 3.
 */

use App\Domain\Handicaps\Pick;
use App\Domain\Handicaps\Queries\VirtualMatch;
use App\Models\CourseRating;
use App\Models\Player;
use App\Models\PlayerRoundStat;
use App\Models\Round;
use App\Models\RoundHole;
use App\Models\RoundPlayer;
use App\Models\Score;
use Carbon\CarbonImmutable;
use Database\Factories\RoundFactory;

/** Gab's 74 on the par 71 of the reference card: 3 over par on the first three holes. */
const VIRTUAL_GAB_74 = [5, 5, 4, 5, 4, 4, 5, 3, 4, 4, 4, 4, 5, 3, 4, 4, 3, 4];

/** Manu's 88: 17 over par on the first seventeen holes. */
const VIRTUAL_MANU_88 = [5, 5, 4, 6, 5, 5, 6, 4, 5, 5, 5, 5, 6, 4, 5, 5, 4, 4];

/** Sean's 84: 13 over par on the first thirteen holes. */
const VIRTUAL_SEAN_84 = [5, 5, 4, 6, 5, 5, 6, 4, 5, 5, 5, 5, 6, 3, 4, 4, 3, 4];

/** Manu « 88 − 23 », 65 net, before Gab « 74 − 2 », 72 net (scenario « Trois joueurs, deux jours »). */
const MANU_65_GAB_72 = [['Manu', 88, 23, 65, 1], ['Gab', 74, 2, 72, 2]];

/**
 * A round built by the factories on the holes of the reference card, its first hole a par
 * $par1, saved at $savedAt: one line per player in this order, with its strokes and its index
 * just before the round in player_round_stats, the gross being the sum of the strokes.
 *
 * @param  list<array{Player, list<int>, int|null}>  $lines  [player, strokes, index before]
 */
function virtualMatchRound(string $courseName, string $tees, string $playedOn, array $lines, int $par1 = 4, string $savedAt = '2026-11-19 20:00:00'): Round
{
    test()->travelTo(CarbonImmutable::parse($savedAt, 'UTC'));
    $round = Round::factory()->create(['course_name' => $courseName, 'tees' => $tees, 'played_on' => $playedOn]);

    $pars = [$par1, ...array_slice(RoundFactory::PARS, 1)];
    foreach ($pars as $i => $par) {
        RoundHole::factory()->for($round)->create([
            'hole' => $i + 1,
            'par' => $par,
            'stroke_index' => RoundFactory::STROKE_INDEXES[$i],
        ]);
    }

    foreach ($lines as $position => [$player, $strokes, $indexBeforeT]) {
        RoundPlayer::factory()->for($round)->for($player)->create(['position' => $position]);
        foreach ($strokes as $i => $hole) {
            Score::factory()->for($round)->for($player)->create(['hole' => $i + 1, 'strokes' => $hole]);
        }
        PlayerRoundStat::factory()->for($round)->for($player)->create([
            'gross' => array_sum($strokes),
            'index_before_t' => $indexBeforeT,
        ]);
    }
    test()->travelBack();

    return $round;
}

/**
 * The scenario « Trois joueurs, deux jours »: Gab alone on 2026-11-18 with 74 from 1,0, Manu and
 * Sean on 2026-11-19 with 88 from 17,4 and 84 from 12,5, on BETHPAGE BLACK / Red at 71,4 / 144.
 *
 * @return array{Round, Round} the round of Gab, then the round of Manu and Sean
 */
function threePlayersTwoDays(): array
{
    CourseRating::factory()->bethpageRed()->create();

    return [
        virtualMatchRound('BETHPAGE BLACK', 'Red', '2026-11-18', [[test()->gab, VIRTUAL_GAB_74, 10]], savedAt: '2026-11-18 20:00:00'),
        virtualMatchRound('BETHPAGE BLACK', 'Red', '2026-11-19', [[test()->manu, VIRTUAL_MANU_88, 174], [test()->sean, VIRTUAL_SEAN_84, 125]]),
    ];
}

/**
 * Each line of the leaderboard reduced to [name, gross, playing handicap, net, rank].
 *
 * @param  array<string, mixed>  $match
 * @return list<array{string, int, int|null, int|null, int|null}>
 */
function virtualLeaderboard(array $match): array
{
    return array_map(
        fn (array $line): array => [$line['name'], $line['gross'], $line['playingHandicap'], $line['net'], $line['rank']],
        $match['leaderboard'],
    );
}

/**
 * The virtual match of BETHPAGE BLACK / Red for these picks, in this order.
 *
 * @param  list<array{Round, Player}>  $picks  [round, player]
 * @return array<string, mixed>
 */
function bethpageRedMatch(array $picks): array
{
    return app(VirtualMatch::class)->for('BETHPAGE BLACK', 'Red', array_map(
        fn (array $pick): Pick => new Pick($pick[0]->id, $pick[1]->id),
        $picks,
    ));
}

beforeEach(function () {
    createPlayersOnTest('Gab', 'Manu', 'Sean');
});

test('three players of two days are ranked on the index just before their own round', function () {
    [$gabRound, $manuRound] = threePlayersTwoDays();

    $match = bethpageRedMatch([
        [$gabRound, $this->gab],
        [$manuRound, $this->manu],
        [$manuRound, $this->sean],
    ]);

    expect($match['courseName'])->toBe('BETHPAGE BLACK')
        ->and($match['tees'])->toBe('Red')
        ->and($match['rating'])->toBe(['rated' => true, 'ratingT' => 714, 'slope' => 144])
        ->and($match['leaderboard'])->toBe([
            ['roundId' => $manuRound->id, 'playerId' => $this->manu->id, 'name' => 'Manu', 'colorSlot' => 1, 'playedOn' => '2026-11-19', 'gross' => 88, 'indexBeforeT' => 174, 'playingHandicap' => 23, 'net' => 65, 'rank' => 1],
            ['roundId' => $manuRound->id, 'playerId' => $this->sean->id, 'name' => 'Sean', 'colorSlot' => 2, 'playedOn' => '2026-11-19', 'gross' => 84, 'indexBeforeT' => 125, 'playingHandicap' => 16, 'net' => 68, 'rank' => 2],
            ['roundId' => $gabRound->id, 'playerId' => $this->gab->id, 'name' => 'Gab', 'colorSlot' => 0, 'playedOn' => '2026-11-18', 'gross' => 74, 'indexBeforeT' => 10, 'playingHandicap' => 2, 'net' => 72, 'rank' => 3],
        ])
        ->and($match['picks'])->toBe([
            ['roundId' => $gabRound->id, 'playerId' => $this->gab->id, 'name' => 'Gab', 'colorSlot' => 0, 'playedOn' => '2026-11-18', 'strokes' => [5, 5, 4, 5, 4, 4, 5, 3, 4, 4, 4, 4, 5, 3, 4, 4, 3, 4]],
            ['roundId' => $manuRound->id, 'playerId' => $this->manu->id, 'name' => 'Manu', 'colorSlot' => 1, 'playedOn' => '2026-11-19', 'strokes' => [5, 5, 4, 6, 5, 5, 6, 4, 5, 5, 5, 5, 6, 4, 5, 5, 4, 4]],
            ['roundId' => $manuRound->id, 'playerId' => $this->sean->id, 'name' => 'Sean', 'colorSlot' => 2, 'playedOn' => '2026-11-19', 'strokes' => [5, 5, 4, 6, 5, 5, 6, 4, 5, 5, 5, 5, 6, 3, 4, 4, 3, 4]],
        ]);
});

test('the add list holds every player round of the course, the latest round first, with its partners', function () {
    [$gabRound, $manuRound] = threePlayersTwoDays();

    expect(app(VirtualMatch::class)->for('BETHPAGE BLACK', 'Red', [])['candidates'])->toBe([
        ['roundId' => $manuRound->id, 'playerId' => $this->manu->id, 'name' => 'Manu', 'colorSlot' => 1, 'playedOn' => '2026-11-19', 'partners' => ['Sean'], 'gross' => 88],
        ['roundId' => $manuRound->id, 'playerId' => $this->sean->id, 'name' => 'Sean', 'colorSlot' => 2, 'playedOn' => '2026-11-19', 'partners' => ['Manu'], 'gross' => 84],
        ['roundId' => $gabRound->id, 'playerId' => $this->gab->id, 'name' => 'Gab', 'colorSlot' => 0, 'playedOn' => '2026-11-18', 'partners' => [], 'gross' => 74],
    ]);
});

test('two rounds of the same day are listed by date of saving, the latest first', function () {
    $earlier = virtualMatchRound('BETHPAGE BLACK', 'Red', '2026-11-19', [[$this->gab, VIRTUAL_GAB_74, 10]], savedAt: '2026-11-19 10:00:00');
    $later = virtualMatchRound('BETHPAGE BLACK', 'Red', '2026-11-19', [[$this->manu, VIRTUAL_MANU_88, 174]], savedAt: '2026-11-19 18:00:00');

    $candidates = app(VirtualMatch::class)->for('BETHPAGE BLACK', 'Red', [])['candidates'];

    expect(array_column($candidates, 'roundId'))->toBe([$later->id, $earlier->id]);
});

test('a new round of Gab since leaves his net of the virtual match unchanged', function () {
    [$gabRound, $manuRound] = threePlayersTwoDays();
    // The index of Gab goes to 5,0 after this round.
    $since = virtualMatchRound('PAYNES VALLEY', 'Blue', '2026-11-25', [[$this->gab, strokesOverPar(10), 10]]);
    PlayerRoundStat::query()->where('round_id', $since->id)->update(['index_after_t' => 50]);

    $match = bethpageRedMatch([
        [$gabRound, $this->gab],
        [$manuRound, $this->manu],
    ]);

    expect(virtualLeaderboard($match))->toBe(MANU_65_GAB_72);
});

test('each line plays on the par of its own round, and without a rating on par × 10 and 113', function (bool $rated, array $expected) {
    if ($rated) {
        CourseRating::factory()->bethpageRed()->create();
    }
    // Gab on the par 71 of the reference card, Manu on a par 72 with a par 5 on the hole 1.
    $gabRound = virtualMatchRound('BETHPAGE BLACK', 'Red', '2026-11-18', [[$this->gab, VIRTUAL_GAB_74, 10]]);
    $manuRound = virtualMatchRound('BETHPAGE BLACK', 'Red', '2026-11-19', [[$this->manu, VIRTUAL_MANU_88, 174]], par1: 5);

    $match = bethpageRedMatch([
        [$gabRound, $this->gab],
        [$manuRound, $this->manu],
    ]);

    expect(virtualLeaderboard($match))->toBe($expected);
})->with([
    // Manu 17,4 × 144 / 113 + 71,4 − 72 = 21,57: 22; Gab 1,0 × 144 / 113 + 71,4 − 71 = 1,67: 2.
    'rated 71,4 / 144' => [true, [['Manu', 88, 22, 66, 1], ['Gab', 74, 2, 72, 2]]],
    // Manu 17,4 + 72 − 72: 17; Gab 1,0 + 71 − 71: 1.
    'without a rating' => [false, [['Manu', 88, 17, 71, 1], ['Gab', 74, 1, 73, 2]]],
]);

test('tied nets of different days share the rank in the order of the picks', function (string $first, array $expected) {
    CourseRating::factory()->bethpageRed()->create();
    // Gab 72 − 2 and Manu 93 − 23: 70 net each.
    $gabRound = virtualMatchRound('BETHPAGE BLACK', 'Red', '2026-11-18', [[$this->gab, strokesOverPar(1), 10]]);
    $manuRound = virtualMatchRound('BETHPAGE BLACK', 'Red', '2026-11-19', [[$this->manu, strokesOverPar(22), 174]]);
    $picks = ['Gab' => new Pick($gabRound->id, $this->gab->id), 'Manu' => new Pick($manuRound->id, $this->manu->id)];
    $ordered = $first === 'Gab' ? [$picks['Gab'], $picks['Manu']] : [$picks['Manu'], $picks['Gab']];

    expect(virtualLeaderboard(app(VirtualMatch::class)->for('BETHPAGE BLACK', 'Red', $ordered)))->toBe($expected);
})->with([
    'Gab then Manu' => ['Gab', [['Gab', 72, 2, 70, 1], ['Manu', 93, 23, 70, 1]]],
    'Manu then Gab' => ['Manu', [['Manu', 93, 23, 70, 1], ['Gab', 72, 2, 70, 1]]],
]);

test('a player round without index comes after the ranked ones, without net nor rank', function () {
    [$gabRound, $manuRound] = threePlayersTwoDays();
    $seanFirst = virtualMatchRound('BETHPAGE BLACK', 'Red', '2026-11-17', [[$this->sean, strokesOverPar(24), null]]);

    $match = bethpageRedMatch([
        [$seanFirst, $this->sean],
        [$gabRound, $this->gab],
        [$manuRound, $this->manu],
    ]);

    expect(virtualLeaderboard($match))->toBe([
        ['Manu', 88, 23, 65, 1],
        ['Gab', 74, 2, 72, 2],
        ['Sean', 95, null, null, null],
    ])->and($match['leaderboard'][2]['indexBeforeT'])->toBeNull();
});

test('a pick of a deleted round, of other tees or of a player absent from the round is ignored', function () {
    [$gabRound, $manuRound] = threePlayersTwoDays();
    $blue = virtualMatchRound('BETHPAGE BLACK', 'Blue', '2026-11-18', [[$this->gab, VIRTUAL_GAB_74, 10]]);
    $deleted = virtualMatchRound('BETHPAGE BLACK', 'Red', '2026-11-10', [[$this->gab, VIRTUAL_GAB_74, 10]]);
    $deletedId = $deleted->id;
    $deleted->delete();

    $match = app(VirtualMatch::class)->for('BETHPAGE BLACK', 'Red', [
        new Pick($deletedId, $this->gab->id),
        new Pick($blue->id, $this->gab->id),
        new Pick($manuRound->id, $this->gab->id),
        new Pick($manuRound->id, $this->manu->id),
        new Pick($manuRound->id, $this->sean->id),
    ]);

    expect(virtualLeaderboard($match))->toBe([
        ['Manu', 88, 23, 65, 1],
        ['Sean', 84, 16, 68, 2],
    ])->and(array_column($match['picks'], 'name'))->toBe(['Manu', 'Sean'])
        ->and(array_column($match['candidates'], 'roundId'))->toBe([$manuRound->id, $manuRound->id, $gabRound->id]);
});

test('a second pick of the same player is ignored', function () {
    [$gabRound, $manuRound] = threePlayersTwoDays();
    $gabEarlier = virtualMatchRound('BETHPAGE BLACK', 'Red', '2026-11-02', [[$this->gab, strokesOverPar(10), 10]]);

    $match = bethpageRedMatch([
        [$gabRound, $this->gab],
        [$manuRound, $this->manu],
        [$gabEarlier, $this->gab],
    ]);

    expect(virtualLeaderboard($match))->toBe(MANU_65_GAB_72)->and(array_column($match['picks'], 'playedOn'))->toBe(['2026-11-18', '2026-11-19']);
});

test('the course and tees are matched without case nor surrounding spaces', function () {
    CourseRating::factory()->bethpageRed()->create();
    $gabRound = virtualMatchRound('BETHPAGE BLACK', 'Red', '2026-11-18', [[$this->gab, VIRTUAL_GAB_74, 10]]);
    $manuRound = virtualMatchRound(' Bethpage Black ', 'red ', '2026-11-19', [[$this->manu, VIRTUAL_MANU_88, 174]]);

    $match = bethpageRedMatch([
        [$gabRound, $this->gab],
        [$manuRound, $this->manu],
    ]);

    expect(virtualLeaderboard($match))->toBe(MANU_65_GAB_72)->and(array_column($match['candidates'], 'name'))->toBe(['Manu', 'Gab']);
});

test('the holes of the card come from the latest round picked and say when they differ', function (int $manuPar1, array $expectedPars, bool $expectedDiffer) {
    $gabRound = virtualMatchRound('BETHPAGE BLACK', 'Red', '2026-11-18', [[$this->gab, VIRTUAL_GAB_74, 10]]);
    $manuRound = virtualMatchRound('BETHPAGE BLACK', 'Red', '2026-11-19', [[$this->manu, VIRTUAL_MANU_88, 174]], par1: $manuPar1);

    $match = bethpageRedMatch([
        [$gabRound, $this->gab],
        [$manuRound, $this->manu],
    ]);

    expect(array_column($match['holes'], 'par'))->toBe($expectedPars)
        ->and(array_column($match['holes'], 'strokeIndex'))->toBe([8, 16, 18, 2, 4, 10, 6, 14, 12, 9, 11, 7, 3, 17, 1, 5, 13, 15])
        ->and($match['holesDiffer'])->toBe($expectedDiffer);
})->with([
    'a par 5 on the hole 1 of the latest round' => [5, [5, 4, 3, 5, 4, 4, 5, 3, 4, 4, 4, 4, 5, 3, 4, 4, 3, 4], true],
    'identical cards' => [4, [4, 4, 3, 5, 4, 4, 5, 3, 4, 4, 4, 4, 5, 3, 4, 4, 3, 4], false],
]);

test('five player rounds are all ranked in three queries, without one query per pick', function () {
    CourseRating::factory()->bethpageRed()->create();
    createPlayersOnTest('Alex', 'Max');
    $gabRound = virtualMatchRound('BETHPAGE BLACK', 'Red', '2026-11-18', [[$this->gab, VIRTUAL_GAB_74, 10]]);
    $manuRound = virtualMatchRound('BETHPAGE BLACK', 'Red', '2026-11-19', [[$this->manu, VIRTUAL_MANU_88, 174], [$this->sean, VIRTUAL_SEAN_84, 125]]);
    $alexRound = virtualMatchRound('BETHPAGE BLACK', 'Red', '2026-11-12', [[$this->alex, strokesOverPar(10), 100]]);
    $maxRound = virtualMatchRound('BETHPAGE BLACK', 'Red', '2026-11-05', [[$this->max, strokesOverPar(30), null]]);

    DB::flushQueryLog();
    DB::enableQueryLog();
    $match = bethpageRedMatch([
        [$gabRound, $this->gab],
        [$manuRound, $this->manu],
        [$manuRound, $this->sean],
        [$alexRound, $this->alex],
        [$maxRound, $this->max],
    ]);
    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();

    // Alex at 10,0: 13 on 71,4 / 144, 81 − 13.
    expect($queries)->toBe(3)
        ->and(virtualLeaderboard($match))->toBe([
            ['Manu', 88, 23, 65, 1],
            ['Sean', 84, 16, 68, 2],
            ['Alex', 81, 13, 68, 2],
            ['Gab', 74, 2, 72, 4],
            ['Max', 101, null, null, null],
        ]);
});
