<?php

namespace App\Domain\LegacyImport\Exceptions;

use RuntimeException;

/**
 * Thrown at the end of a dry run to roll the transaction back, caught at once by
 * ImportLegacyDatabase: never seen outside it.
 */
final class DryRunCancelled extends RuntimeException {}
