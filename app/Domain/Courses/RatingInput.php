<?php

namespace App\Domain\Courses;

/**
 * A course rating typed by hand. Port of parseRatingT of the Next.js application.
 */
final class RatingInput
{
    /**
     * « 71,4 » or « 71.4 » to 714: two digits, one decimal at most after a comma or a point,
     * from 40,0 to 90,0; null otherwise.
     */
    public static function tenths(string $typed): ?int
    {
        if (preg_match('/^(\d{2})(?:[.,](\d))?$/', trim($typed), $match) !== 1) {
            return null;
        }

        $tenths = (int) $match[1] * 10 + (int) ($match[2] ?? 0);

        return $tenths >= 400 && $tenths <= 900 ? $tenths : null;
    }
}
