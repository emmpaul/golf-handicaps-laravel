<?php

namespace App\Domain\Courses;

use App\Domain\Courses\Sgt\SgtCourse;
use App\Domain\Courses\Sgt\SgtTee;

/**
 * How a lookup ended (design D5): the status, the note shown, and what was found on Simulator
 * Golf Tour. A null course keeps the SGT course already written on the row.
 */
final readonly class CourseRatingLookupOutcome
{
    /**
     * @param  list<SgtCourse>  $candidates
     * @param  list<SgtTee>  $teesRead
     */
    private function __construct(
        public CourseRatingStatus $status,
        public ?string $note = null,
        public ?SgtCourse $course = null,
        public array $candidates = [],
        public array $teesRead = [],
        public ?string $proposedTee = null,
    ) {}

    /**
     * Left to a rating typed by hand, for the reason of the note.
     */
    public static function manual(string $note, ?SgtCourse $course = null): self
    {
        return new self(CourseRatingStatus::Manual, $note, $course);
    }

    public static function notFound(): self
    {
        return new self(CourseRatingStatus::NotFound);
    }

    /**
     * @param  non-empty-list<SgtCourse>  $candidates
     */
    public static function chooseCourse(array $candidates): self
    {
        return new self(CourseRatingStatus::ChooseCourse, candidates: $candidates);
    }

    /**
     * @param  list<SgtTee>  $teesRead
     */
    public static function chooseTee(?SgtCourse $course, array $teesRead): self
    {
        return new self(CourseRatingStatus::ChooseTee, course: $course, teesRead: $teesRead);
    }

    /**
     * @param  list<SgtTee>  $teesRead
     */
    public static function proposed(?SgtCourse $course, array $teesRead, string $proposedTee): self
    {
        return new self(CourseRatingStatus::Proposed, course: $course, teesRead: $teesRead, proposedTee: $proposedTee);
    }

    public static function failed(string $note): self
    {
        return new self(CourseRatingStatus::Failed, $note);
    }
}
