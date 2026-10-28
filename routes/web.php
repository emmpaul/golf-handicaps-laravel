<?php

use App\Http\Controllers\LoginController;
use App\Http\Middleware\RequirePasscode;
use Illuminate\Support\Facades\Route;

// The only web routes open without the passcode session.
Route::withoutMiddleware(RequirePasscode::class)->controller(LoginController::class)->group(function () {
    Route::get('/login', 'create')->name('login');
    Route::post('/login', 'store')->name('login.store');
});

Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

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

// Unknown addresses go through the web group: without a session they redirect to /login.
// Addresses under api/ stay out of it and answer a plain 404, with or without a session.
Route::fallback(fn () => abort(404))
    ->where('fallbackPlaceholder', '(?!api(/|$)).*');
