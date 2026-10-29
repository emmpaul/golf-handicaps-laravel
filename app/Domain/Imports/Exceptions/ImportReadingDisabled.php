<?php

namespace App\Domain\Imports\Exceptions;

use App\Domain\DomainException;

/**
 * No Anthropic key is configured: no photo is accepted for reading.
 */
final class ImportReadingDisabled extends DomainException
{
    public const string MESSAGE = 'Lecture des photos désactivée : aucune clé d’IA n’est configurée.';

    public function __construct()
    {
        parent::__construct(self::MESSAGE);
    }
}
