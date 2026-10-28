<?php

namespace App\Domain\Handicaps;

/**
 * A round of a player as the index sees it: when it was played and saved, and its differential
 * in tenths.
 */
final readonly class RoundDifferential
{
    /**
     * @param  string  $playedOn  `Y-m-d`
     * @param  string  $createdAt  a timestamp that sorts as text, in a single time zone
     */
    public function __construct(
        public string $playedOn,
        public string $createdAt,
        public int $differentialT,
    ) {}
}
