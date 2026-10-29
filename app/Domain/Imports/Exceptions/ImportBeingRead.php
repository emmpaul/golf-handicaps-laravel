<?php

namespace App\Domain\Imports\Exceptions;

use App\Domain\DomainException;

/**
 * An import queued or being read: it can be neither corrected, deleted nor read again.
 */
final class ImportBeingRead extends DomainException
{
    public function __construct()
    {
        parent::__construct('Lecture en cours');
    }
}
