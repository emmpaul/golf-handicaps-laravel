<?php

namespace App\Http\Requests;

use App\Domain\Imports\ImportCorrection;
use App\Domain\Rounds\RoundCard;
use App\Domain\Rounds\RoundCardShape;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The correction sent by the autosave of the correction screen (design D7): `{ version: 1,
 * card }`, the card with its totals and names read; a player deleted since is accepted.
 */
class ImportCorrectionRequest extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return ImportCorrection::rules();
    }

    public function card(): RoundCard
    {
        /** @var array<array-key, mixed> $card */
        $card = $this->validated('card');

        return RoundCardShape::card($card);
    }
}
