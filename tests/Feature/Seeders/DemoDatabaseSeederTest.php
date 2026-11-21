<?php

/*
 * Mutations that turn these tests red:
 * - fake()->seed(2026) removed from DemoRoundsSeeder: the generated scores change from one run
 *   to the next and the signatures of the first five rounds differ from the literal list;
 * - the guard of DemoDatabaseSeeder against a filled base removed: on a base with Gab the
 *   seeder does not fail with its message (it writes rounds or fails elsewhere);
 * - the reference photo not copied, or copied under another path: the photo is missing from
 *   the photos disk;
 * - a generated card with a stroke index in double: RoundCardChecks refuses it and the seeding
 *   fails;
 * - the photos directory not emptied first: the stale file stays on the disk;
 * - DemoConfirmedRatingSeeder through SaveCourseRating: the rating has the source manual;
 * - DemoCourseRatingsSeeder called after DemoRoundsSeeder: each new course of the rounds already
 *   has its pending row and its job, and the seeder fails on the unique index of course_ratings;
 *   CALA NOVA / White left out of DemoCourseRatingsSeeder: its rounds queue a lookup and
 *   assertNothingPushed fails;
 * - DemoConfirmedRatingSeeder writing the confirmed rating by an update, without
 *   ConfirmCourseRatingProposal: the history is not recomputed and Gab and Manu stay at 10 and
 *   170 on the reference round.
 * - the capped hole of DemoRoundsSeeder left out, or written on another round or hole: Manu has
 *   no capped hole, or not hole 2 of PAYNES VALLEY on 2026-09-10 at 8 for a par 3;
 * - the capped hole written by changing the strokes drawn for Manu (another value of OVER_PAR)
 *   instead of overriding one hole after the draw: the signatures of the first five rounds move;
 * - the round of Gab on PAYNES VALLEY / Blue written on 2026-10-17 instead of 2026-10-24: it is
 *   more than 7 days before the round of Manu and Sean, and the suggestion is empty;
 * - one hole of a virtual match round changed: its gross differs from the literal signatures.
 */

use App\Domain\Courses\Queries\CourseOverview;
use App\Domain\Handicaps\Pick;
use App\Domain\Handicaps\Queries\PlayerIndexComputation;
use App\Domain\Handicaps\Queries\PlayerStandings;
use App\Domain\Handicaps\Queries\VirtualMatch;
use App\Domain\Imports\Queries\PendingImports;
use App\Domain\Rounds\Queries\RoundList;
use App\Domain\Rounds\Queries\VirtualMatchSuggestion;
use App\Domain\Rounds\RoundCard;
use App\Domain\Rounds\RoundCardChecks;
use App\Models\CourseRating;
use App\Models\Player;
use App\Models\PlayerRoundStat;
use App\Models\Round;
use App\Models\RoundPlayer;
use Database\Seeders\DemoDatabaseSeeder;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

/**
 * The first rounds of the Rounds page, as « date course / tees : player gross, … ».
 *
 * @return list<string>
 */
function demoRoundSignatures(int $limit): array
{
    return Round::query()
        ->with(['lines.player', 'scores'])
        ->orderByDesc('played_on')
        ->orderByDesc('created_at')
        ->limit($limit)
        ->get()
        ->map(fn (Round $round): string => sprintf(
            '%s %s / %s : %s',
            $round->played_on->toDateString(),
            $round->course_name,
            $round->tees,
            $round->lines->map(fn (RoundPlayer $line): string => $line->player->name.' '
                .$round->scores->where('player_id', $line->player_id)->sum('strokes'))->implode(', '),
        ))
        ->values()
        ->all();
}

/**
 * Every round with its whole card and the names of its players, to compare two runs.
 *
 * @return list<array<string, mixed>>
 */
function demoRoundCards(): array
{
    $names = Player::query()->pluck('name', 'id');

    return Round::query()->orderBy('played_on')->get()
        ->map(function (Round $round) use ($names): array {
            $card = RoundCard::fromRound($round)->toArray();
            $card['lines'] = array_map(fn (array $line): array => [
                'player' => $names[$line['playerId']],
                'strokes' => $line['strokes'],
            ], $card['lines']);

            return $card;
        })
        ->values()
        ->all();
}

const DEMO_FIRST_FIVE = [
    '2026-10-25 PAYNES VALLEY / Blue : Manu 90, Sean 84',
    '2026-10-24 PAYNES VALLEY / Blue : Gab 76',
    '2026-10-17 BETHPAGE BLACK / Red : Gab 72, Manu 88',
    '2026-10-15 CALA NOVA / White : Manu 95, Gab 75, Sean 89',
    '2026-10-06 PAYNES VALLEY / Blue : Manu 99, Gab 71',
];

beforeEach(function () {
    Storage::fake('photos');
});

test('the demonstration base has the development players, the reference round with its photo and generated rounds', function () {
    Storage::disk('photos')->put('rounds/stale.webp', 'old');

    $this->seed(DemoDatabaseSeeder::class);

    expect(playersWithAliases())->toBe(['Gab' => ['GAB'], 'Manu' => ['MANU'], 'Sean' => ['SEAN']])
        ->and(Round::query()->count())->toBe(27)
        ->and(Round::query()->selectRaw("course_name || ' / ' || tees as course")->distinct()->orderBy('course')->pluck('course')->all())
        ->toBe(['BETHPAGE BLACK / Red', 'CALA NOVA / White', 'PAYNES VALLEY / Blue'])
        ->and(Round::query()->min('played_on'))->toBe('2026-04-01')
        ->and(Round::query()->where('played_on', '<', '2026-10-17')->max('played_on'))->toBe('2026-10-15')
        ->and(Round::query()->max('played_on'))->toBe('2026-10-25');

    $reference = Round::query()->whereDate('played_on', '2026-10-17')->sole();
    expect($reference->photo_path)->toBe("rounds/{$reference->id}.webp")
        ->and(Storage::disk('photos')->get("rounds/{$reference->id}.webp"))
        ->toBe(file_get_contents(base_path('database/data/img3064.webp')))
        ->and(Round::query()->whereNotNull('photo_path')->count())->toBe(1)
        ->and(Storage::disk('photos')->exists('rounds/stale.webp'))->toBeFalse();

    foreach (Round::query()->get() as $round) {
        expect(RoundCardChecks::problems(RoundCard::fromRound($round)))->toBe([], "round of {$round->played_on->toDateString()}");
    }
});

// development-seed-data, « Partie virtuelle de démonstration »: Gab alone on PAYNES VALLEY / Blue
// on 2026-10-24, Manu and Sean the next day, no other round of these tees within 7 days.
test('the demonstration has a virtual match on PAYNES VALLEY / Blue suggested on the round of Manu and Sean', function () {
    $this->seed(DemoDatabaseSeeder::class);

    $paynesValley = fn (string $playedOn): Round => Round::query()
        ->where('course_name', 'PAYNES VALLEY')->where('tees', 'Blue')->whereDate('played_on', $playedOn)->sole();
    $solo = $paynesValley('2026-10-24');
    $pair = $paynesValley('2026-10-25');

    expect(Round::query()->where('course_name', 'PAYNES VALLEY')->where('tees', 'Blue')
        ->whereBetween('played_on', ['2026-10-17', '2026-11-01'])->count())->toBe(2)
        ->and($solo->scores()->where('player_id', Player::query()->where('name', 'Gab')->sole()->id)->orderBy('hole')->pluck('strokes')->all())
        ->toBe([4, 3, 5, 5, 3, 4, 5, 5, 4, 3, 4, 5, 5, 4, 4, 3, 5, 5]);

    $suggestions = app(VirtualMatchSuggestion::class)->for($pair);
    expect(array_map(fn (array $suggestion): array => [$suggestion['name'], $suggestion['roundId'], $suggestion['playedOn']], $suggestions))
        ->toBe([['Gab', $solo->id, '2026-10-24']]);

    $picks = [
        ...$pair->lines()->orderBy('position')->get()->map(fn (RoundPlayer $line): Pick => new Pick($pair->id, $line->player_id))->all(),
        new Pick($solo->id, $suggestions[0]['playerId']),
    ];
    $leaderboard = app(VirtualMatch::class)->for('PAYNES VALLEY', 'Blue', $picks)['leaderboard'];

    expect(collect($leaderboard)->pluck('name')->sort()->values()->all())->toBe(['Gab', 'Manu', 'Sean'])
        ->and(collect($leaderboard)->pluck('gross', 'name')->sortKeys()->all())->toBe(['Gab' => 76, 'Manu' => 90, 'Sean' => 84])
        ->and(collect($leaderboard)->pluck('rank')->filter(fn (?int $rank): bool => $rank !== null)->count())->toBe(3);
});

test('two runs on an emptied base write the same rounds', function () {
    $this->seed(DemoDatabaseSeeder::class);
    $firstSignatures = demoRoundSignatures(5);
    $firstCards = demoRoundCards();

    Round::query()->delete();
    Player::query()->delete();
    CourseRating::query()->delete();

    $this->seed(DemoDatabaseSeeder::class);

    expect($firstSignatures)->toBe(DEMO_FIRST_FIVE)
        ->and(demoRoundSignatures(5))->toBe(DEMO_FIRST_FIVE)
        ->and(demoRoundCards())->toBe($firstCards);
});

test('the demonstration refuses a base that already has a player and writes nothing', function () {
    Player::factory()->create(['name' => 'Gab']);

    expect(fn () => $this->seed(DemoDatabaseSeeder::class))
        ->toThrow(RuntimeException::class, 'Le jeu de démonstration ne s’écrit que sur une base sans joueur ni round : lancer make demo.');

    expect(Player::query()->pluck('name')->all())->toBe(['Gab'])
        ->and(Round::query()->count())->toBe(0);
});

// Mutation: the guard that only counts players: the seeder writes next to the existing round.
test('the demonstration refuses a base that already has a round and writes nothing', function () {
    Round::factory()->create(['course_name' => 'OLD COURSE']);

    expect(fn () => $this->seed(DemoDatabaseSeeder::class))
        ->toThrow(RuntimeException::class, 'Le jeu de démonstration ne s’écrit que sur une base sans joueur ni round : lancer make demo.');

    expect(Player::query()->count())->toBe(0)
        ->and(Round::query()->pluck('course_name')->all())->toBe(['OLD COURSE']);
});

// No request leaves the seeding: Http::preventStrayRequests() of tests/Pest.php throws on any.
test('the demonstration confirms BETHPAGE BLACK / Red from SGT, proposes PAYNES VALLEY / Blue, finds no CALA NOVA, queues no lookup and computes the index history of every round', function () {
    Queue::fake();

    $this->seed(DemoDatabaseSeeder::class);

    Queue::assertNothingPushed();

    expect(CourseRating::query()->orderBy('course_name')->get()->map(fn (CourseRating $rating): array => [
        $rating->course_name, $rating->tees, $rating->rating_t, $rating->slope, $rating->source,
        $rating->status->value, $rating->sgt_course_id, $rating->proposed_tee,
    ])->all())
        ->toBe([
            ['BETHPAGE BLACK', 'Red', 714, 144, 'sgt', 'confirmed', 30, 'Red'],
            ['CALA NOVA', 'White', null, null, null, 'not_found', null, null],
            ['PAYNES VALLEY', 'Blue', null, null, null, 'proposed', 573, 'Blue'],
        ])
        // jsonb keeps its keys in its own order: compared by value.
        ->and(CourseRating::query()->where('course_name', 'PAYNES VALLEY')->sole()->tees_read)
        ->toEqual([['name' => 'Blue', 'ratingT' => 729, 'slope' => 131]])
        ->and(PlayerRoundStat::query()->count())->toBe(RoundPlayer::query()->count());

    // The reference round of BETHPAGE BLACK / Red computed on 71,4 / 144: Gab 72 and Manu 88 give
    // 0,5 and 13,0 (tasks.md, preamble), not 1,0 and 17,0 on the par and 113.
    $reference = Round::query()->whereDate('played_on', '2026-10-17')->sole();

    expect(PlayerRoundStat::query()->where('round_id', $reference->id)->join('players', 'players.id', '=', 'player_round_stats.player_id')
        ->orderBy('players.name')->pluck('differential_t', 'players.name')->all())
        ->toBe(['Gab' => 5, 'Manu' => 130]);
});

// Mutation: DemoImportsSeeder removed from DemoDatabaseSeeder: PendingImports counts 0.
test('the demonstration fills the home, the strokes page and the imports to check', function () {
    $this->seed(DemoDatabaseSeeder::class);

    $standings = collect(app(PlayerStandings::class)->get())
        ->map(fn (array $player): array => [$player['name'], $player['indexT'] !== null])
        ->all();
    expect($standings)->toBe([['Gab', true], ['Manu', true], ['Sean', true]]);

    expect(app(PendingImports::class)->count())->toBe(1);

    $latest = app(RoundList::class)->get(1);
    expect($latest)->toHaveCount(1)
        ->and([$latest[0]['playedOn'], $latest[0]['courseName'], $latest[0]['tees']])
        ->toBe(['2026-10-25', 'PAYNES VALLEY', 'Blue']);

    $courses = collect(app(CourseOverview::class)->get())
        ->map(fn (array $course): array => [$course['courseName'], $course['tees'], $course['ratingT'], $course['slope']])
        ->all();
    expect($courses)->toBe([
        ['BETHPAGE BLACK', 'Red', 714, 144],
        ['CALA NOVA', 'White', null, null],
        ['PAYNES VALLEY', 'Blue', null, null],
    ]);
});

// The page « Calcul de l'index » (index-computation-page, task 7.1) has a full example in the
// demonstration: Manu's latest round is on PAYNES VALLEY without confirmed rating (the virtual
// match round), the one before on the confirmed rating of BETHPAGE BLACK / Red, and his
// 2026-09-10 round on PAYNES VALLEY has 8 on the par 3 of hole 2, capped at 7.
test('the demonstration gives Manu a capped hole, a round without rating and a round on a confirmed rating among his 20 latest rounds', function () {
    $this->seed(DemoDatabaseSeeder::class);

    $computation = app(PlayerIndexComputation::class)->get(Player::query()->where('name', 'Manu')->sole()->id);
    $round = fn (string $playedOn): array => collect($computation['rounds'])->firstWhere('playedOn', $playedOn);

    expect($computation['cappedHole'])->toBe(['hole' => 2, 'par' => 3, 'strokes' => 8])
        ->and($computation['rounds'])->toHaveCount(20)
        ->and(array_slice(array_map(
            fn (array $round): array => [$round['playedOn'], $round['courseName'], $round['ratingT'], $round['slope']],
            $computation['rounds'],
        ), 0, 2))->toBe([
            ['2026-10-25', 'PAYNES VALLEY', null, null],
            ['2026-10-17', 'BETHPAGE BLACK', 714, 144],
        ])
        ->and([$round('2026-09-10')['courseName'], $round('2026-09-10')['gross'], $round('2026-09-10')['adjusted']])
        ->toBe(['PAYNES VALLEY', 93, 92]);
});
