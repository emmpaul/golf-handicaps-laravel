<?php

namespace App\Integrations\Ai;

use App\Domain\Courses\Sgt\SgtCard;
use Laravel\Ai\Responses\Data\TextUsage;

/**
 * What a reading of an SGT scorecard gave (design D4): the card, or null when the reading is
 * unusable, and the tokens billed for the call, always present.
 */
final readonly class SgtCardReading
{
    public function __construct(
        public ?SgtCard $card,
        public TextUsage $usage,
    ) {}
}
