<?php

namespace App\Domain\Rounds;

/**
 * A coherence problem of a round card and the cells it highlights.
 *
 * Cell keys, shared with resources/js/lib/round-checks.ts: `courseName`, `tees`, `playedOn`;
 * `<row>:<hole 1..18>` with `<row>` = `par`, `strokeIndex`, `distance` or `player<n>`
 * (n = position of the line, from 0); `player<n>:player` for the player chosen on a line.
 */
final readonly class Problem
{
    /**
     * @param  list<string>  $cells
     */
    public function __construct(
        public string $message,
        public array $cells,
    ) {}
}
