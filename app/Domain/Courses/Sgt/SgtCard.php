<?php

namespace App\Domain\Courses\Sgt;

/**
 * The par total and the tees read on an SGT scorecard, in the order of the card.
 */
final readonly class SgtCard
{
    /**
     * @param  list<SgtTee>  $tees
     */
    public function __construct(
        public int $par,
        public array $tees,
    ) {}
}
