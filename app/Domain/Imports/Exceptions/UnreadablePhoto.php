<?php

namespace App\Domain\Imports\Exceptions;

use App\Domain\DomainException;

/**
 * A file sent as the photo of a card that the server cannot decode as an image: nothing is
 * written.
 */
final class UnreadablePhoto extends DomainException
{
    public function __construct()
    {
        parent::__construct('Photo illisible');
    }
}
