<?php

/*
 * Spec app-shell « Bouton Importer »: the number of imports ready to check, shared with every
 * page as a deferred prop for the badge of the Importer button; design D7.
 *
 * Mutations that turn these tests red:
 * - the reading imports counted (status ready condition dropped): 3 instead of 2;
 * - the ready import saved as a round counted (`round_id is null` dropped): 3 instead of 2;
 * - the prop shared eagerly instead of deferred: it is in the first response;
 * - the prop shared without a session: /login carries the count.
 */

use App\Models\Import;
use App\Models\Round;
use Inertia\Testing\AssertableInertia as Assert;

test('the pages share the number of ready imports without round, as a deferred prop', function () {
    Import::factory()->ready()->count(2)->create();
    Import::factory()->reading()->create();
    Import::factory()->savedAs(Round::factory()->create())->create();

    $this->withPasscodeSession()
        ->get('/rounds')
        ->assertInertia(fn (Assert $page) => $page
            ->component('rounds/index')
            ->missing('pendingImports')
            ->loadDeferredProps(fn (Assert $reload) => $reload->where('pendingImports', 2)));
});

test('without import the shared number is 0', function () {
    $this->withPasscodeSession()
        ->get('/players')
        ->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload->where('pendingImports', 0)));
});

test('the login page shares no number of imports, even when asked for it', function () {
    Import::factory()->ready()->create();

    $this->get('/login')
        ->assertInertia(fn (Assert $page) => $page
            ->component('login')
            ->reload(fn (Assert $reload) => $reload->missing('pendingImports'), only: 'pendingImports'));
});
