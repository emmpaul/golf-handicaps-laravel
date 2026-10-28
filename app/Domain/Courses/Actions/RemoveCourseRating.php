<?php

namespace App\Domain\Courses\Actions;

use App\Domain\Courses\Exceptions\CourseHasNoRating;
use App\Domain\Courses\Exceptions\UnknownCourse;
use App\Domain\Courses\Queries\CourseRounds;
use App\Domain\Handicaps\Actions\RecomputePlayerStats;
use App\Models\CourseRating;
use Illuminate\Support\Facades\DB;

/**
 * Removes the rating of a course and tees, whatever the case and the surrounding spaces; the
 * players who have a round there are recomputed on the par total and 113 (design D6).
 */
final class RemoveCourseRating
{
    public function __construct(
        private readonly CourseRounds $courseRounds,
        private readonly RecomputePlayerStats $recomputePlayerStats,
    ) {}

    /**
     * @throws UnknownCourse when no round is saved on the course and tees, whatever the case
     * @throws CourseHasNoRating when the course and tees have no rating
     */
    public function handle(string $courseName, string $tees): void
    {
        DB::transaction(function () use ($courseName, $tees): void {
            if (! $this->courseRounds->exist($courseName, $tees)) {
                throw new UnknownCourse;
            }

            $deleted = CourseRating::query()
                ->whereRaw('lower(trim(course_name)) = lower(trim(?))', [$courseName])
                ->whereRaw('lower(trim(tees)) = lower(trim(?))', [$tees])
                ->delete();

            if ($deleted === 0) {
                throw new CourseHasNoRating;
            }

            $this->recomputePlayerStats->handle($this->courseRounds->playerIds($courseName, $tees));
        });
    }
}
