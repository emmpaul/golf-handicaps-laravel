<?php

namespace App\Domain\Players\Exceptions;

use App\Domain\DomainException;

/**
 * INITIAL_PLAYERS names the same player twice, whatever the case.
 */
final class DuplicateInitialPlayer extends DomainException
{
    public function __construct(string $name)
    {
        parent::__construct("INITIAL_PLAYERS nomme deux fois le même joueur : {$name}.");
    }
}
