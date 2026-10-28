<?php

namespace App\Http\Requests;

use App\Models\Player;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The name of a player, added or renamed: trimmed by the TrimStrings middleware, required, 50
 * characters at most, unique whatever the case. The unique index on lower(name) stays the guard.
 */
abstract class PlayerNameRequest extends FormRequest
{
    /**
     * The player whose own name does not count as taken, when it is renamed.
     */
    abstract protected function renamedPlayer(): ?Player;

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:50', $this->uniqueName(...)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Le nom est obligatoire',
            'name.max' => 'Le nom fait au plus 50 caractères',
        ];
    }

    private function uniqueName(string $attribute, mixed $value, Closure $fail): void
    {
        $taken = Player::query()
            ->whereRaw('lower(name) = lower(?)', [(string) $value])
            ->when($this->renamedPlayer(), fn ($query, Player $player) => $query->whereKeyNot($player->getKey()))
            ->exists();

        if ($taken) {
            $fail('Ce joueur existe déjà');
        }
    }
}
