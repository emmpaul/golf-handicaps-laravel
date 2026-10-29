<?php

namespace App\Domain\Courses\Exceptions;

use App\Domain\DomainException;

/**
 * The tee chosen is not one of the tees read on the SGT card, by its exact name.
 */
final class TeeNotRead extends DomainException
{
    public function __construct()
    {
        parent::__construct('Tee absent de la carte SGT');
    }
}
