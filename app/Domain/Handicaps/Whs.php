<?php

namespace App\Domain\Handicaps;

/**
 * The WHS-inspired computation of the Next.js application (src/domain/handicap.ts and the
 * course handicap of src/domain/strokes.ts), ported one for one: pure, without base nor
 * framework. Differentials and indexes are integer tenths (174 = 17,4), rounded as Next does
 * with floor(x + 0.5): 0,05 up, negative values included.
 */
final class Whs
{
    /** The highest a hole counts for the handicap: par + 4 (quadruple bogey). */
    private const int MAX_OVER_PAR = 4;

    /** The index looks at the 20 latest rounds at most. */
    private const int WINDOW = 20;

    /** The cap of the index: 54,0. */
    private const int MAX_INDEX_T = 540;

    public static function adjustedHoleScore(int $strokes, int $par): int
    {
        return min($strokes, $par + self::MAX_OVER_PAR);
    }

    /**
     * @param  list<int>  $strokes  the 18 gross strokes, hole 1 first
     * @param  list<int>  $pars  the 18 pars, hole 1 first
     */
    public static function adjustedScore(array $strokes, array $pars): int
    {
        $sum = 0;
        foreach ($strokes as $hole => $holeStrokes) {
            $sum += self::adjustedHoleScore($holeStrokes, $pars[$hole]);
        }

        return $sum;
    }

    /**
     * The differential in integer tenths: 113 / slope × (adjusted score − course rating),
     * rounded to the nearest, 0,05 up.
     *
     * @param  list<int>  $strokes  the 18 gross strokes, hole 1 first
     * @param  list<int>  $pars  the 18 pars, hole 1 first
     */
    public static function differentialTenths(array $strokes, array $pars, CourseRating $rating): int
    {
        return self::roundHalfUp(113 * (self::adjustedScore($strokes, $pars) * 10 - $rating->ratingT) / $rating->slope);
    }

    /**
     * The index of a player on his 20 latest rounds, sorted by date of play then by date of
     * saving; rounds equal on both keep the order given (usort is stable, as Array.sort).
     *
     * @param  list<RoundDifferential>  $rounds
     */
    public static function computeIndex(array $rounds): HandicapIndex
    {
        $recent = array_keys($rounds);
        usort($recent, fn (int $a, int $b): int => [$rounds[$a]->playedOn, $rounds[$a]->createdAt]
            <=> [$rounds[$b]->playedOn, $rounds[$b]->createdAt]);
        $recent = array_slice($recent, -self::WINDOW);

        $n = count($recent);
        if ($n === 0) {
            return new HandicapIndex(null, false, []);
        }

        $provisional = $n < 3;
        [$best, $adjustmentT] = $provisional ? [$n, 0] : self::whsRule($n);

        $retained = $recent;
        usort($retained, fn (int $a, int $b): int => $rounds[$a]->differentialT <=> $rounds[$b]->differentialT);
        $retained = array_slice($retained, 0, $best);

        $sumT = 0;
        foreach ($retained as $position) {
            $sumT += $rounds[$position]->differentialT;
        }
        $counts = array_map(fn (int $position): bool => in_array($position, $retained, true), array_keys($rounds));

        return new HandicapIndex(
            min(self::roundHalfUp($sumT / $best + $adjustmentT), self::MAX_INDEX_T),
            $provisional,
            $counts,
        );
    }

    /**
     * The playing handicap: the index (in tenths) rounded to the integer, 0,5 up.
     */
    public static function playingHandicap(int $indexT): int
    {
        return (int) floor(($indexT + 5) / 10);
    }

    /**
     * The course handicap: index × slope / 113 + (course rating − par), rounded to the integer,
     * 0,5 up. Without a rating, the playing handicap: the fallback (par × 10, 113) gives the
     * same.
     */
    public static function courseHandicap(int $indexT, ?RatedCourse $course): int
    {
        if ($course === null) {
            return self::playingHandicap($indexT);
        }

        return self::roundHalfUp(($indexT * $course->slope / 113 + $course->ratingT - $course->par * 10) / 10);
    }

    /**
     * The WHS table: for n rounds (3 to 20), the number of best differentials retained and the
     * adjustment in tenths.
     *
     * @return array{int, int}
     */
    private static function whsRule(int $n): array
    {
        return match (true) {
            $n <= 3 => [1, -20],
            $n === 4 => [1, -10],
            $n === 5 => [1, 0],
            $n === 6 => [2, -10],
            $n <= 8 => [2, 0],
            $n <= 11 => [3, 0],
            $n <= 14 => [4, 0],
            $n <= 16 => [5, 0],
            $n <= 18 => [6, 0],
            $n === 19 => [7, 0],
            default => [8, 0],
        };
    }

    /**
     * Next's Math.floor(x + 0.5): to the nearest integer, 0,5 up, negative values included.
     */
    private static function roundHalfUp(float|int $value): int
    {
        return (int) floor($value + 0.5);
    }
}
