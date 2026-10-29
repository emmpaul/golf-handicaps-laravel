<?php

namespace App\Models;

use App\Domain\Imports\ImportStatus;
use Carbon\CarbonImmutable;
use Database\Factories\ImportFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A photo of a scorecard sent for reading by the AI, then checked and saved as a round.
 *
 * @property string $id
 * @property ImportStatus $status
 * @property string $photo_path relative to the photos disk
 * @property array<string, mixed>|null $draft the card read by the AI
 * @property array<string, mixed>|null $correction the editor state saved by the autosave
 * @property string|null $failure why the reading failed
 * @property string|null $round_id the round saved from this import
 * @property CarbonImmutable|null $created_at
 */
#[Fillable(['status', 'photo_path', 'draft', 'correction', 'failure', 'round_id'])]
class Import extends Model
{
    /** @use HasFactory<ImportFactory> */
    use HasFactory, HasUuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ImportStatus::class,
            'draft' => 'array',
            'correction' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Round, $this>
     */
    public function round(): BelongsTo
    {
        return $this->belongsTo(Round::class);
    }
}
