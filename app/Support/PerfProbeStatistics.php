<?php

namespace App\Support;

/**
 * The arithmetic of golf:perf-probe (design D2), without the framework: median, 90th percentile
 * and minimum of the trials of a page, the reading of Server-Timing, and the cell of the report.
 */
final class PerfProbeStatistics
{
    /**
     * The median and the 90th percentile at the nearest rank (ceil(p × n), counted from 1) of
     * the sorted trials, and their minimum. The ranks pick a trial, never an average of two.
     *
     * @template T of int|float
     *
     * @param  list<T>  $trials
     * @return array{median: T, p90: T, min: T}|null null without any trial
     */
    public static function summarize(array $trials): ?array
    {
        if ($trials === []) {
            return null;
        }

        sort($trials);

        return [
            'median' => self::nearestRank($trials, 50),
            'p90' => self::nearestRank($trials, 90),
            'min' => $trials[0],
        ];
    }

    /**
     * The app and db-count measures of a Server-Timing header, in milliseconds and in queries.
     * A measure that the header does not carry is null.
     *
     * @return array{app: float|null, db-count: float|null}|null null without the header
     */
    public static function serverTiming(?string $header): ?array
    {
        if ($header === null || trim($header) === '') {
            return null;
        }

        $durations = [];

        foreach (explode(',', $header) as $metric) {
            if (preg_match('/^\s*([A-Za-z0-9_-]+)\s*;.*?\bdur=([0-9]+(?:\.[0-9]+)?)/', $metric, $match) === 1) {
                $durations[$match[1]] = (float) $match[2];
            }
        }

        return ['app' => $durations['app'] ?? null, 'db-count' => $durations['db-count'] ?? null];
    }

    /**
     * A cell of the report: the value rounded to the given decimals, or « — » without a value.
     */
    public static function display(int|float|null $value, int $decimals = 0): string
    {
        return $value === null ? '—' : number_format((float) $value, $decimals, '.', '');
    }

    /**
     * @template T of int|float
     *
     * @param  non-empty-list<T>  $sorted
     * @return T
     */
    private static function nearestRank(array $sorted, int $percent): int|float
    {
        // ceil(percent × n / 100) in integers: no rounding of 0.9 × n in floating point.
        $rank = max(1, intdiv($percent * count($sorted) + 99, 100));

        return $sorted[$rank - 1];
    }
}
