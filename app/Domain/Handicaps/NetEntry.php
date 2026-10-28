<?php

namespace App\Domain\Handicaps;

/**
 * A line of a round for its net leaderboard: the gross and the index of the player just before
 * the round, null for his first round.
 */
final readonly class NetEntry
{
    public function __construct(
        public int $gross,
        public ?int $indexBeforeT,
    ) {}
}
