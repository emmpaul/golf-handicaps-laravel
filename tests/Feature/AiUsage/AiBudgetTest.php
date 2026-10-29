<?php

/*
 * Default configuration: a ceiling of 5 $, a maximal call cost of 52 000 micro-dollars
 * (claude-sonnet-5-5, 6 000 tokens in at 2 and 4 000 out at 10), the America/Montreal month.
 *
 * Mutations that turn these tests red:
 * - < instead of <= in canSpend(): 4 948 000 + 52 000 = 5 000 000 is refused;
 * - max_input_tokens left at 4 000 (maximal cost 48 000): 4 950 000 + 48 000 = 4 998 000 is
 *   allowed;
 * - the month computed in UTC: the call of 2026-11-01 02:30 UTC is counted in 2026-11 without
 *   variable;
 * - the month of the cumulated total ignored: the full October still blocks November.
 */

use App\Domain\AiUsage\AiBudget;
use App\Models\AiUsage;
use Carbon\CarbonImmutable;

test('a call is allowed while the month total plus its maximal cost fits the ceiling', function (int $spent, bool $allowed) {
    AiUsage::factory()->inMonth('2026-10')->create(['cost_micro_usd' => $spent]);

    expect(app(AiBudget::class)->canSpend(CarbonImmutable::parse('2026-10-20 15:00:00', 'UTC')))->toBe($allowed);
})->with([
    'ceiling reached' => [4950000, false],
    'ceiling just held' => [4948000, true],
]);

test('a new month starts from zero', function () {
    AiUsage::factory()->inMonth('2026-10')->create(['cost_micro_usd' => 5000000]);

    expect(app(AiBudget::class)->canSpend(CarbonImmutable::parse('2026-11-15 15:00:00', 'UTC')))->toBeTrue();
});

test('a call is recorded with its cost in the month of the budget time zone', function (?string $timeZone, string $month) {
    if ($timeZone !== null) {
        config(['golf.ai.budget_time_zone' => $timeZone]);
    }

    app(AiBudget::class)->record('claude-sonnet-5-5', 3000, 1200, CarbonImmutable::parse('2026-11-01 02:30:00', 'UTC'));

    expect(AiUsage::query()->get(['month', 'model', 'input_tokens', 'output_tokens', 'cost_micro_usd'])->toArray())->toBe([[
        'month' => $month,
        'model' => 'claude-sonnet-5-5',
        'input_tokens' => 3000,
        'output_tokens' => 1200,
        'cost_micro_usd' => 18000,
    ]]);
})->with([
    'no variable: America/Montreal' => [null, '2026-10'],
    'Europe/Paris' => ['Europe/Paris', '2026-11'],
]);
