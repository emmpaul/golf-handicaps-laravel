<?php

namespace Database\Factories;

use App\Domain\Imports\ImportStatus;
use App\Models\Import;
use App\Models\Round;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Storage;

/**
 * @extends Factory<Import>
 */
class ImportFactory extends Factory
{
    /**
     * An import waiting for its reading, its photo not written: withPhoto() writes it.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'status' => ImportStatus::Queued,
            'photo_path' => 'imports/'.fake()->uuid().'.webp',
            'draft' => null,
            'correction' => null,
            'failure' => null,
            'round_id' => null,
        ];
    }

    /**
     * The card read from the reference photo IMG_3064: BETHPAGE BLACK, tees Red, 2026-10-17,
     * par 71, GAB 72 and MANU 88.
     *
     * @return array<string, mixed>
     */
    public static function referenceDraft(): array
    {
        /** @var array<string, mixed> */
        return json_decode((string) file_get_contents(database_path('data/img3064.draft.json')), true, flags: JSON_THROW_ON_ERROR);
    }

    public function queued(): static
    {
        return $this->state(['status' => ImportStatus::Queued]);
    }

    public function reading(): static
    {
        return $this->state(['status' => ImportStatus::Reading]);
    }

    /**
     * Read by the AI, ready to check; the reference card when no draft is given.
     *
     * @param  array<string, mixed>|null  $draft
     */
    public function ready(?array $draft = null): static
    {
        return $this->state(fn (): array => ['status' => ImportStatus::Ready, 'draft' => $draft ?? self::referenceDraft()]);
    }

    public function failed(string $failure): static
    {
        return $this->state(['status' => ImportStatus::Failed, 'failure' => $failure]);
    }

    /**
     * The reference photo IMG_3064 written on the photos disk at the import's path.
     */
    public function withPhoto(): static
    {
        return $this->afterCreating(function (Import $import): void {
            Storage::disk('photos')->put($import->photo_path, (string) file_get_contents(database_path('data/img3064.webp')));
        });
    }

    /**
     * Read, then saved as this round, which shares the photo of the import.
     */
    public function savedAs(Round $round): static
    {
        return $this->ready()->state(fn (array $attributes): array => [
            'round_id' => $round->id,
            'photo_path' => $round->photo_path ?? $attributes['photo_path'],
        ]);
    }
}
