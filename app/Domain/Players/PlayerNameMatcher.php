<?php

namespace App\Domain\Players;

use App\Models\Player;
use App\Models\PlayerAlias;
use Illuminate\Support\Collection;

/**
 * Finds the player of a name read on a card (spec players, « Reconnaissance des noms lus sur la
 * carte »), the port of matchPlayerName of the Next application.
 */
final class PlayerNameMatcher
{
    private const int MAX_CLOSE_DISTANCE = 1;

    /**
     * The player whose name or alias is the read name, whatever the case and the surrounding
     * spaces; otherwise the only player at most 1 character away; otherwise null.
     *
     * @param  Collection<int, Player>  $players  with their aliases loaded
     */
    public static function match(string $read, Collection $players): ?PlayerMatch
    {
        $wanted = PlayerName::alias($read);

        $distances = $players->map(fn (Player $player): array => [
            'playerId' => $player->id,
            'distance' => min(array_map(
                fn (string $label): int => self::distance($wanted, PlayerName::alias($label)),
                [$player->name, ...$player->aliases->map(fn (PlayerAlias $alias): string => $alias->alias)->all()],
            )),
        ]);

        $exact = $distances->firstWhere('distance', 0);
        if ($exact !== null) {
            return new PlayerMatch($exact['playerId'], 'exact');
        }

        $close = $distances->filter(fn (array $candidate): bool => $candidate['distance'] <= self::MAX_CLOSE_DISTANCE);

        return $close->count() === 1 ? new PlayerMatch($close->sole()['playerId'], 'close') : null;
    }

    /**
     * The edit distance counted in characters, not in bytes as levenshtein() does: « ÉMILE » and
     * « EMILE » are 1 apart.
     */
    private static function distance(string $a, string $b): int
    {
        $a = mb_str_split($a);
        $b = mb_str_split($b);

        $previous = range(0, count($b));
        foreach ($a as $i => $charA) {
            $current = [$i + 1];
            foreach ($b as $j => $charB) {
                $current[$j + 1] = min(
                    $previous[$j + 1] + 1,
                    $current[$j] + 1,
                    $previous[$j] + ($charA === $charB ? 0 : 1),
                );
            }
            $previous = $current;
        }

        return $previous[count($b)];
    }
}
