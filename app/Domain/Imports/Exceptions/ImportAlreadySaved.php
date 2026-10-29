<?php

namespace App\Domain\Imports\Exceptions;

use App\Domain\DomainException;

/**
 * An import already saved as a round: it can be neither corrected, saved again, deleted nor
 * read again.
 */
final class ImportAlreadySaved extends DomainException
{
    public function __construct()
    {
        parent::__construct('Cette carte est déjà enregistrée');
    }
}
