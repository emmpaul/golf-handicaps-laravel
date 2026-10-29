<?php

/*
 * Spec app-shell « Bouton Importer »: the number of imports ready to check, shared with every
 * page for the badge of the Importer button; design D7, DO35 (a plain prop, read on the first
 * load: deferred, the badge blinked in and the home page shifted).
 *
 * Mutations that turn these tests red:
 * - the reading imports counted (status ready condition dropped): 3 instead of 2;
 * - the ready import saved as a round counted (`round_id is null` dropped): 3 instead of 2;
 * - the prop deferred again (Inertia::defer): it is missing from the first response;
 * - the prop shared without a session: /login carries the count.
 */

use App\Models\Import;
use App\Models\Round;
use Inertia\Testing\AssertableInertia as Assert;

test('the pages share the number of ready imports without round on the first load', function () {
    Import::factory()->ready()->count(2)->create();
    Import::factory()->reading()->create();
    Import::factory()->savedAs(Round::factory()->create())->create();

    $this->withPasscodeSession()
        ->get('/rounds')
        ->assertInertia(fn (Assert $page) => $page
            ->component('rounds/index')
            ->where('pendingImports', 2));
});

test('without import the shared number is 0', function () {
    $this->withPasscodeSession()
        ->get('/players')
        ->assertInertia(fn (Assert $page) => $page->where('pendingImports', 0));
});

test('the login page shares no number of imports, even when asked for it', function () {
    Import::factory()->ready()->create();

    $this->get('/login')
        ->assertInertia(fn (Assert $page) => $page
            ->component('login')
            ->missing('pendingImports')
            ->reload(fn (Assert $reload) => $reload->missing('pendingImports'), only: 'pendingImports'));
});
