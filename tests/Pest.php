<?php

use App\Domain\Rounds\Actions\CreateRound;
use App\Domain\Rounds\RoundCard;
use App\Integrations\Ai\ScorecardPhotoReader;
use App\Integrations\Ai\SgtScorecardReader;
use App\Models\Player;
use App\Models\PlayerRoundStat;
use App\Models\Round;
use Carbon\CarbonImmutable;
use Database\Factories\ImportFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\TextUsage;
use Laravel\Ai\Responses\StructuredTextResponse;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

// Design D9: no test reaches Anthropic or any other host. Unit tests that do not boot the
// application have no facade to fake, hence the TestCase guard.
pest()->beforeEach(function () {
    if (! $this instanceof TestCase) {
        return;
    }

    ScorecardPhotoReader::fake()->preventStrayPrompts();
    SgtScorecardReader::fake()->preventStrayPrompts();
    Http::preventStrayRequests();
})->in('Feature', 'Unit');

/**
 * @return array<string, list<string>> the aliases of each player, keyed by player name
 */
function playersWithAliases(): array
{
    return Player::query()->with('aliases')->orderBy('name')->get()
        ->mapWithKeys(fn (Player $player): array => [
            $player->name => $player->aliases->pluck('alias')->sort()->values()->all(),
        ])->all();
}

/**
 * Creates one player per name and keeps each on the running test, as $this->gab for « Gab ».
 */
function createPlayersOnTest(string ...$names): void
{
    foreach ($names as $name) {
        test()->{strtolower($name)} = Player::factory()->create(['name' => $name]);
    }
}

/**
 * The reference card IMG_3064 (BETHPAGE BLACK, tees Red, 2026-10-17, par 71), keyed as the
 * editor sends it: Gab 72 on line 0, Manu 88 on line 1.
 *
 * @return array{courseName: string, tees: string, playedOn: string, holes: list<array{par: int, strokeIndex: int, distance: int}>, lines: list<array{playerId: string|null, strokes: list<int>}>}
 */
function referenceCardData(?string $gabId, ?string $manuId): array
{
    $pars = [4, 4, 3, 5, 4, 4, 5, 3, 4, 4, 4, 4, 5, 3, 4, 4, 3, 4];
    $strokeIndexes = [8, 16, 18, 2, 4, 10, 6, 14, 12, 9, 11, 7, 3, 17, 1, 5, 13, 15];
    $distances = [420, 344, 128, 444, 398, 377, 480, 155, 392, 353, 405, 392, 470, 149, 401, 423, 173, 337];

    return [
        'courseName' => 'BETHPAGE BLACK',
        'tees' => 'Red',
        'playedOn' => '2026-10-17',
        'holes' => array_map(
            fn (int $par, int $strokeIndex, int $distance): array => ['par' => $par, 'strokeIndex' => $strokeIndex, 'distance' => $distance],
            $pars, $strokeIndexes, $distances,
        ),
        'lines' => [
            ['playerId' => $gabId, 'strokes' => [5, 5, 3, 5, 4, 4, 4, 3, 5, 4, 4, 4, 4, 3, 4, 5, 3, 3]],
            ['playerId' => $manuId, 'strokes' => [7, 5, 3, 7, 6, 5, 5, 3, 5, 3, 6, 5, 7, 4, 5, 4, 3, 5]],
        ],
    ];
}

/**
 * Strokes on the 18 holes of the reference card, $over strokes over its par 71 in all: one
 * more (or one less) on the first holes, never above par + 4. Port of strokesOverPar of the
 * Next.js tests.
 *
 * @return list<int>
 */
function strokesOverPar(int $over): array
{
    $pars = [4, 4, 3, 5, 4, 4, 5, 3, 4, 4, 4, 4, 5, 3, 4, 4, 3, 4];
    $step = $over <=> 0;

    return array_map(
        fn (int $par, int $hole): int => $par + $step * (intdiv(abs($over), 18) + ($hole < abs($over) % 18 ? 1 : 0)),
        $pars,
        array_keys($pars),
    );
}

/**
 * The reference card played on another day by other lines, keyed as the editor sends it.
 *
 * @param  list<array{string, list<int>}>  $lines  [playerId, strokes] in the order of the card
 * @return array{courseName: string, tees: string, playedOn: string, holes: list<array{par: int, strokeIndex: int, distance: int}>, lines: list<array{playerId: string|null, strokes: list<int>}>}
 */
function referenceCardOn(string $playedOn, array $lines): array
{
    $card = referenceCardData(null, null);
    $card['playedOn'] = $playedOn;
    $card['lines'] = array_map(fn (array $line): array => ['playerId' => $line[0], 'strokes' => $line[1]], $lines);

    return $card;
}

/**
 * Saves a card through CreateRound, which recomputes the history of its players.
 *
 * @param  array<string, mixed>  $card  keyed as the editor sends it
 */
function createRoundCard(array $card): Round
{
    return app(CreateRound::class)->handle(RoundCard::fromArray($card));
}

/**
 * The rows of player_round_stats of a player by date of play, each reduced to the values of
 * $columns in their order.
 *
 * @param  list<string>  $columns
 * @return list<list<mixed>>
 */
function statsByDateOf(Player $player, array $columns): array
{
    return array_values(PlayerRoundStat::query()
        ->join('rounds', 'rounds.id', '=', 'player_round_stats.round_id')
        ->where('player_round_stats.player_id', $player->id)
        ->orderBy('rounds.played_on')
        ->get()
        ->map(fn (PlayerRoundStat $stat): array => array_map(fn (string $column): mixed => $stat->getAttribute($column), $columns))
        ->all());
}

/**
 * Saves Manu's strokes of the reference card for $player on « Bethpage Black / RED », the course
 * of the reference card in another case, on 2026-10-24.
 */
function createManuStrokesInAnotherCase(Player $player): Round
{
    $card = referenceCardOn('2026-10-24', [[$player->id, referenceCardData(null, null)['lines'][1]['strokes']]]);
    $card['courseName'] = 'Bethpage Black';
    $card['tees'] = 'RED';

    return createRoundCard($card);
}

/**
 * The differential of each player who has a row of player_round_stats, keyed by name; one
 * round per player.
 *
 * @return array<string, int>
 */
function differentialsByName(): array
{
    return PlayerRoundStat::query()
        ->join('players', 'players.id', '=', 'player_round_stats.player_id')
        ->orderBy('players.name')
        ->pluck('player_round_stats.differential_t', 'players.name')
        ->all();
}

/**
 * The card the AI reads on the reference photo IMG_3064: the reference draft with the date as
 * printed instead of playedOn.
 *
 * @return array<string, mixed>
 */
function referenceReading(): array
{
    $card = ImportFactory::referenceDraft();
    unset($card['playedOn']);

    return [...$card, 'dateText' => 'DATE: 10/17/2026'];
}

/**
 * An answer of the agent billed 3 000 input tokens and 1 200 output tokens.
 *
 * @param  array<string, mixed>  $card
 */
function billedAnswer(array $card): StructuredTextResponse
{
    return new StructuredTextResponse($card, (string) json_encode($card), new TextUsage(3000, 1200), new Meta);
}

/**
 * An answer of the SGT scorecard agent billed 1 500 input tokens and 300 output tokens
 * (course-rating-sgt, design D11).
 *
 * @param  array<string, mixed>  $reading
 */
function billedSgtReading(array $reading): StructuredTextResponse
{
    return new StructuredTextResponse($reading, (string) json_encode($reading), new TextUsage(1500, 300), new Meta);
}

/**
 * What the AI reads on tests/fixtures/sgt/scorecard_30.jpg (Bethpage Black): the « Slope/Rating »
 * line of its five tees and the par 71, as the schema of the agent asks.
 *
 * @return array{found: bool, par: int, tees: list<array{name: string, rating: float, slope: int}>}
 */
function bethpageSgtReading(): array
{
    return [
        'found' => true,
        'par' => 71,
        'tees' => [
            ['name' => 'Blue', 'rating' => 77.5, 'slope' => 155],
            ['name' => 'White', 'rating' => 74.2, 'slope' => 148],
            ['name' => 'Red', 'rating' => 71.4, 'slope' => 144],
            ['name' => 'Junior', 'rating' => 71.0, 'slope' => 120],
            ['name' => 'PAR3', 'rating' => 54.0, 'slope' => 120],
        ],
    ];
}

/**
 * Saves the reference card on $count days from 2026-09-02, played by Gab (+1), Manu (+17) and
 * Sean (+12) of the running test: the base of the query count tests (design D8 of
 * home-and-match-strokes).
 */
function createDailyRoundsOfThree(int $count): void
{
    foreach (range(1, $count) as $day) {
        createRoundCard(referenceCardOn(CarbonImmutable::parse('2026-09-01')->addDays($day)->toDateString(), [
            [test()->gab->id, strokesOverPar(1)],
            [test()->manu->id, strokesOverPar(17)],
            [test()->sean->id, strokesOverPar(12)],
        ]));
    }
}

/**
 * The number of queries run by $callback.
 */
function queriesDuring(Closure $callback): int
{
    $queries = 0;
    DB::listen(function () use (&$queries): void {
        $queries++;
    });
    $callback();

    return $queries;
}
