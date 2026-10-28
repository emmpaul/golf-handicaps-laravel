<?php

namespace App\Http\Controllers;

use App\Domain\Courses\Actions\RemoveCourseRating;
use App\Domain\Courses\Actions\SaveCourseRating;
use App\Http\Requests\RemoveCourseRatingRequest;
use App\Http\Requests\SaveCourseRatingRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * The rating of a course and tees, addressed by its pair in the body: a course without rating
 * has no row, hence no id (design D6).
 */
class CourseRatingController extends Controller
{
    /**
     * Type or correct the rating, recompute the players of the course, then go back.
     */
    public function update(SaveCourseRatingRequest $request, SaveCourseRating $saveCourseRating): RedirectResponse
    {
        $saveCourseRating->handle(
            $request->string('courseName')->value(),
            $request->string('tees')->value(),
            $request->ratingT(),
            $request->slope(),
        );

        return Inertia::flash('toast', ['type' => 'success', 'message' => 'Rating enregistrée.'])->back();
    }

    /**
     * Remove the rating, recompute the players of the course on the par and 113, then go back.
     */
    public function destroy(RemoveCourseRatingRequest $request, RemoveCourseRating $removeCourseRating): RedirectResponse
    {
        $removeCourseRating->handle($request->string('courseName')->value(), $request->string('tees')->value());

        return Inertia::flash('toast', ['type' => 'success', 'message' => 'Rating retirée.'])->back();
    }
}
