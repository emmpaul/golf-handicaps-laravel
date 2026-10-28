<?php

namespace App\Http\Requests;

use App\Support\Passcode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * An empty passcode is not a validation error: it gets "Code incorrect" like any wrong one.
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'passcode' => ['nullable', 'string'],
        ];
    }

    /**
     * Check the entered passcode against the configured one, at most 10 attempts a minute per IP.
     *
     * Every attempt counts, a right one included: the limit counts attempts, not failures.
     *
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $key = 'login:'.$this->ip();

        if (RateLimiter::tooManyAttempts($key, 10)) {
            throw ValidationException::withMessages(['passcode' => "Trop d'essais. Réessaie dans une minute."]);
        }

        RateLimiter::hit($key, 60);

        if (! Passcode::matches((string) $this->validated('passcode'))) {
            throw ValidationException::withMessages(['passcode' => 'Code incorrect']);
        }
    }
}
