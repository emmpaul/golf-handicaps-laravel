<?php

namespace App\Http\Requests;

use App\Domain\Handicaps\Pick;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

/**
 * The address of a virtual match (design D4): ?course=…&tees=…&picks=roundUuid.playerUuid,…
 * It only reads the shape: a value of another shape, a malformed UUID or an unreadable pair is
 * left out, never refused, since the spec asks to ignore them without error.
 */
class CompareRoundsRequest extends FormRequest
{
    /**
     * Nothing is refused: every value is read leniently by the accessors below.
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [];
    }

    /**
     * The requested course name, null when absent or not a string.
     */
    public function courseName(): ?string
    {
        return $this->stringQuery('course');
    }

    /**
     * The requested tees, null when absent or not a string.
     */
    public function tees(): ?string
    {
        return $this->stringQuery('tees');
    }

    /**
     * The readable picks, in the order of the address.
     *
     * @return list<Pick>
     */
    public function picks(): array
    {
        $picks = [];
        foreach (explode(',', $this->stringQuery('picks') ?? '') as $pair) {
            $ids = explode('.', trim($pair));
            if (count($ids) === 2 && Str::isUuid($ids[0]) && Str::isUuid($ids[1])) {
                $picks[] = new Pick(strtolower($ids[0]), strtolower($ids[1]));
            }
        }

        return $picks;
    }

    private function stringQuery(string $key): ?string
    {
        $value = $this->query($key);

        return is_string($value) ? $value : null;
    }
}
