<?php

namespace App\Domain\Handicaps\Queries;

use App\Models\PlayerRoundStat;
use stdClass;

/**
 * The rounds of one player for the Handicaps page (spec handicap-index, « Liste des rounds d'un
 * joueur »): from the latest to the oldest by date of play, date of saving then id, each with
 * its course, its differential, the index just after it and whether its differential counts in
 * the current index. One query.
 */
final class PlayerHandicapHistory
{
    /**
     * @return list<array{roundId: string, playedOn: string, courseName: string, tees: string, differentialT: int, indexAfterT: int, countsInCurrent: bool}>
     */
    public function get(string $playerId): array
    {
        return array_values(PlayerRoundStat::query()
            ->toBase()
            ->join('rounds', 'rounds.id', '=', 'player_round_stats.round_id')
            ->where('player_round_stats.player_id', $playerId)
            ->select([
                'rounds.id as round_id',
                'rounds.played_on',
                'rounds.course_name',
                'rounds.tees',
                'player_round_stats.differential_t',
                'player_round_stats.index_after_t',
                'player_round_stats.counts_in_current',
            ])
            ->orderByDesc('rounds.played_on')
            ->orderByDesc('rounds.created_at')
            ->orderByDesc('rounds.id')
            ->get()
            ->map(fn (stdClass $row): array => [
                'roundId' => (string) $row->round_id,
                'playedOn' => (string) $row->played_on,
                'courseName' => (string) $row->course_name,
                'tees' => (string) $row->tees,
                'differentialT' => (int) $row->differential_t,
                'indexAfterT' => (int) $row->index_after_t,
                'countsInCurrent' => (bool) $row->counts_in_current,
            ])
            ->all());
    }
}
