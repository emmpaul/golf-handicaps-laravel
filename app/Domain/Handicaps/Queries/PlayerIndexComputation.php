<?php

namespace App\Domain\Handicaps\Queries;

use App\Domain\Handicaps\Whs;
use App\Models\PlayerRoundStat;
use App\Models\RoundHole;
use App\Models\Score;
use Illuminate\Database\Query\JoinClause;
use stdClass;

/**
 * The facts of the page « Calcul de l'index » for one player (design D2 of
 * index-computation-page): the player's 20 latest rounds in the order of the index, from the
 * latest, with the rating and slope the computation used; the latest capped hole among them; the
 * mean of the differentials retained and the adjustment of the WHS rule. Reads only what the
 * history saved, in two queries: the current index stays the one of PlayerStandings.
 */
final class PlayerIndexComputation
{
    /** The index looks at the 20 latest rounds. */
    private const int WINDOW = 20;

    /**
     * @return array{
     *     rounds: list<array{roundId: string, playedOn: string, courseName: string, gross: int, adjusted: int, parTotal: int, ratingT: int|null, slope: int|null, differentialT: int, countsInCurrent: bool}>,
     *     cappedHole: array{hole: int, par: int, strokes: int}|null,
     *     meanT: int|null,
     *     adjustmentT: int,
     * }
     */
    public function get(string $playerId): array
    {
        $rounds = $this->rounds($playerId);
        $retained = array_column(array_filter($rounds, fn (array $round): bool => $round['countsInCurrent']), 'differentialT');
        [, $adjustmentT] = $rounds === [] ? [0, 0] : Whs::retainedAndAdjustment(count($rounds));

        return [
            'rounds' => $rounds,
            'cappedHole' => $rounds === [] ? null : $this->cappedHole($playerId, array_column($rounds, 'roundId')),
            'meanT' => $retained === [] ? null : Whs::roundHalfUp(array_sum($retained) / count($retained)),
            'adjustmentT' => $adjustmentT,
        ];
    }

    /**
     * The 20 latest rounds by date of play, date of saving then id, from the latest. The rating
     * join is the one of RecomputePlayerStats::history() (design D3): the rating typed for the
     * course and tees, without case nor surrounding spaces.
     *
     * @return list<array{roundId: string, playedOn: string, courseName: string, gross: int, adjusted: int, parTotal: int, ratingT: int|null, slope: int|null, differentialT: int, countsInCurrent: bool}>
     */
    private function rounds(string $playerId): array
    {
        $parTotal = RoundHole::query()->toBase()
            ->selectRaw('sum(round_holes.par)')
            ->whereColumn('round_holes.round_id', 'rounds.id');

        return array_values(PlayerRoundStat::query()
            ->toBase()
            ->join('rounds', 'rounds.id', '=', 'player_round_stats.round_id')
            ->leftJoin('course_ratings', fn (JoinClause $join): JoinClause => $join
                ->whereRaw('lower(trim(course_ratings.course_name)) = lower(trim(rounds.course_name))')
                ->whereRaw('lower(trim(course_ratings.tees)) = lower(trim(rounds.tees))'))
            ->where('player_round_stats.player_id', $playerId)
            ->select([
                'rounds.id as round_id',
                'rounds.played_on',
                'rounds.course_name',
                'player_round_stats.gross',
                'player_round_stats.adjusted',
                'course_ratings.rating_t',
                'course_ratings.slope',
                'player_round_stats.differential_t',
                'player_round_stats.counts_in_current',
            ])
            ->selectSub($parTotal, 'par_total')
            ->orderByDesc('rounds.played_on')
            ->orderByDesc('rounds.created_at')
            ->orderByDesc('rounds.id')
            ->limit(self::WINDOW)
            ->get()
            ->map(fn (stdClass $row): array => [
                'roundId' => (string) $row->round_id,
                'playedOn' => (string) $row->played_on,
                'courseName' => (string) $row->course_name,
                'gross' => (int) $row->gross,
                'adjusted' => (int) $row->adjusted,
                'parTotal' => (int) $row->par_total,
                'ratingT' => $row->rating_t === null ? null : (int) $row->rating_t,
                'slope' => $row->slope === null ? null : (int) $row->slope,
                'differentialT' => (int) $row->differential_t,
                'countsInCurrent' => (bool) $row->counts_in_current,
            ])
            ->all());
    }

    /**
     * The latest hole above par + 4 among $roundIds: from the latest round that has one, the
     * largest overage, then the lowest hole number.
     *
     * @param  list<string>  $roundIds
     * @return array{hole: int, par: int, strokes: int}|null
     */
    private function cappedHole(string $playerId, array $roundIds): ?array
    {
        $row = Score::query()
            ->toBase()
            ->join('round_holes', fn (JoinClause $join): JoinClause => $join
                ->on('round_holes.round_id', '=', 'scores.round_id')
                ->on('round_holes.hole', '=', 'scores.hole'))
            ->join('rounds', 'rounds.id', '=', 'scores.round_id')
            ->where('scores.player_id', $playerId)
            ->whereIn('scores.round_id', $roundIds)
            ->whereRaw('scores.strokes > round_holes.par + ?', [Whs::MAX_OVER_PAR])
            ->select(['scores.hole', 'round_holes.par', 'scores.strokes'])
            ->orderByDesc('rounds.played_on')
            ->orderByDesc('rounds.created_at')
            ->orderByDesc('rounds.id')
            ->orderByRaw('scores.strokes - round_holes.par desc')
            ->orderBy('scores.hole')
            ->first();

        return $row === null ? null : [
            'hole' => (int) $row->hole,
            'par' => (int) $row->par,
            'strokes' => (int) $row->strokes,
        ];
    }
}
