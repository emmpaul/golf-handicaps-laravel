<?php

use Illuminate\Support\Facades\Route;

Route::inertia('/', 'home')->name('home');

// Sections without a page yet: each address and its sub-addresses serve the placeholder,
// until the section's own change replaces the route under the same name.
Route::inertia('/import/{path?}', 'section-placeholder', ['section' => 'Importer'])
    ->where('path', '.*')
    ->name('import.index');
Route::inertia('/rounds/{path?}', 'section-placeholder', ['section' => 'Rounds'])
    ->where('path', '.*')
    ->name('rounds.index');
Route::inertia('/handicaps/{path?}', 'section-placeholder', ['section' => 'Handicaps'])
    ->where('path', '.*')
    ->name('handicaps.index');
Route::inertia('/strokes/{path?}', 'section-placeholder', ['section' => 'Coups'])
    ->where('path', '.*')
    ->name('strokes.index');
Route::inertia('/players/{path?}', 'section-placeholder', ['section' => 'Joueurs'])
    ->where('path', '.*')
    ->name('players.index');
