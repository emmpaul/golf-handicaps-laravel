<?php

/*
 * Mutations that turn these tests red:
 * - in bootstrap/app.php, flash the refusal under the key 'message' instead of 'toast': the
 *   Inertia visit comes back without the toast;
 * - answer the JSON request with status 400 instead of 422;
 * - drop the render callback: the visit gets the 500 error page instead of going back;
 * - drop the DomainException check from App\Integrations\Sentry\SentryEventFilter: the refusals
 *   each send an event to Sentry;
 * - add $exceptions->stopIgnoring(ValidationException::class) to bootstrap/app.php: the name
 *   already taken sends an event.
 */

use App\Domain\DomainException;
use Illuminate\Support\Facades\Route;
use Tests\Support\Sentry\SentryEventRecorder;

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

test('a refusal by the domain keeps its toast and sends no event to Sentry', function () {
    $recorder = SentryEventRecorder::install();

    $this->withPasscodeSession()
        ->withHeader('X-Inertia', 'true')
        ->from('/rounds')
        ->post('/domain-exception-probe')
        ->assertRedirect('/rounds')
        ->assertInertiaFlash('toast', ['type' => 'error', 'message' => 'Refus de test']);

    expect($recorder->events())->toBe([]);
});

test('a refused JSON request keeps its 422 and sends no event to Sentry', function () {
    $recorder = SentryEventRecorder::install();

    $this->withPasscodeSession()->postJson('/domain-exception-probe')->assertStatus(422);

    expect($recorder->events())->toBe([]);
});

test('a name already taken keeps its validation error and sends no event to Sentry', function () {
    createPlayersOnTest('Manu');
    $recorder = SentryEventRecorder::install();

    $this->withPasscodeSession()
        ->from('/players')
        ->post('/players', ['name' => 'Manu'])
        ->assertRedirect('/players')
        ->assertSessionHasErrors(['name' => 'Ce joueur existe déjà']);

    expect($recorder->events())->toBe([]);
});
