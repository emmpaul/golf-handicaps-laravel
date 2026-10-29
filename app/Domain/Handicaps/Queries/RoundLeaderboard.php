<?php

namespace App\Domain\Handicaps\Queries;

use App\Domain\Handicaps\NetEntry;
use App\Domain\Handicaps\NetRanking;
use App\Domain\Handicaps\RankedNet;
use App\Domain\Handicaps\RatedCourse;
use App\Models\CourseRating;
use App\Models\Player;
use App\Models\Round;
use App\Models\RoundPlayer;

/**
 * The net leaderboard of a round (spec rounds, « Classement net d'un round », « Handicap de
 * jeu d'un round »), port of roundLeaderboard of the Next.js application: the lines in the
 * order of the card with their gross and the index just before the round from
 * player_round_stats, ranked on the rating of the round. Neither the playing handicap nor the
 * net is stored: they follow the rating, which can change.
 */
final class RoundLeaderboard
{
    /**
     * @param  array{rated: bool, ratingT: int, slope: int}|null  $rating  the rating of the round when
     *                                                                     the caller has read it already
     * @return list<array{playerId: string, name: string, colorSlot: int, gross: int, indexBeforeT: int|null, playingHandicap: int|null, net: int|null, rank: int|null}>
     */
    public function for(Round $round, ?array $rating = null): array
    {
        $rating ??= $this->rating($round);

        $lines = RoundPlayer::query()
            ->join('players', 'players.id', '=', 'round_players.player_id')
            ->joinSub(Player::colorSlots(), 'player_slots', 'player_slots.id', '=', 'round_players.player_id')
            ->join('player_round_stats', fn ($join) => $join
                ->on('player_round_stats.round_id', '=', 'round_players.round_id')
                ->on('player_round_stats.player_id', '=', 'round_players.player_id'))
            ->where('round_players.round_id', $round->id)
            ->orderBy('round_players.position')
            ->toBase()
            ->get(['round_players.player_id', 'players.name', 'player_slots.color_slot', 'player_round_stats.gross', 'player_round_stats.index_before_t'])
            ->all();

        // Without a typed rating, the fallback (par × 10, 113) gives the index rounded, as Next.
        $ranked = NetRanking::rank(
            array_values(array_map(fn (object $line): NetEntry => new NetEntry(
                (int) $line->gross,
                $line->index_before_t === null ? null : (int) $line->index_before_t,
            ), $lines)),
            new RatedCourse($rating['ratingT'], $rating['slope'], $this->par($round)),
        );

        return array_map(fn (object $line, RankedNet $net): array => [
            'playerId' => $line->player_id,
            'name' => $line->name,
            'colorSlot' => (int) $line->color_slot,
            'gross' => $net->gross,
            'indexBeforeT' => $net->indexBeforeT,
            'playingHandicap' => $net->playingHandicap,
            'net' => $net->net,
            'rank' => $net->rank,
        ], $lines, $ranked);
    }

    /**
     * The rating used for the differentials and the leaderboard of the round, port of ratingFor:
     * the one confirmed for its course and tees, without case nor surrounding spaces (compared in
     * SQL), or the par total of the round and 113. A row of the lookup without confirmed value
     * counts as no rating (design D1 of course-rating-sgt).
     *
     * @return array{rated: bool, ratingT: int, slope: int}
     */
    public function rating(Round $round): array
    {
        $confirmed = CourseRating::query()
            ->whereRaw('lower(trim(course_name)) = lower(trim(?))', [$round->course_name])
            ->whereRaw('lower(trim(tees)) = lower(trim(?))', [$round->tees])
            ->first(['rating_t', 'slope']);

        // The CHECKs of course_ratings set rating_t and slope together.
        return $confirmed?->rating_t === null || $confirmed->slope === null
            ? ['rated' => false, 'ratingT' => $this->par($round) * 10, 'slope' => 113]
            : ['rated' => true, 'ratingT' => $confirmed->rating_t, 'slope' => $confirmed->slope];
    }

    /**
     * The par total of the round's own holes; loaded once, shared with the caller.
     */
    private function par(Round $round): int
    {
        $round->loadMissing('holes');

        return (int) $round->holes->sum('par');
    }
}
