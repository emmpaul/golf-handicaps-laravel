<?php

namespace App\Domain\Handicaps;

/**
 * The net leaderboard of a round (spec rounds), ported from rankNet of the Next.js application:
 * the playing handicap is the course handicap from the index just before the round, the net is
 * gross − playing handicap. Equal nets share the same rank (competition rank: 1, 2, 2, 4).
 */
final class NetRanking
{
    /**
     * @param  list<NetEntry>  $entries
     * @param  RatedCourse|null  $course  null: the index rounded, as without rating
     * @return list<RankedNet> in the order of the entries
     */
    public static function rank(array $entries, ?RatedCourse $course): array
    {
        $handicaps = array_map(
            fn (NetEntry $entry): ?int => $entry->indexBeforeT === null ? null : Whs::courseHandicap($entry->indexBeforeT, $course),
            $entries,
        );

        $nets = [];
        foreach ($entries as $i => $entry) {
            $nets[$i] = $handicaps[$i] === null ? null : $entry->gross - $handicaps[$i];
        }
        $ranked = array_values(array_filter($nets, fn (?int $net): bool => $net !== null));

        return array_map(
            fn (NetEntry $entry, ?int $handicap, ?int $net): RankedNet => new RankedNet(
                gross: $entry->gross,
                indexBeforeT: $entry->indexBeforeT,
                playingHandicap: $handicap,
                net: $net,
                rank: $net === null ? null : 1 + count(array_filter($ranked, fn (int $other): bool => $other < $net)),
            ),
            $entries,
            $handicaps,
            $nets,
        );
    }
}
