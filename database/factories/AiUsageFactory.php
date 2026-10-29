<?php

namespace Database\Factories;

use App\Models\AiUsage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiUsage>
 */
class AiUsageFactory extends Factory
{
    /**
     * A reading of the current month by the default model: 3 000 tokens in, 1 200 out, 18 000
     * micro-dollars at 2 and 10 micro-dollars per token.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'month' => now()->format('Y-m'),
            'model' => 'claude-sonnet-5-5',
            'input_tokens' => 3000,
            'output_tokens' => 1200,
            'cost_micro_usd' => 18000,
        ];
    }

    /**
     * @param  string  $month  YYYY-MM
     */
    public function inMonth(string $month): static
    {
        return $this->state(['month' => $month]);
    }
}
