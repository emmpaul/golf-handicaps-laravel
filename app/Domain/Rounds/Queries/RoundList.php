<?php

namespace App\Domain\Rounds\Queries;

use App\Domain\Handicaps\NetEntry;
use App\Domain\Handicaps\NetRanking;
use App\Domain\Handicaps\RatedCourse;
use App\Models\PlayerRoundStat;
use App\Models\Round;
use App\Models\RoundPlayer;
use App\Models\Score;
use Illuminate\Database\Query\JoinClause;
use stdClass;

/**
 * The rounds of the Rounds page (spec rounds, « Liste des rounds »): from the latest to the
 * oldest by date of play, then by date of saving, each with the gross of its players in the
 * order of the card, and its net winners: every player ranked first, ties included, none when
 * nobody had an index before the round. Five queries whatever the number of rounds.
 */
final class RoundList
{
    /**
     * @return list<array{id: string, playedOn: string, courseName: string, tees: string, players: list<array{name: string, gross: int}>, netWinners: list<string>}>
     */
    public function get(): array
    {
        $rounds = Round::query()
            ->with('lines.player:id,name')
            ->orderByDesc('played_on')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        $gross = Score::query()
            ->toBase()
            ->selectRaw('round_id, player_id, sum(strokes) as gross')
            ->groupBy('round_id', 'player_id')
            ->get()
            ->mapWithKeys(fn (object $row): array => ["{$row->round_id}:{$row->player_id}" => (int) $row->gross]);

        $stats = $this->statsByRound();

        return array_values($rounds->map(fn (Round $round): array => [
            'id' => $round->id,
            'playedOn' => $round->played_on->toDateString(),
            'courseName' => $round->course_name,
            'tees' => $round->tees,
            'players' => array_values($round->lines->map(fn (RoundPlayer $line): array => [
                'name' => $line->player->name,
                'gross' => $gross->get("{$round->id}:{$line->player_id}", 0),
            ])->all()),
            'netWinners' => $this->netWinners($round, $stats[$round->id] ?? null),
        ])->all());
    }

    /**
     * The course of every round with a line in player_round_stats, keyed by round: its par
     * total, the rating typed for its course and tees (compared in SQL without case nor
     * surrounding spaces, null without) and the gross and index before the round of each
     * player, keyed by player. One query.
     *
     * @return array<string, array{par: int, ratingT: int|null, slope: int|null, entries: array<string, NetEntry>}>
     */
    private function statsByRound(): array
    {
        $rows = PlayerRoundStat::query()
            ->toBase()
            ->join('rounds', 'rounds.id', '=', 'player_round_stats.round_id')
            ->leftJoin('course_ratings', fn (JoinClause $join): JoinClause => $join
                ->whereRaw('lower(trim(course_ratings.course_name)) = lower(trim(rounds.course_name))')
                ->whereRaw('lower(trim(course_ratings.tees)) = lower(trim(rounds.tees))'))
            ->select([
                'player_round_stats.round_id',
                'player_round_stats.player_id',
                'player_round_stats.gross',
                'player_round_stats.index_before_t',
                'course_ratings.rating_t',
                'course_ratings.slope',
            ])
            ->selectRaw('(select sum(round_holes.par) from round_holes where round_holes.round_id = rounds.id) as par')
            ->get();

        $byRound = [];
        foreach ($rows as $row) {
            /** @var stdClass $row */
            $byRound[$row->round_id] ??= [
                'par' => (int) $row->par,
                'ratingT' => $row->rating_t === null ? null : (int) $row->rating_t,
                'slope' => $row->slope === null ? null : (int) $row->slope,
                'entries' => [],
            ];
            $byRound[$row->round_id]['entries'][$row->player_id] = new NetEntry(
                (int) $row->gross,
                $row->index_before_t === null ? null : (int) $row->index_before_t,
            );
        }

        return $byRound;
    }

    /**
     * The names of the players ranked first in net, in the order of the card.
     *
     * @param  array{par: int, ratingT: int|null, slope: int|null, entries: array<string, NetEntry>}|null  $stats
     * @return list<string>
     */
    private function netWinners(Round $round, ?array $stats): array
    {
        if ($stats === null) {
            return [];
        }

        $lines = array_values(array_filter(
            $round->lines->all(),
            fn (RoundPlayer $line): bool => isset($stats['entries'][$line->player_id]),
        ));

        // Without a typed rating, the par total and 113, as the leaderboard of the round.
        $ranked = NetRanking::rank(
            array_map(fn (RoundPlayer $line): NetEntry => $stats['entries'][$line->player_id], $lines),
            $stats['ratingT'] === null || $stats['slope'] === null
                ? new RatedCourse($stats['par'] * 10, 113, $stats['par'])
                : new RatedCourse($stats['ratingT'], $stats['slope'], $stats['par']),
        );

        $winners = [];
        foreach ($lines as $i => $line) {
            if ($ranked[$i]->rank === 1) {
                $winners[] = $line->player->name;
            }
        }

        return $winners;
    }
}
