<?php

namespace App\Domain\Courses\Sgt;

/**
 * The links of an SGT course shown on the courses page (design D2).
 */
final class SgtLinks
{
    /**
     * The scorecard image of the course, where the rating and slope of each tee are read.
     */
    public static function scorecardUrl(int $sgtCourseId): string
    {
        return "https://simulatorgolftour.com/public/assets/courseImages/scorecards/scorecard_{$sgtCourseId}.jpg";
    }

    /**
     * The The Course View page of the course, slug of its SGT name (« Diamond Hawk Golf Course
     * & Pub » → `diamond-hawk-golf-course-pub`). A help: the page may not exist.
     */
    public static function courseViewUrl(string $sgtName): string
    {
        $slug = trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($sgtName)), '-');

        return "https://thecourseview.com/courses/{$slug}";
    }
}
