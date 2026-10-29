<?php

namespace App\Domain\Courses;

/**
 * What the lookup job looks up for a pending row (design D5): its pair, the SGT course already
 * chosen among the candidates, and the par total of the most recent round of the pair, null
 * when no round is left on it.
 */
final readonly class CourseRatingLookupTarget
{
    public function __construct(
        public string $id,
        public string $courseName,
        public string $tees,
        public ?int $sgtCourseId,
        public ?int $par,
    ) {}
}
