<?php

namespace App\Domain\Imports;

use App\Domain\Imports\Exceptions\ImportReadingDisabled;

/**
 * Whether the photos can be read by the AI: an Anthropic key is configured (config/ai.php).
 */
final class AiReading
{
    public static function enabled(): bool
    {
        return filled(config('ai.providers.anthropic.key'));
    }

    /**
     * @throws ImportReadingDisabled without key
     */
    public static function ensureEnabled(): void
    {
        if (! self::enabled()) {
            throw new ImportReadingDisabled;
        }
    }
}
