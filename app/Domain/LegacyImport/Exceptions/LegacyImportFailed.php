<?php

namespace App\Domain\LegacyImport\Exceptions;

use RuntimeException;

/**
 * Why golf:import-sqlite stops, for the operator of the server: a message and the list of what
 * is at fault, every fault of a check and not only the first. Outside DomainException: no HTTP
 * request renders it, and nothing of it goes to the application log.
 */
final class LegacyImportFailed extends RuntimeException
{
    /**
     * @param  list<string>  $details  one line per fault
     */
    public function __construct(string $message, public readonly array $details = [])
    {
        parent::__construct($message);
    }
}
