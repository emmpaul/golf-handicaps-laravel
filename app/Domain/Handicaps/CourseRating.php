<?php

namespace App\Domain\Handicaps;

/**
 * The course rating of a course and tees, in tenths (714 = 71,4), and its slope. Without a
 * typed rating, the par total × 10 and 113.
 */
final readonly class CourseRating
{
    public function __construct(
        public int $ratingT,
        public int $slope,
    ) {}
}
