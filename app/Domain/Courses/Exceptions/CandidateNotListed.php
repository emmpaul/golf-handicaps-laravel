<?php

namespace App\Domain\Courses\Exceptions;

use App\Domain\DomainException;

/**
 * The SGT course chosen is not one of the candidates of the row.
 */
final class CandidateNotListed extends DomainException
{
    public function __construct()
    {
        parent::__construct('Parcours SGT absent des candidats');
    }
}
