<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The SGT candidate chosen on the Courses page, by its SGT id (design D7).
 */
class ChooseSgtCandidateRequest extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'sgtCourseId' => ['required', 'integer', 'min:1'],
        ];
    }
}
