<?php

/*
 * Mutations that turn these tests red:
 * - in bootstrap/app.php, flash the refusal under the key 'message' instead of 'toast': the
 *   Inertia visit comes back without the toast;
 * - answer the JSON request with status 400 instead of 422;
 * - drop the render callback: the visit gets the 500 error page instead of going back.
 */

use App\Domain\DomainException;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Route::middleware('web')->post('/domain-exception-probe', function () {
        throw new class('Refus de test') extends DomainException {};
    });
});

test('an Inertia visit refused by the domain goes back with an error toast', function () {
    $this->withPasscodeSession()
        ->withHeader('X-Inertia', 'true')
        ->from('/rounds')
        ->post('/domain-exception-probe')
        ->assertRedirect('/rounds')
        ->assertInertiaFlash('toast', ['type' => 'error', 'message' => 'Refus de test']);
});

test('a JSON request refused by the domain gets 422 with the message', function () {
    $this->withPasscodeSession()
        ->postJson('/domain-exception-probe')
        ->assertStatus(422)
        ->assertExactJson(['error' => 'Refus de test']);
});
