<?php

namespace App\Support;

/**
 * The SQL queries of the current request: how many ran and their total time in milliseconds.
 *
 * Bound with scoped() (design D1): Octane forgets it when a request comes in, and the classic
 * SAPI starts each request in a new process, so it never adds up the queries of an earlier
 * request. Fed by the DB::listen of AppServiceProvider, read by AddServerTiming.
 */
final class QueryClock
{
    private int $count = 0;

    private float $milliseconds = 0.0;

    /**
     * Count one more query that took the given time.
     */
    public function record(float $milliseconds): void
    {
        $this->count++;
        $this->milliseconds += $milliseconds;
    }

    public function count(): int
    {
        return $this->count;
    }

    public function milliseconds(): float
    {
        return $this->milliseconds;
    }
}
