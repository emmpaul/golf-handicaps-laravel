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

        $nets = array_map(
            fn (NetEntry $entry, ?int $handicap): ?int => $handicap === null ? null : $entry->gross - $handicap,
            $entries,
            $handicaps,
        );
        $ranks = self::competitionRanks($nets);

        return array_map(
            fn (NetEntry $entry, ?int $handicap, ?int $net, ?int $rank): RankedNet => new RankedNet(
                gross: $entry->gross,
                indexBeforeT: $entry->indexBeforeT,
                playingHandicap: $handicap,
                net: $net,
                rank: $rank,
            ),
            $entries,
            $handicaps,
            $nets,
            $ranks,
        );
    }

    /**
     * The competition rank of each net: a null net is not ranked, equal nets share the rank and
     * the next rank skips the shared places (80, 70, 59, 70 → 4, 2, 1, 2).
     *
     * @param  list<?int>  $nets
     * @return list<?int> in the order of the nets
     */
    public static function competitionRanks(array $nets): array
    {
        $ranked = array_values(array_filter($nets, fn (?int $net): bool => $net !== null));

        return array_map(
            fn (?int $net): ?int => $net === null ? null : 1 + count(array_filter($ranked, fn (int $other): bool => $other < $net)),
            $nets,
        );
    }
}
