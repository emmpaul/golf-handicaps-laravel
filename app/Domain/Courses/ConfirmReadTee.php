<?php

namespace App\Domain\Courses;

use App\Domain\Courses\Queries\CourseRounds;
use App\Domain\DomainException;
use App\Domain\Handicaps\Actions\RecomputePlayerStats;
use App\Models\CourseRating;
use Illuminate\Support\Facades\DB;

/**
 * Confirms the rating and slope of a tee read on the SGT card, found by its exact name, then
 * recomputes the history of every player who has a round on the pair (design D7). The note
 * stays as the lookup wrote it (decision Q18). Shared by the confirmation of the proposal and
 * the choice of a tee.
 */
final class ConfirmReadTee
{
    public function __construct(
        private readonly CourseRounds $courseRounds,
        private readonly RecomputePlayerStats $recomputePlayerStats,
    ) {}

    /**
     * @param  string|null  $teeName  the tee to retain, or null for the tee proposed on the row
     *
     * @throws DomainException $refusal when no tee read has that name, or when a relaunched
     *                         lookup runs: the tees on the row are those of the earlier lookup
     */
    public function handle(CourseRating $courseRating, ?string $teeName, DomainException $refusal): void
    {
        DB::transaction(function () use ($courseRating, $teeName, $refusal): void {
            $rating = CourseRating::query()->lockForUpdate()->findOrFail($courseRating->id);
            if ($rating->status === CourseRatingStatus::Pending) {
                throw $refusal;
            }
            $name = $teeName ?? $rating->proposed_tee;
            $tee = collect($rating->tees_read ?? [])->first(fn (array $read): bool => $name !== null && $read['name'] === $name);
            if ($tee === null) {
                throw $refusal;
            }

            $rating->update([
                'rating_t' => $tee['ratingT'],
                'slope' => $tee['slope'],
                'source' => 'sgt',
                'status' => CourseRatingStatus::Confirmed,
                'proposed_tee' => $tee['name'],
            ]);

            $this->recomputePlayerStats->handle($this->courseRounds->playerIds($rating->course_name, $rating->tees));
        });
    }
}
