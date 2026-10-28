<?php

namespace App\Domain\Players;

/**
 * Normalizes a player name and an alias before they are compared or stored.
 */
final class PlayerName
{
    /**
     * A player name without its surrounding spaces; its case is kept.
     */
    public static function name(string $raw): string
    {
        return trim($raw);
    }

    /**
     * An alias without its surrounding spaces, upper cased, accented letters included.
     */
    public static function alias(string $raw): string
    {
        return mb_strtoupper(trim($raw));
    }
}
