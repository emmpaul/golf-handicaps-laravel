<?php

namespace App\Domain\Players\Exceptions;

use App\Domain\DomainException;

/**
 * Another player already has this name, whatever the case.
 */
final class DuplicatePlayer extends DomainException
{
    public function __construct()
    {
        parent::__construct('Ce joueur existe déjà');
    }
}
