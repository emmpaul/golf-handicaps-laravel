<?php

namespace App\Domain\Handicaps;

/**
 * A line of the net leaderboard: without an index before the round, no playing handicap, no net
 * and no rank.
 */
final readonly class RankedNet
{
    public function __construct(
        public int $gross,
        public ?int $indexBeforeT,
        public ?int $playingHandicap,
        public ?int $net,
        public ?int $rank,
    ) {}
}
