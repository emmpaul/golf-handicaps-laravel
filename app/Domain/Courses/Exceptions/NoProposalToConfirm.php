<?php

namespace App\Domain\Courses\Exceptions;

use App\Domain\DomainException;

/**
 * The row has no tee proposed among the tees read on the SGT card.
 */
final class NoProposalToConfirm extends DomainException
{
    public function __construct()
    {
        parent::__construct('Aucune proposition à confirmer');
    }
}
