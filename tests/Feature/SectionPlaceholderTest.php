<?php

/*
 * Mutation that turns the sub-address test red: drop `->where('path', '.*')` from the rounds
 * route in routes/web.php. `{path?}` alone still matches one segment, so the nested
 * /rounds/0190a6b2-7c3e-7000-8000-000000000001/edit then answers 404.
 */

use Inertia\Testing\AssertableInertia as Assert;

test('each section without a page serves the placeholder', function (string $uri, string $section) {
    $this->get($uri)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('section-placeholder')
            ->where('section', $section));
})->with([
    'import' => ['/import', 'Importer'],
    'rounds' => ['/rounds', 'Rounds'],
    'handicaps' => ['/handicaps', 'Handicaps'],
    'strokes' => ['/strokes', 'Coups'],
    'players' => ['/players', 'Joueurs'],
]);

test('a sub-address of a section without a page serves the placeholder', function (string $uri) {
    $this->get($uri)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('section-placeholder')
            ->where('section', 'Rounds'));
})->with([
    'round' => ['/rounds/0190a6b2-7c3e-7000-8000-000000000001'],
    'nested' => ['/rounds/0190a6b2-7c3e-7000-8000-000000000001/edit'],
]);

test('the home page renders the home component', function () {
    $this->get('/')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('home'));
});
