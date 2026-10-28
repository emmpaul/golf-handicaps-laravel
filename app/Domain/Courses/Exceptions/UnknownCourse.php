<?php

namespace App\Domain\Courses\Exceptions;

use App\Domain\DomainException;

/**
 * No round is saved on this course and these tees, whatever the case.
 */
final class UnknownCourse extends DomainException
{
    public function __construct()
    {
        parent::__construct('Parcours introuvable');
    }
}
