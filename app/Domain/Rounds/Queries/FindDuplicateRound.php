<?php

namespace App\Domain\Rounds\Queries;

use App\Domain\Rounds\RoundCard;
use App\Models\Round;

/**
 * A saved round with the same course and tees (without case nor surrounding spaces), the same
 * date and the same gross for the same players, whatever the order of the lines. Not an
 * invariant: the person may save anyway (design D5).
 */
final class FindDuplicateRound
{
    /**
     * @param  Round|null  $except  the round being corrected, never its own duplicate
     */
    public function for(RoundCard $card, ?Round $except = null): ?Round
    {
        $candidates = Round::query()
            ->whereRaw('lower(trim(course_name)) = lower(trim(?))', [(string) $card->courseName])
            ->whereRaw('lower(trim(tees)) = lower(trim(?))', [(string) $card->tees])
            // Without a date (an incoherent card), where() becomes whereNull(): no candidate.
            ->where('played_on', $card->playedOn)
            ->when($except, fn ($query, Round $round) => $query->whereKeyNot($round->getKey()))
            ->with('scores')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        $wanted = self::signature(array_map(
            fn (array $line): array => [(string) $line['playerId'], array_sum($line['strokes'])],
            $card->lines,
        ));

        return $candidates->first(fn (Round $round): bool => self::signature(
            $round->scores
                ->groupBy('player_id')
                ->map(fn ($scores, string $playerId): array => [$playerId, (int) $scores->sum('strokes')])
                ->values()
                ->all(),
        ) === $wanted);
    }

    /**
     * « player:gross » of each line, sorted: the order of the lines does not count.
     *
     * @param  array<int, array{string, int}>  $lines
     */
    private static function signature(array $lines): string
    {
        $parts = array_map(fn (array $line): string => "{$line[0]}:{$line[1]}", $lines);
        sort($parts);

        return implode(',', $parts);
    }
}
