<?php

namespace App\Domain\Handicaps;

/**
 * The index of a player in tenths, null without any round; provisional under 3 rounds.
 * `counts` follows the order of the rounds given to Whs::computeIndex() and marks the
 * differentials retained in the index.
 */
final readonly class HandicapIndex
{
    /**
     * @param  list<bool>  $counts
     */
    public function __construct(
        public ?int $indexT,
        public bool $provisional,
        public array $counts,
    ) {}
}
