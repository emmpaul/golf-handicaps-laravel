<?php

namespace App\Domain\Courses\Sgt;

/**
 * The SGT course of a round: one retained, candidates to choose from, or none.
 */
final readonly class SgtMatch
{
    /**
     * @param  list<SgtCourse>  $candidates
     */
    private function __construct(
        public ?SgtCourse $course,
        public array $candidates,
    ) {}

    public static function retained(SgtCourse $course): self
    {
        return new self($course, []);
    }

    /**
     * @param  non-empty-list<SgtCourse>  $candidates
     */
    public static function candidates(array $candidates): self
    {
        return new self(null, $candidates);
    }

    public static function none(): self
    {
        return new self(null, []);
    }
}
