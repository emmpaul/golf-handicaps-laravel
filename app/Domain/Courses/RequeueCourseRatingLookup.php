<?php

namespace App\Domain\Courses;

use App\Jobs\LookUpCourseRating;
use App\Models\CourseRating;
use Closure;
use Illuminate\Support\Facades\DB;

/**
 * Puts a row back in lookup with the SGT course given, or none, and queues its job once the
 * transaction is committed (design D7). The confirmed rating, its slope and its source stay:
 * only a new confirmation changes them. What the earlier lookup read stays until the job ends.
 */
final class RequeueCourseRatingLookup
{
    /**
     * $sgtCourse reads, on the row locked, the SGT course to look up, or null to match the names
     * again; it may refuse by throwing.
     *
     * @param  Closure(CourseRating): (array{id: int, name: string, location: string}|null)  $sgtCourse
     */
    public function handle(CourseRating $courseRating, Closure $sgtCourse): void
    {
        DB::transaction(function () use ($courseRating, $sgtCourse): void {
            $rating = CourseRating::query()->lockForUpdate()->findOrFail($courseRating->id);
            $course = $sgtCourse($rating);

            $rating->update([
                'status' => CourseRatingStatus::Pending,
                'note' => null,
                'sgt_course_id' => $course['id'] ?? null,
                'sgt_course_name' => $course['name'] ?? null,
                'sgt_course_location' => $course['location'] ?? null,
            ]);

            LookUpCourseRating::dispatch($rating->id)->afterCommit();
        });
    }
}
