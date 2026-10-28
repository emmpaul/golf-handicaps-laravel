<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * An alias added by hand: required, 50 characters at most. Taken by another player, it moves
 * (AssignAlias), so it is never refused as a duplicate.
 */
class StorePlayerAliasRequest extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'alias' => ['required', 'string', 'max:50'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'alias.required' => 'L’alias est obligatoire',
            'alias.max' => 'L’alias fait au plus 50 caractères',
        ];
    }
}
