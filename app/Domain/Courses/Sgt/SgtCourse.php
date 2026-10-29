<?php

namespace App\Domain\Courses\Sgt;

/**
 * A course of the Simulator Golf Tour list: its id gives the links of its scorecard.
 */
final readonly class SgtCourse
{
    public function __construct(
        public int $id,
        public string $name,
        public string $location,
        public int $par,
    ) {}
}
