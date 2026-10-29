<?php

namespace App\Domain\Imports;

use App\Domain\Rounds\RoundCard;
use App\Domain\Rounds\RoundCardShape;
use Illuminate\Support\Facades\Validator;

/**
 * The correction of an import saved by the autosave (design D7): `{ version: 1, card }`, the
 * card being the editor state without the keys of its lines, totals and names read included.
 * Validated at writing and at reading: an unreadable correction is ignored, never an error.
 */
final class ImportCorrection
{
    public const int VERSION = 1;

    /**
     * The validation rules of a correction; a player deleted since is accepted.
     *
     * @return array<string, list<string>>
     */
    public static function rules(): array
    {
        return [
            'version' => ['required', 'integer', 'in:'.self::VERSION],
            'card' => ['required', 'array'],
            ...RoundCardShape::rules('card.', withReading: true, knownPlayers: false),
        ];
    }

    /**
     * The card of a saved correction, or null when it does not have the expected shape.
     *
     * @param  array<array-key, mixed>|null  $correction
     */
    public static function fromArray(?array $correction): ?RoundCard
    {
        if ($correction === null) {
            return null;
        }

        $validator = Validator::make($correction, self::rules());
        if ($validator->fails()) {
            return null;
        }

        /** @var array{card: array<array-key, mixed>} $valid */
        $valid = $validator->validated();

        return RoundCardShape::card($valid['card']);
    }

    /**
     * What imports.correction keeps for this card.
     *
     * @return array{version: int, card: array<string, mixed>}
     */
    public static function toArray(RoundCard $card): array
    {
        return ['version' => self::VERSION, 'card' => $card->toArray()];
    }
}
