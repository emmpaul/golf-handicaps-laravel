<?php

/*
 * Prices of config/golf.php in micro-dollars per token: claude-sonnet-5-5 2 in, 10 out;
 * claude-opus-5-5 4 in, 20 out (Anthropic grid, 2026-10-28).
 *
 * Mutations that turn these tests red:
 * - input and output prices swapped (in the formula or in config/golf.php): 3 000 / 1 200 on
 *   Sonnet costs 42 000 instead of 18 000;
 * - the maximal call cost computed on the output bound only: 40 000 instead of 52 000;
 * - max_input_tokens left at 4 000, under the 4 306 tokens measured on IMG_3064: 48 000
 *   instead of 52 000;
 * - the bounds of a reading of an SGT scorecard set to those of a photo (6 000 / 4 000): 52 000
 *   instead of 19 000 (course-rating-sgt, design D4).
 */

use App\Domain\AiUsage\AiPricing;
use Tests\TestCase;

// AiPricing reads config('golf.ai'): the application must be booted.
pest()->extend(TestCase::class);

test('a call costs its input and output tokens at the model prices', function (string $model, int $input, int $output, int $cost) {
    expect(AiPricing::cost($model, $input, $output))->toBe($cost);
})->with([
    'Sonnet 5.5' => ['claude-sonnet-5-5', 3000, 1200, 18000],
    'Opus 5.5' => ['claude-opus-5-5', 3000, 1200, 36000],
]);

// 6 000 tokens in at 2, 4 000 out at 10.
test('the maximal call cost is that of the token bounds at the default model', function () {
    expect(AiPricing::maxCallCost())->toBe(52000);
});

// Course-rating-sgt, design D4: an SGT scorecard is read under the bounds of the Next
// application, 2 000 tokens in at 2 and 1 500 out at 10.
test('a reading of an SGT scorecard costs at most its token bounds at the default model', function () {
    expect(AiPricing::cost(
        'claude-sonnet-5-5',
        config()->integer('golf.ai.rating_max_input_tokens'),
        config()->integer('golf.ai.rating_max_output_tokens'),
    ))->toBe(19000);
});
