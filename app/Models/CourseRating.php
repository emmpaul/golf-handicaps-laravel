<?php

namespace App\Models;

use Database\Factories\CourseRatingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * The course rating and slope typed for a course and tees; one per pair, whatever the case and
 * the surrounding spaces (index on lower(trim(...))). It applies to every round of the pair,
 * past and future.
 *
 * @property string $id
 * @property string $course_name
 * @property string $tees
 * @property int $rating_t in tenths: 714 = 71,4
 * @property int $slope
 */
#[Fillable(['course_name', 'tees', 'rating_t', 'slope'])]
class CourseRating extends Model
{
    /** @use HasFactory<CourseRatingFactory> */
    use HasFactory, HasUuids;
}
