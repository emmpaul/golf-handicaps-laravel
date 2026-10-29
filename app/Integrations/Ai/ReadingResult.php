<?php

namespace App\Integrations\Ai;

use Laravel\Ai\Responses\Data\TextUsage;

/**
 * What a reading of a scorecard photo gave: the card as the AI wrote it, checked by nobody yet,
 * and the tokens billed for the call.
 */
final readonly class ReadingResult
{
    /**
     * @param  array<mixed>  $card  [] for a refusal, a truncated output or a text that is not JSON
     */
    public function __construct(
        public array $card,
        public TextUsage $usage,
    ) {}
}
