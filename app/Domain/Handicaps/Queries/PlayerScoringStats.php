<?php

namespace App\Domain\Handicaps\Queries;

use App\Domain\Handicaps\ScoreTint;
use App\Models\Score;

/**
 * The STATS block of the Handicaps page for one player (spec handicap-index, « Statistiques de
 * jeu d'un joueur », « Répartition des trous », « Moyennes par par »; design D13 of
 * visual-redesign): every saved round of the player, all courses, against the par of each hole
 * saved with its round. One query of aggregates; the roundings are done here in integers on the
 * exact values, half up.
 */
final class PlayerScoringStats
{
    /** The pars that get an average, in their order on the page. */
    private const array PARS = [3, 4, 5];

    /**
     * @return array{rounds: int, holes: int, averageGrossT: int, bestGross: int, parOrBetterPercent: int, classes: list<int>, classPercents: list<int>, pars: list<array{par: int, holes: int, averageT: int, deltaT: int, tint: ScoreTint}>}|null
     */
    public function get(string $playerId): ?array
    {
        $holes = Score::query()
            ->toBase()
            ->join('round_holes', function ($join): void {
                $join->on('round_holes.round_id', '=', 'scores.round_id')
                    ->on('round_holes.hole', '=', 'scores.hole');
            })
            ->where('scores.player_id', $playerId)
            ->select(['scores.round_id', 'scores.strokes', 'round_holes.par']);

        $grossByRound = Score::query()
            ->toBase()
            ->where('player_id', $playerId)
            ->groupBy('round_id')
            ->selectRaw('sum(strokes) as gross');

        $row = Score::query()
            ->toBase()
            ->fromSub($holes, 'h')
            ->selectRaw('count(distinct round_id) as rounds')
            ->selectRaw('count(*) as holes')
            ->selectRaw('coalesce(sum(strokes), 0) as strokes')
            ->selectSub(fn ($query) => $query->fromSub($grossByRound, 'g')->selectRaw('min(gross)'), 'best_gross')
            ->selectRaw('count(*) filter (where strokes - par <= -2) as eagles')
            ->selectRaw('count(*) filter (where strokes - par = -1) as birdies')
            ->selectRaw('count(*) filter (where strokes - par = 0) as pars')
            ->selectRaw('count(*) filter (where strokes - par = 1) as bogeys')
            ->selectRaw('count(*) filter (where strokes - par >= 2) as doubles')
            ->selectRaw(implode(', ', array_map(
                fn (int $par): string => "count(*) filter (where par = {$par}) as par{$par}_holes, coalesce(sum(strokes) filter (where par = {$par}), 0) as par{$par}_strokes",
                self::PARS,
            )))
            ->first();

        if ($row === null) {
            return null;
        }

        $rounds = (int) $row->rounds;
        if ($rounds === 0) {
            return null;
        }

        $holeCount = (int) $row->holes;
        $classes = [(int) $row->eagles, (int) $row->birdies, (int) $row->pars, (int) $row->bogeys, (int) $row->doubles];

        return [
            'rounds' => $rounds,
            'holes' => $holeCount,
            'averageGrossT' => self::rounded(10 * (int) $row->strokes, $rounds),
            'bestGross' => (int) $row->best_gross,
            'parOrBetterPercent' => self::rounded(100 * ($classes[0] + $classes[1] + $classes[2]), $holeCount),
            'classes' => $classes,
            'classPercents' => array_map(fn (int $count): int => self::rounded(100 * $count, $holeCount), $classes),
            'pars' => array_values(array_filter(array_map(
                fn (int $par): ?array => self::par($par, (int) $row->{"par{$par}_holes"}, (int) $row->{"par{$par}_strokes"}),
                self::PARS,
            ))),
        ];
    }

    /**
     * The average of $sum strokes on $holes holes of par $par in tenths, the signed delta of that
     * rounded average to the par in tenths and its tint; null when the par was never played.
     *
     * @return array{par: int, holes: int, averageT: int, deltaT: int, tint: ScoreTint}|null
     */
    private static function par(int $par, int $holes, int $sum): ?array
    {
        if ($holes === 0) {
            return null;
        }

        $averageT = self::rounded(10 * $sum, $holes);

        return [
            'par' => $par,
            'holes' => $holes,
            'averageT' => $averageT,
            // The delta of the average shown, so that « 2,8 » on a par 3 reads « −0,2 » (DO40);
            // the tint stays decided on the exact values.
            'deltaT' => $averageT - 10 * $par,
            'tint' => ScoreTint::of($sum, $par, $holes),
        ];
    }

    /**
     * $numerator / $denominator to the nearest integer, half up, for a non-negative numerator.
     */
    private static function rounded(int $numerator, int $denominator): int
    {
        return intdiv(2 * $numerator + $denominator, 2 * $denominator);
    }
}
