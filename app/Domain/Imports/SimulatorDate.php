<?php

namespace App\Domain\Imports;

/**
 * The date of a card as the simulator prints it, month/day/year (« DATE: 10/17/2026 »). The AI
 * copies the text; the server reads it, so that « 03/04/2026 » is never read the other way.
 */
final class SimulatorDate
{
    /**
     * The first month/day/year date of the text as `Y-m-d`, or null when there is none or it
     * does not exist.
     */
    public static function parse(?string $text): ?string
    {
        if ($text === null || preg_match('#(\d{1,2})/(\d{1,2})/(\d{4})#', $text, $parts) !== 1) {
            return null;
        }

        [, $month, $day, $year] = array_map('intval', $parts);

        return checkdate($month, $day, $year) ? sprintf('%04d-%02d-%02d', $year, $month, $day) : null;
    }
}
