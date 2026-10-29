<?php

namespace App\Domain\Imports\Exceptions;

use App\Domain\DomainException;

/**
 * Only a failed import is read again.
 */
final class ImportNotFailed extends DomainException
{
    public function __construct()
    {
        parent::__construct('Cet import n’est pas en échec');
    }
}
