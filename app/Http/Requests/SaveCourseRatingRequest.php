<?php

namespace App\Http\Requests;

use App\Domain\Courses\RatingInput;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A rating typed by hand on the Courses page (design D6): « 71,4 » or « 71.4 » from 40,0 to
 * 90,0, a whole slope from 55 to 155. Trimmed by the TrimStrings middleware; an empty field
 * comes as null and gets the same message as a value out of bounds.
 */
class SaveCourseRatingRequest extends FormRequest
{
    private const string RATING_RANGE = 'Rating entre 40,0 et 90,0';

    private const string SLOPE_RANGE = 'Slope entre 55 et 155';

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'courseName' => ['required', 'string', 'max:100'],
            'tees' => ['required', 'string', 'max:100'],
            'rating' => ['required', $this->ratingInRange(...)],
            'slope' => ['required', $this->slopeInRange(...)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'rating.required' => self::RATING_RANGE,
            'slope.required' => self::SLOPE_RANGE,
        ];
    }

    /**
     * The validated rating, in tenths.
     */
    public function ratingT(): int
    {
        return (int) RatingInput::tenths($this->string('rating')->value());
    }

    /**
     * The validated slope.
     */
    public function slope(): int
    {
        return $this->integer('slope');
    }

    private function ratingInRange(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_scalar($value) || RatingInput::tenths((string) $value) === null) {
            $fail(self::RATING_RANGE);
        }
    }

    private function slopeInRange(string $attribute, mixed $value, Closure $fail): void
    {
        $slope = is_scalar($value) ? trim((string) $value) : '';

        if (preg_match('/^\d+$/', $slope) !== 1 || (int) $slope < 55 || (int) $slope > 155) {
            $fail(self::SLOPE_RANGE);
        }
    }
}
