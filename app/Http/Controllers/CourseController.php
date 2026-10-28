<?php

namespace App\Http\Controllers;

use App\Domain\Courses\Queries\CourseOverview;
use Inertia\Inertia;
use Inertia\Response;

class CourseController extends Controller
{
    /**
     * The known courses with their par and their rating.
     */
    public function index(CourseOverview $overview): Response
    {
        return Inertia::render('courses/index', ['courses' => $overview->get()]);
    }
}
