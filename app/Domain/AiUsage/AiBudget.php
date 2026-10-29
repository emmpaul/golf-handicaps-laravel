<?php

namespace App\Domain\AiUsage;

use App\Models\AiUsage;
use Carbon\CarbonImmutable;

/**
 * The monthly ceiling of AI spending (spec scorecard-ai-completion, « Plafond de dépense
 * mensuel »), counted in ai_usage per calendar month of config('golf.ai.budget_time_zone').
 */
final class AiBudget
{
    /**
     * Whether a call is allowed: the total of the month plus the cost of the worst call still
     * fits the ceiling. The worst call is that of a photo unless the caller gives its own, as
     * the reading of an SGT scorecard does.
     */
    public function canSpend(CarbonImmutable $now, ?int $maxCallCostMicroUsd = null): bool
    {
        $spent = (int) AiUsage::query()->where('month', $this->month($now))->sum('cost_micro_usd');

        return $spent + ($maxCallCostMicroUsd ?? AiPricing::maxCallCost()) <= $this->ceiling();
    }

    /**
     * Counts a billed call in the month it was made.
     */
    public function record(string $model, int $inputTokens, int $outputTokens, CarbonImmutable $now): AiUsage
    {
        return AiUsage::query()->create([
            'month' => $this->month($now),
            'model' => $model,
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
            'cost_micro_usd' => AiPricing::cost($model, $inputTokens, $outputTokens),
        ]);
    }

    /**
     * `YYYY-MM` of the instant in the budget time zone.
     */
    private function month(CarbonImmutable $now): string
    {
        return $now->setTimezone(config()->string('golf.ai.budget_time_zone'))->format('Y-m');
    }

    /**
     * The monthly ceiling in micro-dollars.
     */
    private function ceiling(): int
    {
        return (int) round(config()->float('golf.ai.monthly_budget_usd') * 1_000_000);
    }
}
