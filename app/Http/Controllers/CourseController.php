<?php

namespace App\Http\Controllers;

use App\Domain\Courses\CourseRatingStatus;
use App\Domain\Courses\Queries\CourseOverview;
use Inertia\Inertia;
use Inertia\Response;

class CourseController extends Controller
{
    /**
     * The known courses with their par, their rating and the state of its lookup; lookupPending
     * tells the page to read them again while a lookup runs (design D8 of course-rating-sgt).
     */
    public function index(CourseOverview $overview): Response
    {
        $courses = $overview->get();

        return Inertia::render('courses/index', [
            'courses' => $courses,
            'lookupPending' => in_array(CourseRatingStatus::Pending->value, array_column($courses, 'status'), true),
        ]);
    }
}
