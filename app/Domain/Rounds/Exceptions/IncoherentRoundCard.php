<?php

namespace App\Domain\Rounds\Exceptions;

use App\Domain\DomainException;
use App\Domain\Rounds\Problem;
use App\Domain\Rounds\RoundCardChecks;

/**
 * A round card that fails the coherence checks: nothing is written. The message counts the
 * problems, « 1 problème à corriger ».
 */
final class IncoherentRoundCard extends DomainException
{
    /**
     * @param  non-empty-list<Problem>  $problems
     */
    public function __construct(public readonly array $problems)
    {
        parent::__construct(RoundCardChecks::problemCountLabel(count($problems)));
    }
}
