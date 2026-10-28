<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The course and tees whose rating is removed (design D6).
 */
class RemoveCourseRatingRequest extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'courseName' => ['required', 'string', 'max:100'],
            'tees' => ['required', 'string', 'max:100'],
        ];
    }
}
