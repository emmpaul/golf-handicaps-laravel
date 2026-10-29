<?php

use App\Domain\AiUsage\AiBudget;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Tests\Support\LegacySqlite\LegacySqliteFixture;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-29 03:12:45', 'UTC'));
    $this->base = LegacySqliteFixture::countsSet();
    [$this->exitCode, $this->lines] = importSqlite($this->base->path);
});

// Spec « Comptes identiques ».
// Mutation that turns it red: the scores inserted with the SQLite round id (foreign key of
// scores not remapped): PostgreSQL refuses the row.
test('every row of the base is imported once and no job of Next is queued', function () {
    expect($this->exitCode)->toBe(0)
        ->and(nonEmptyTargetTables())->toMatchArray([
            'players' => 3, 'player_aliases' => 7, 'rounds' => 2, 'round_holes' => 36, 'round_players' => 4,
            'scores' => 72, 'course_ratings' => 2, 'imports' => 2, 'ai_usage' => 3,
        ])
        ->and(DB::table('jobs')->count())->toBe(0);
});

// Spec « Clés remappées ». Mutation that turns it red: the player of each score swapped, Gab
// for Manu and Manu for Gab.
test('each score, hole and line points to its imported round and player', function () {
    $strokes = DB::table('scores')
        ->join('rounds', 'rounds.id', '=', 'scores.round_id')
        ->join('players', 'players.id', '=', 'scores.player_id')
        ->orderBy('rounds.played_on')->orderBy('players.name')->orderBy('scores.hole')
        ->get(['rounds.played_on', 'players.name', 'scores.strokes'])
        ->groupBy(fn (object $score): string => "{$score->played_on} {$score->name}")
        ->map(fn ($scores): array => $scores->pluck('strokes')->all())
        ->all();

    expect($strokes)->toBe([
        '2026-10-10 Gab' => [5, 5, 4, 6, 5, 5, 6, 4, 5, 5, 5, 5, 6, 4, 5, 5, 4, 4],
        '2026-10-10 Sean' => [5, 5, 4, 6, 5, 5, 6, 4, 5, 5, 5, 5, 5, 3, 4, 4, 3, 4],
        '2026-10-17 Gab' => [5, 5, 3, 5, 4, 4, 4, 3, 5, 4, 4, 4, 4, 3, 4, 5, 3, 3],
        '2026-10-17 Manu' => [7, 5, 3, 7, 6, 5, 5, 3, 5, 3, 6, 5, 7, 4, 5, 4, 3, 5],
    ]);

    $lines = DB::table('round_players')
        ->join('rounds', 'rounds.id', '=', 'round_players.round_id')
        ->join('players', 'players.id', '=', 'round_players.player_id')
        ->orderBy('rounds.played_on')->orderBy('round_players.position')
        ->get(['rounds.played_on', 'round_players.position', 'players.name'])
        ->map(fn (object $line): array => [$line->played_on, $line->position, $line->name])
        ->all();

    expect($lines)->toBe([
        ['2026-10-10', 0, 'Gab'], ['2026-10-10', 1, 'Sean'],
        ['2026-10-17', 0, 'Gab'], ['2026-10-17', 1, 'Manu'],
    ]);

    $holes = DB::table('round_holes')
        ->join('rounds', 'rounds.id', '=', 'round_holes.round_id')
        ->orderBy('rounds.played_on')->orderBy('round_holes.hole')
        ->get(['rounds.played_on', 'round_holes.par', 'round_holes.stroke_index', 'round_holes.distance'])
        ->groupBy('played_on')
        ->map(fn ($holes): array => [$holes->pluck('par')->all(), $holes->pluck('stroke_index')->all(), $holes->pluck('distance')->all()])
        ->all();

    $referenceHoles = [
        [4, 4, 3, 5, 4, 4, 5, 3, 4, 4, 4, 4, 5, 3, 4, 4, 3, 4],
        [8, 16, 18, 2, 4, 10, 6, 14, 12, 9, 11, 7, 3, 17, 1, 5, 13, 15],
        [420, 344, 128, 444, 398, 377, 480, 155, 392, 353, 405, 392, 470, 149, 401, 423, 173, 337],
    ];
    expect($holes)->toBe(['2026-10-10' => $referenceHoles, '2026-10-17' => $referenceHoles]);
});

// Mutation that turns it red: the alias inserted with the SQLite player id unmapped to its
// own player (aliases given to players in the order of the alias ids).
test('players and aliases keep their names, each alias on its player', function () {
    $aliases = DB::table('player_aliases')
        ->join('players', 'players.id', '=', 'player_aliases.player_id')
        ->orderBy('players.name')->orderBy('player_aliases.alias')
        ->get(['players.name', 'player_aliases.alias'])
        ->map(fn (object $alias): array => [$alias->name, $alias->alias])
        ->all();

    expect($aliases)->toBe([
        ['Gab', 'GAB'], ['Gab', 'GABRIEL'],
        ['Manu', 'EMMANUEL'], ['Manu', 'MANU'], ['Manu', 'MANUEL'],
        ['Sean', 'SEAN'], ['Sean', 'SEANP'],
    ]);
});

// Spec « Instant d'un round ». Mutations that turn it red: the milliseconds rounded instead of
// truncated (15:44:04), updated_at left null.
test('the instant of a round is converted to UTC to the second, milliseconds truncated', function () {
    $round = DB::table('rounds')->where('played_on', '2026-10-17')->first();

    expect([$round->course_name, $round->tees, $round->played_on, $round->created_at, $round->updated_at])
        ->toBe(['BETHPAGE BLACK', 'Red', '2026-10-17', '2026-10-17 15:44:03', '2026-10-17 15:44:03']);
});

// Mutation that turns it red: the aliases given the creation instant of their player.
test('an alias, without a date in Next, is dated at the instant of the import', function () {
    expect(DB::table('player_aliases')->distinct()->pluck('created_at')->all())->toBe(['2026-10-29 03:12:45'])
        ->and(DB::table('player_aliases')->distinct()->pluck('updated_at')->all())->toBe(['2026-10-29 03:12:45']);
});

// Mutation that turns it red: created_at of a rating left null (Next has only updated_at).
test('a rating takes its SQLite update instant for both dates', function () {
    $ratings = DB::table('course_ratings')->orderBy('course_name')
        ->get(['course_name', 'tees', 'status', 'created_at', 'updated_at'])
        ->map(fn (object $rating): array => (array) $rating)
        ->all();

    expect($ratings)->toBe([
        ['course_name' => 'BETHPAGE BLACK', 'tees' => 'Red', 'status' => 'pending', 'created_at' => '2026-10-21 23:09:50', 'updated_at' => '2026-10-21 23:09:50'],
        ['course_name' => 'PAYNES VALLEY', 'tees' => 'Blue', 'status' => 'choose_course', 'created_at' => '2026-10-21 23:09:50', 'updated_at' => '2026-10-21 23:09:50'],
    ]);
});

// Spec « Consommation IA du mois ». Mutation that turns it red: cost_micro_usd not copied (0).
test('the ai usage is copied and counts in the budget of its month', function () {
    $usage = DB::table('ai_usage')->where('month', '2026-10')->get(['month', 'model', 'input_tokens', 'output_tokens', 'cost_micro_usd', 'created_at'])
        ->map(fn (object $row): array => (array) $row)
        ->all();

    expect($usage)->toBe([[
        'month' => '2026-10', 'model' => 'claude-opus-5-5', 'input_tokens' => 3000,
        'output_tokens' => 1200, 'cost_micro_usd' => 36000, 'created_at' => '2026-10-17 15:43:30',
    ]]);

    // A ceiling of 36 000 micro-dollars: the month has spent exactly that much.
    config(['golf.ai.monthly_budget_usd' => 0.036]);
    $october = CarbonImmutable::parse('2026-10-20 12:00:00', 'UTC');
    expect(app(AiBudget::class)->canSpend($october, 0))->toBeTrue()
        ->and(app(AiBudget::class)->canSpend($october, 1))->toBeFalse();
});

// Mutation that turns it red: the import of round 1 given the UUID of round 2 (round_id not
// remapped through its own key).
test('an import keeps its round and both share the photo path under imports/', function () {
    $imports = DB::table('imports')
        ->leftJoin('rounds', 'rounds.id', '=', 'imports.round_id')
        ->orderBy('imports.created_at')
        ->get(['imports.status', 'imports.photo_path', 'rounds.played_on', 'rounds.photo_path as round_photo_path', 'imports.created_at'])
        ->map(fn (object $import): array => (array) $import)
        ->all();

    expect($imports)->toBe([
        ['status' => 'ready', 'photo_path' => 'imports/3f2a.webp', 'played_on' => '2026-10-17', 'round_photo_path' => 'imports/3f2a.webp', 'created_at' => '2026-10-17 15:43:20'],
        ['status' => 'failed', 'photo_path' => 'imports/9b7c.webp', 'played_on' => null, 'round_photo_path' => null, 'created_at' => '2026-10-17 15:45:00'],
    ]);
});
