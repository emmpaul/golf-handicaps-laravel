<?php

/*
 * Mutations that turn these tests red:
 * - drop `->where('path', '.*')` from the handicaps route in routes/web.php: `{path?}` alone
 *   still matches one segment, so the nested /handicaps/0190a6b2-7c3e-7000-8000-000000000001/edit
 *   then answers 404;
 * - put the placeholder route of Rounds back before RoundController: /rounds serves
 *   section-placeholder again.
 */

use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withPasscodeSession();
});

test('each section without a page serves the placeholder', function (string $uri, string $section) {
    $this->get($uri)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('section-placeholder')
            ->where('section', $section));
})->with([
    'import' => ['/import', 'Importer'],
    'handicaps' => ['/handicaps', 'Handicaps'],
    'strokes' => ['/strokes', 'Coups'],
]);

test('a sub-address of a section without a page serves the placeholder', function (string $uri) {
    $this->get($uri)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('section-placeholder')
            ->where('section', 'Handicaps'));
})->with([
    'one segment' => ['/handicaps/0190a6b2-7c3e-7000-8000-000000000001'],
    'nested' => ['/handicaps/0190a6b2-7c3e-7000-8000-000000000001/edit'],
]);

// The placeholder text is drawn by the section-placeholder component in the browser: the
// response of a section with its page names its own component, never that one.
test('a section that has its page no longer serves the placeholder', function (string $uri, string $component) {
    $this->get($uri)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component($component))
        ->assertDontSee('section-placeholder')
        ->assertDontSee('Section à venir');
})->with([
    'rounds' => ['/rounds', 'rounds/index'],
    'players' => ['/players', 'players/index'],
]);

test('the home page renders the home component', function () {
    $this->get('/')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('home'));
});
