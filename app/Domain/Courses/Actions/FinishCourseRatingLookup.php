<?php

namespace App\Domain\Courses\Actions;

use App\Domain\Courses\CourseRatingLookupOutcome;
use App\Domain\Courses\CourseRatingLookupTarget;
use App\Domain\Courses\CourseRatingStatus;
use App\Domain\Courses\Sgt\SgtCourse;
use App\Domain\Courses\Sgt\SgtTee;
use App\Models\CourseRating;
use Illuminate\Support\Facades\DB;

/**
 * Writes how a lookup ended (design D5, step 8), on a row still pending with the SGT course the
 * job read only: a row confirmed, or relaunched during the reading of a candidate, keeps what the
 * user did. What an earlier lookup left (note, candidates,
 * tees read, tee proposed) is cleared first. The confirmed rating, its slope and its source are
 * never touched: only a confirmation writes them.
 */
final class FinishCourseRatingLookup
{
    public function handle(CourseRatingLookupTarget $target, CourseRatingLookupOutcome $outcome): void
    {
        DB::transaction(function () use ($target, $outcome): void {
            $rating = CourseRating::query()->lockForUpdate()->find($target->id);
            if ($rating === null || $rating->status !== CourseRatingStatus::Pending) {
                return;
            }
            // Relaunched during the reading of a candidate: the row is pending again without SGT
            // course and a new job does the whole lookup; this outcome is no longer its own.
            if ($rating->sgt_course_id !== $target->sgtCourseId) {
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
