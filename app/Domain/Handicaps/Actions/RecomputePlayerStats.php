<?php

namespace App\Domain\Handicaps\Actions;

use App\Domain\Handicaps\CourseRating;
use App\Domain\Handicaps\RoundDifferential;
use App\Domain\Handicaps\Whs;
use App\Models\Player;
use App\Models\PlayerRoundStat;
use App\Models\Round;
use App\Models\RoundHole;
use App\Models\Score;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Replays the whole history of each player given and rewrites his rows of player_round_stats:
 * differential, index just before and just after each round, differentials retained in the
 * current index. Port of recomputePlayerStats of the Next.js application.
 *
 * Runs inside the transaction of the calling write (a savepoint) and locks the players first,
 * in the order of their id: two writes that touch the same player run one after the other, and
 * the second reads the history the first committed.
 */
final class RecomputePlayerStats
{
    /**
     * @param  iterable<string>  $playerIds  duplicates allowed
     */
    public function handle(iterable $playerIds): void
    {
        $ids = [];
        foreach ($playerIds as $playerId) {
            $ids[$playerId] = true;
        }
        $ids = array_keys($ids);
        sort($ids);

        if ($ids === []) {
            return;
        }

        DB::transaction(function () use ($ids): void {
            Player::query()->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->pluck('id');

            foreach ($ids as $playerId) {
                $this->recompute($playerId);
            }
        });
    }

    private function recompute(string $playerId): void
    {
        PlayerRoundStat::query()->where('player_id', $playerId)->delete();

        $rounds = $this->history($playerId);
        if ($rounds === []) {
            return;
        }

        $roundIds = array_column($rounds, 'id');
        $pars = RoundHole::query()->whereIn('round_id', $roundIds)->orderBy('hole')->get(['round_id', 'par'])
            ->groupBy('round_id')->map(fn ($holes): array => array_values($holes->pluck('par')->all()));
        $strokes = Score::query()->where('player_id', $playerId)->whereIn('round_id', $roundIds)->orderBy('hole')
            ->get(['round_id', 'strokes'])
            ->groupBy('round_id')->map(fn ($scores): array => array_values($scores->pluck('strokes')->all()));

        $played = [];
        $rows = [];
        foreach ($rounds as $round) {
            /** @var list<int> $roundPars */
            $roundPars = $pars[$round['id']];
            /** @var list<int> $roundStrokes */
            $roundStrokes = $strokes[$round['id']];

            // Without a typed rating, the par total and 113.
            $rating = $round['ratingT'] === null || $round['slope'] === null
                ? new CourseRating(array_sum($roundPars) * 10, 113)
                : new CourseRating($round['ratingT'], $round['slope']);
            $differentialT = Whs::differentialTenths($roundStrokes, $roundPars, $rating);

            $indexBeforeT = Whs::computeIndex($played)->indexT;
            $played[] = new RoundDifferential($round['playedOn'], $round['createdAt'], $differentialT);

            $rows[] = [
                'id' => (string) Str::uuid7(),
                'round_id' => $round['id'],
                'player_id' => $playerId,
                'gross' => array_sum($roundStrokes),
                'adjusted' => Whs::adjustedScore($roundStrokes, $roundPars),
                'differential_t' => $differentialT,
                'index_before_t' => $indexBeforeT,
                // At least one round played: the index exists.
                'index_after_t' => Whs::computeIndex($played)->indexT,
            ];
        }

        $counts = Whs::computeIndex($played)->counts;
        foreach ($rows as $i => $row) {
            $rows[$i]['counts_in_current'] = $counts[$i];
        }

        PlayerRoundStat::query()->insert($rows);
    }

    /**
     * The rounds of the player in the order of the index: date of play, date of saving, then
     * id; with the rating typed for their course and tees, without case nor surrounding spaces
     * (the expression of the unique index of course_ratings and of KnownCourses).
     *
     * @return list<array{id: string, playedOn: string, createdAt: string, ratingT: int|null, slope: int|null}>
     */
    private function history(string $playerId): array
    {
        return array_values(Round::query()
            ->join('round_players', 'round_players.round_id', '=', 'rounds.id')
            ->leftJoin('course_ratings', fn (JoinClause $join): JoinClause => $join
                ->whereRaw('lower(trim(course_ratings.course_name)) = lower(trim(rounds.course_name))')
                ->whereRaw('lower(trim(course_ratings.tees)) = lower(trim(rounds.tees))'))
            ->where('round_players.player_id', $playerId)
            ->orderBy('rounds.played_on')
            ->orderBy('rounds.created_at')
            ->orderBy('rounds.id')
            ->get(['rounds.id', 'rounds.played_on', 'rounds.created_at', 'course_ratings.rating_t', 'course_ratings.slope'])
            ->map(fn (Round $round): array => [
                'id' => $round->id,
                'playedOn' => $round->played_on->toDateString(),
                'createdAt' => $round->created_at?->format('Y-m-d H:i:s.u') ?? '',
                'ratingT' => $round->getAttribute('rating_t'),
                'slope' => $round->getAttribute('slope'),
            ])
            ->all());
    }
}
