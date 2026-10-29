<?php

namespace App\Domain\Courses\Sgt;

/**
 * A tee read on an SGT scorecard, its rating in tenths (71.4 → 714).
 */
final readonly class SgtTee
{
    public function __construct(
        public string $name,
        public int $ratingT,
        public int $slope,
    ) {}
}
