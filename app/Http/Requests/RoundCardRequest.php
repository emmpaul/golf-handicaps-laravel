<?php

namespace App\Http\Requests;

use App\Domain\Rounds\RoundCard;
use App\Domain\Rounds\RoundCardShape;
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
            ...RoundCardShape::rules(withReading: $this->withReading()),
            'force' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * The validated card, its numbers as integers whether they came as JSON or as form fields.
     */
    public function card(): RoundCard
    {
        return RoundCardShape::card($this->validated());
    }

    /**
     * Whether the person chose to save a card that looks like a saved round.
     */
    public function forced(): bool
    {
        return $this->boolean('force');
    }

    /**
     * Whether the card may carry the totals and names read on a photo.
     */
    protected function withReading(): bool
    {
        return false;
    }
}
