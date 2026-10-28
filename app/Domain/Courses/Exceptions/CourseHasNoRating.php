<?php

namespace App\Domain\Courses\Exceptions;

use App\Domain\DomainException;

/**
 * The course and tees have rounds but no typed rating to remove.
 */
final class CourseHasNoRating extends DomainException
{
    public function __construct()
    {
        parent::__construct('Parcours déjà sans rating');
    }
}
