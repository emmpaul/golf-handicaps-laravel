<?php

namespace App\Models;

use App\Domain\Courses\CourseRatingStatus;
use Database\Factories\CourseRatingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * The rating of a course and tees and its lookup on Simulator Golf Tour; one per pair, whatever
 * the case and the surrounding spaces (index on lower(trim(...))). Only the confirmed rating and
 * slope count, for every round of the pair, past and future.
 *
 * @property string $id
 * @property string $course_name
 * @property string $tees
 * @property int|null $rating_t confirmed, in tenths: 714 = 71,4
 * @property int|null $slope confirmed
 * @property string|null $source sgt or manual, set with the confirmed values
 * @property CourseRatingStatus $status
 * @property int|null $sgt_course_id the SGT course retained
 * @property string|null $sgt_course_name
 * @property string|null $sgt_course_location
 * @property list<array{id: int, name: string, location: string, par: int}>|null $candidates
 * @property list<array{name: string, ratingT: int, slope: int}>|null $tees_read read on the SGT card
 * @property string|null $proposed_tee the name of the tee of tees_read proposed or retained
 * @property string|null $note why the lookup ended there
 */
#[Fillable([
    'course_name', 'tees', 'rating_t', 'slope', 'source', 'status',
    'sgt_course_id', 'sgt_course_name', 'sgt_course_location',
    'candidates', 'tees_read', 'proposed_tee', 'note',
])]
class CourseRating extends Model
{
    /** @use HasFactory<CourseRatingFactory> */
    use HasFactory, HasUuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => CourseRatingStatus::class,
            'candidates' => 'array',
            'tees_read' => 'array',
        ];
    }
}
