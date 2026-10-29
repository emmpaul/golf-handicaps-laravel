<?php

namespace App\Domain\AiUsage;

/**
 * The cost of an AI call in micro-dollars, at the prices of config('golf.ai.prices').
 */
final class AiPricing
{
    /**
     * The cost of the tokens billed for one call, reasoning included in the output.
     */
    public static function cost(string $model, int $inputTokens, int $outputTokens): int
    {
        /** @var array<string, array{input: int, output: int}> $prices */
        $prices = config('golf.ai.prices');
        $price = $prices[$model];

        return $inputTokens * $price['input'] + $outputTokens * $price['output'];
    }

    /**
     * The cost of the worst call at the configured model: its input and output token bounds.
     */
    public static function maxCallCost(): int
    {
        return self::cost(
            config()->string('golf.ai.model'),
            config()->integer('golf.ai.max_input_tokens'),
            config()->integer('golf.ai.max_output_tokens'),
        );
    }
}
