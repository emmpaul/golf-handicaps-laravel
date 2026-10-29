<?php

namespace App\Domain\Imports\Exceptions;

use App\Domain\DomainException;
use App\Domain\Imports\ReadingFailure;

/**
 * The monthly AI ceiling leaves no room for another reading: nothing is queued.
 */
final class AiBudgetReached extends DomainException
{
    public function __construct()
    {
        parent::__construct(ReadingFailure::BUDGET_REACHED);
    }
}
