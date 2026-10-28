<?php

use App\Models\Player;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

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
