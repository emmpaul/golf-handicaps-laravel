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
 * - the demonstration rating written by CourseRating::factory() instead of SaveCourseRating:
 *   the differentials are not recomputed and Manu stays at 170 on the reference round;
 * - DemoCourseRatingsSeeder called after DemoRoundsSeeder: each round finds no row and its
 *   lookup ends in « Saisie manuelle » (SGT_LOOKUP off), not in the statuses of the literal list;
 * - DemoConfirmedRatingSeeder called before DemoRoundsSeeder: SaveCourseRating finds no round on
 *   BETHPAGE BLACK / Red and the seeding fails with « Parcours introuvable ».
 */

use App\Domain\Courses\Queries\CourseOverview;
use App\Domain\Handicaps\Queries\PlayerStandings;
use App\Domain\Imports\ImportStatus;
use App\Domain\Imports\Queries\PendingImports;
use App\Domain\Rounds\Queries\RoundList;
use App\Domain\Rounds\RoundCard;
use App\Domain\Rounds\RoundCardChecks;
use App\Models\CourseRating;
use App\Models\Import;
use App\Models\Player;
use App\Models\PlayerRoundStat;
use App\Models\Round;
use App\Models\RoundPlayer;
use Database\Seeders\DemoDatabaseSeeder;
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
    '2026-10-17 BETHPAGE BLACK / Red : Gab 72, Manu 88',
    '2026-10-15 CALA NOVA / White : Manu 95, Gab 75, Sean 89',
    '2026-10-06 PAYNES VALLEY / Blue : Manu 99, Gab 71',
    '2026-09-27 BETHPAGE BLACK / Red : Gab 70, Sean 88, Manu 94',
    '2026-09-19 CALA NOVA / White : Gab 76, Manu 96',
];

beforeEach(function () {
    Storage::fake('photos');
});

test('the demonstration base has the development players, the reference round with its photo and generated rounds', function () {
    Storage::disk('photos')->put('rounds/stale.webp', 'old');

    $this->seed(DemoDatabaseSeeder::class);

    expect(playersWithAliases())->toBe(['Gab' => ['GAB'], 'Manu' => ['MANU'], 'Sean' => ['SEAN']])
        ->and(Round::query()->count())->toBe(25)
        ->and(Round::query()->selectRaw("course_name || ' / ' || tees as course")->distinct()->orderBy('course')->pluck('course')->all())
        ->toBe(['BETHPAGE BLACK / Red', 'CALA NOVA / White', 'PAYNES VALLEY / Blue'])
        ->and(Round::query()->min('played_on'))->toBe('2026-04-01')
        ->and(Round::query()->where('played_on', '<', '2026-10-17')->max('played_on'))->toBe('2026-10-15');

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

test('the demonstration rates BETHPAGE BLACK / Red, proposes PAYNES VALLEY / Blue, finds no CALA NOVA and computes the index history of every round', function () {
    $this->seed(DemoDatabaseSeeder::class);

    expect(CourseRating::query()->orderBy('course_name')->get()->map(fn (CourseRating $rating): array => [
        $rating->course_name, $rating->tees, $rating->rating_t, $rating->slope, $rating->status->value,
    ])->all())
        ->toBe([
            ['BETHPAGE BLACK', 'Red', 714, 144, 'confirmed'],
            ['CALA NOVA', 'White', null, null, 'not_found'],
            ['PAYNES VALLEY', 'Blue', null, null, 'proposed'],
        ])
        ->and(PlayerRoundStat::query()->count())->toBe(RoundPlayer::query()->count());

    $reference = Round::query()->whereDate('played_on', '2026-10-17')->sole();
    $manu = Player::query()->where('name', 'Manu')->sole();

    expect(PlayerRoundStat::query()->where('round_id', $reference->id)->where('player_id', $manu->id)->value('differential_t'))
        ->toBe(130);
});

// Mutation: DemoImportsSeeder removed from DemoDatabaseSeeder: PendingImports counts 0 and has no latest id.
test('the demonstration fills the home, the strokes page and the imports to check', function () {
    $this->seed(DemoDatabaseSeeder::class);

    $standings = collect(app(PlayerStandings::class)->get())
        ->map(fn (array $player): array => [$player['name'], $player['indexT'] !== null])
        ->all();
    expect($standings)->toBe([['Gab', true], ['Manu', true], ['Sean', true]]);

    $readyId = Import::query()->where('status', ImportStatus::Ready->value)->value('id');
    expect(app(PendingImports::class)->get())->toBe(['count' => 1, 'latestId' => $readyId]);

    $latest = app(RoundList::class)->get(1);
    expect($latest)->toHaveCount(1)
        ->and([$latest[0]['playedOn'], $latest[0]['courseName'], $latest[0]['tees']])
        ->toBe(['2026-10-17', 'BETHPAGE BLACK', 'Red']);

    $courses = collect(app(CourseOverview::class)->get())
        ->map(fn (array $course): array => [$course['courseName'], $course['tees'], $course['ratingT'], $course['slope']])
        ->all();
    expect($courses)->toBe([
        ['BETHPAGE BLACK', 'Red', 714, 144],
        ['CALA NOVA', 'White', null, null],
        ['PAYNES VALLEY', 'Blue', null, null],
    ]);
});
