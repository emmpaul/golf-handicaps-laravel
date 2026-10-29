<?php

/*
 * Mutations that turn these tests red:
 * - levenshtein() of PHP, which counts bytes: « EMILE » against « ÉMILE » is 2 apart and gives
 *   null instead of close;
 * - the first close player kept instead of the only one: « SOM » gives Sam instead of null;
 * - the name compared with its case: « gab » is not an exact match of Gab;
 * - the aliases ignored (the name alone compared): « SEANP », alias of Sean only, is only close
 *   to the name Sean instead of exact.
 */

use App\Domain\Players\PlayerNameMatcher;
use App\Models\Player;
use App\Models\PlayerAlias;
use Illuminate\Support\Collection;

/**
 * Players held in memory, with their aliases loaded, as the matcher receives them.
 *
 * @param  array<string, list<string>>  $aliasesByName
 * @return Collection<int, Player>
 */
function candidates(array $aliasesByName): Collection
{
    return collect($aliasesByName)->map(function (array $aliases, string $name): Player {
        $player = new Player(['name' => $name]);
        $player->id = 'id-'.$name;
        $player->setRelation('aliases', collect($aliases)->map(fn (string $alias): PlayerAlias => new PlayerAlias(['alias' => $alias])));

        return $player;
    })->values();
}

test('a read name finds its player', function (string $read, array $players, ?array $expected) {
    $match = PlayerNameMatcher::match($read, candidates($players));

    expect($match === null ? null : [$match->playerId, $match->kind])->toBe($expected);
})->with([
    'same alias, other case' => ['gab', ['Gab' => ['GAB'], 'Manu' => ['MANU']], ['id-Gab', 'exact']],
    'one character misread' => ['MANV', ['Gab' => ['GAB'], 'Manu' => ['MANU']], ['id-Manu', 'close']],
    'same name among two close players' => ['SAM', ['Sam' => [], 'Sim' => []], ['id-Sam', 'exact']],
    'one character from two players' => ['SOM', ['Sam' => [], 'Sim' => []], null],
    'unknown name' => ['JULIEN', ['Gab' => ['GAB'], 'Manu' => ['MANU']], null],
    // Spec players « Alias mémorisé »: the alias learned at the previous import.
    'memorised alias only' => ['SEANP', ['Gab' => ['GAB'], 'Sean' => ['SEAN', 'SEANP']], ['id-Sean', 'exact']],
    'accent misread' => ['EMILE', ['Émile' => ['ÉMILE']], ['id-Émile', 'close']],
]);
