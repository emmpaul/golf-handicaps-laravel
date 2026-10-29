<?php

namespace App\Domain\Courses\Actions;

use App\Domain\Courses\RequeueCourseRatingLookup;
use App\Models\CourseRating;

/**
 * Relaunches the whole lookup of a row, whatever its state, a pending one included (decision
 * Q6): the SGT course retained is forgotten, the confirmed rating stays applied until a new
 * confirmation (spec course-ratings, « Relance de la recherche »; design D7). A second job on a
 * row no longer pending does nothing.
 */
final class RelaunchCourseRatingLookup
{
    public function __construct(private readonly RequeueCourseRatingLookup $requeue) {}

    public function handle(CourseRating $courseRating): void
    {
        $this->requeue->handle($courseRating, fn (): null => null);
    }
}
