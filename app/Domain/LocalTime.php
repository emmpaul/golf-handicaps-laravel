<?php

namespace App\Domain;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use InvalidArgumentException;

/**
 * The single conversion point between stored UTC instants and the display timezone.
 */
final class LocalTime
{
    /**
     * Format an instant in the display timezone. A string (raw SQL value) is read as UTC.
     */
    public static function format(CarbonInterface|string $instant, string $pattern = 'Y-m-d H:i'): string
    {
        $instant = is_string($instant)
            ? CarbonImmutable::parse($instant, 'UTC')
            : CarbonImmutable::instance($instant);

        return $instant->setTimezone(self::timezone())->format($pattern);
    }

    /**
     * Format an instant as a local Y-m-d date.
     */
    public static function date(CarbonInterface|string $instant): string
    {
        return self::format($instant, 'Y-m-d');
    }

    /**
     * Local midnight of the given Y-m-d day, expressed in UTC. The exclusive end of a day
     * is the start of the next day.
     *
     * @throws InvalidArgumentException when the string is not an existing Y-m-d day
     */
    public static function startOfDay(string $date): CarbonImmutable
    {
        $day = CarbonImmutable::createFromFormat('!Y-m-d', $date, self::timezone());

        // Carbon rolls an out-of-range day over (2026-13-45 becomes 2027-02-14) and returns
        // null instead of throwing when strict mode is off: both are rejected here.
        if ($day === null || $day->format('Y-m-d') !== $date) {
            throw new InvalidArgumentException("Invalid date \"{$date}\": expected an existing Y-m-d day.");
        }

        return $day->setTimezone('UTC');
    }

    private static function timezone(): string
    {
        return config()->string('app.display_timezone');
    }
}
