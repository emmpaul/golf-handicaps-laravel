<?php

namespace App\Domain\Courses\Actions;

use App\Domain\Courses\CourseRatingLookupTarget;
use App\Domain\Courses\CourseRatingStatus;
use App\Domain\Courses\Queries\CourseRounds;
use App\Models\CourseRating;

/**
 * Reads what the lookup job has to look up (design D5, step 1). A row that no longer exists or
 * that is no longer pending (already looked up by an earlier run, or confirmed since) gives
 * nothing: the job stops without reaching Simulator Golf Tour nor the AI.
 */
final class StartCourseRatingLookup
{
    public function __construct(private readonly CourseRounds $courseRounds) {}

    public function handle(string $courseRatingId): ?CourseRatingLookupTarget
    {
        $rating = CourseRating::query()->find($courseRatingId);
        if ($rating === null || $rating->status !== CourseRatingStatus::Pending) {
            return null;
        }

        return new CourseRatingLookupTarget(
            $rating->id,
            $rating->course_name,
            $rating->tees,
            $rating->sgt_course_id,
            $this->courseRounds->latestPar($rating->course_name, $rating->tees),
        );
    }
}
