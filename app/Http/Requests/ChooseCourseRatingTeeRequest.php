<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The tee read on the SGT card retained on the Courses page (design D7).
 */
class ChooseCourseRatingTeeRequest extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'tee' => ['required', 'string', 'max:100'],
        ];
    }
}
