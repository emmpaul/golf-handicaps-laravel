<?php

namespace App\Domain\Imports;

/**
 * Why the reading of an import failed, as the list of imports shows it.
 */
final class ReadingFailure
{
    public const string BUDGET_REACHED = 'Budget IA du mois atteint';

    public const string AI_UNAVAILABLE = 'Lecture IA indisponible';
}
