<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\AiUsageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * One billed AI call, counted in the monthly budget of its month.
 *
 * @property string $id
 * @property string $month YYYY-MM in the budget time zone
 * @property string $model
 * @property int $input_tokens
 * @property int $output_tokens
 * @property int $cost_micro_usd
 * @property CarbonImmutable|null $created_at
 */
#[Fillable(['month', 'model', 'input_tokens', 'output_tokens', 'cost_micro_usd'])]
class AiUsage extends Model
{
    /** @use HasFactory<AiUsageFactory> */
    use HasFactory, HasUuids;

    public const UPDATED_AT = null;

    protected $table = 'ai_usage';
}
