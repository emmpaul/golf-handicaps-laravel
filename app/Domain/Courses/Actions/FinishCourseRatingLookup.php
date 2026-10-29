<?php

namespace App\Domain\Courses\Actions;

use App\Domain\Courses\CourseRatingLookupOutcome;
use App\Domain\Courses\CourseRatingStatus;
use App\Domain\Courses\Sgt\SgtCourse;
use App\Domain\Courses\Sgt\SgtTee;
use App\Models\CourseRating;
use Illuminate\Support\Facades\DB;

/**
 * Writes how a lookup ended (design D5, step 8), on a row still pending only: a row confirmed or
 * relaunched meanwhile keeps what the user did. What an earlier lookup left (note, candidates,
 * tees read, tee proposed) is cleared first. The confirmed rating, its slope and its source are
 * never touched: only a confirmation writes them.
 */
final class FinishCourseRatingLookup
{
    public function handle(string $courseRatingId, CourseRatingLookupOutcome $outcome): void
    {
        DB::transaction(function () use ($courseRatingId, $outcome): void {
            $rating = CourseRating::query()->lockForUpdate()->find($courseRatingId);
            if ($rating === null || $rating->status !== CourseRatingStatus::Pending) {
                return;
            }

            $course = $outcome->course === null ? [] : [
                'sgt_course_id' => $outcome->course->id,
                'sgt_course_name' => $outcome->course->name,
                'sgt_course_location' => $outcome->course->location,
            ];

            $rating->update([
                'status' => $outcome->status,
                'note' => $outcome->note,
                'candidates' => $outcome->candidates === [] ? null : array_map(
                    fn (SgtCourse $candidate): array => ['id' => $candidate->id, 'name' => $candidate->name, 'location' => $candidate->location, 'par' => $candidate->par],
                    $outcome->candidates,
                ),
                'tees_read' => $outcome->teesRead === [] ? null : array_map(
                    fn (SgtTee $tee): array => ['name' => $tee->name, 'ratingT' => $tee->ratingT, 'slope' => $tee->slope],
                    $outcome->teesRead,
                ),
                'proposed_tee' => $outcome->proposedTee,
                ...$course,
            ]);
        });
    }
}
