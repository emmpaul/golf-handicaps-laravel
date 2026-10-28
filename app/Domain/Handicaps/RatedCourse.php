<?php

namespace App\Domain\Handicaps;

/**
 * The rating of a course in tenths, its slope and its par total: what the course handicap
 * needs.
 */
final readonly class RatedCourse
{
    public function __construct(
        public int $ratingT,
        public int $slope,
        public int $par,
    ) {}
}
