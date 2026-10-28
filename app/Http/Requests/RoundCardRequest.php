<?php

namespace App\Http\Requests;

use App\Domain\Rounds\RoundCard;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The shape of a round card sent by the round editor (design D3): any cell may be empty, the
 * coherence checks of RoundCardChecks run afterwards in the Action. A card of another shape
 * never comes from the editor and is an ordinary validation error.
 */
abstract class RoundCardRequest extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'courseName' => ['nullable', 'string', 'max:100'],
            'tees' => ['nullable', 'string', 'max:100'],
            'playedOn' => ['nullable', 'date_format:Y-m-d'],
            'holes' => ['required', 'list', 'size:18'],
            'holes.*' => ['array'],
            'holes.*.par' => ['nullable', 'integer'],
            'holes.*.strokeIndex' => ['nullable', 'integer'],
            'holes.*.distance' => ['nullable', 'integer'],
            'lines' => ['required', 'list', 'min:1', 'max:4'],
            'lines.*' => ['array'],
            'lines.*.playerId' => ['nullable', 'uuid', 'exists:players,id'],
            'lines.*.strokes' => ['required', 'list', 'size:18'],
            'lines.*.strokes.*' => ['nullable', 'integer'],
            'force' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * The validated card, its numbers as integers whether they came as JSON or as form fields.
     */
    public function card(): RoundCard
    {
        $int = fn (mixed $value): ?int => $value === null ? null : (int) $value;

        /** @var array{courseName?: string|null, tees?: string|null, playedOn?: string|null, holes: list<array<string, mixed>>, lines: list<array<string, mixed>>} $card */
        $card = $this->validated();

        return new RoundCard(
            courseName: $card['courseName'] ?? null,
            tees: $card['tees'] ?? null,
            playedOn: $card['playedOn'] ?? null,
            holes: array_map(fn (array $hole): array => [
                'par' => $int($hole['par'] ?? null),
                'strokeIndex' => $int($hole['strokeIndex'] ?? null),
                'distance' => $int($hole['distance'] ?? null),
            ], $card['holes']),
            lines: array_map(fn (array $line): array => [
                'playerId' => isset($line['playerId']) ? (string) $line['playerId'] : null,
                'strokes' => array_map($int, array_values((array) $line['strokes'])),
            ], $card['lines']),
        );
    }

    /**
     * Whether the person chose to save a card that looks like a saved round.
     */
    public function forced(): bool
    {
        return $this->boolean('force');
    }
}
