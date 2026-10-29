<?php

use App\Http\Controllers\CourseController;
use App\Http\Controllers\CourseRatingController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\ImportCorrectionController;
use App\Http\Controllers\ImportPhotoController;
use App\Http\Controllers\ImportRoundController;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\PlayerAliasController;
use App\Http\Controllers\PlayerController;
use App\Http\Controllers\RoundController;
use App\Http\Controllers\RoundPhotoController;
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
Route::inertia('/handicaps/{path?}', 'section-placeholder', ['section' => 'Handicaps'])
    ->where('path', '.*')
    ->name('handicaps.index');
Route::inertia('/strokes/{path?}', 'section-placeholder', ['section' => 'Coups'])
    ->where('path', '.*')
    ->name('strokes.index');

// Imports (scorecard-ai-import, design D6): an address under /import without a route, such as
// /import/xyz, falls to the 404 below.
Route::controller(ImportController::class)->group(function () {
    Route::get('/import', 'index')->name('import.index');
    Route::post('/imports', 'store')->name('imports.store');
    Route::get('/imports/{import}', 'show')->whereUuid('import')->name('imports.show');
});
Route::get('/imports/{import}/photo', ImportPhotoController::class)->whereUuid('import')->name('imports.photo');
// JSON only, sent by the autosave of the correction screen.
Route::put('/imports/{import}/correction', ImportCorrectionController::class)
    ->whereUuid('import')
    ->missing(fn () => response()->json(['error' => 'Import introuvable'], 404))
    ->name('imports.correction.update');
Route::post('/imports/{import}/round', ImportRoundController::class)->whereUuid('import')->name('imports.round.store');

// Rounds (design D8): /rounds/new before /rounds/{round}; a {round} that is not a UUID, such as
// /rounds/12, falls to the 404 below without any query.
Route::controller(RoundController::class)->group(function () {
    Route::get('/rounds', 'index')->name('rounds.index');
    Route::get('/rounds/new', 'create')->name('rounds.create');
    Route::post('/rounds', 'store')->name('rounds.store');
    Route::get('/rounds/{round}', 'show')->whereUuid('round')->name('rounds.show');
    Route::get('/rounds/{round}/edit', 'edit')->whereUuid('round')->name('rounds.edit');
    Route::put('/rounds/{round}', 'update')->whereUuid('round')->name('rounds.update');
    Route::delete('/rounds/{round}', 'destroy')->whereUuid('round')->name('rounds.destroy');
});

Route::get('/rounds/{round}/photo', RoundPhotoController::class)->whereUuid('round')->name('rounds.photo');

// Players (design D8): an address under /players without a route falls to the 404 below.
Route::controller(PlayerController::class)->group(function () {
    Route::get('/players', 'index')->name('players.index');
    Route::post('/players', 'store')->name('players.store');
    Route::patch('/players/{player}', 'update')->whereUuid('player')->name('players.update');
});

// {alias} is the id of player_aliases, bound through the player: an alias of another player is a 404.
Route::controller(PlayerAliasController::class)->scopeBindings()->group(function () {
    Route::post('/players/{player}/aliases', 'store')->whereUuid('player')->name('players.aliases.store');
    Route::delete('/players/{player}/aliases/{alias}', 'destroy')
        ->whereUuid(['player', 'alias'])
        ->name('players.aliases.destroy');
});

// Courses (design D6): the rating is addressed by its course and tees in the body, a course
// without rating having no row.
Route::get('/courses', [CourseController::class, 'index'])->name('courses.index');
Route::controller(CourseRatingController::class)->group(function () {
    Route::put('/courses/rating', 'update')->name('courses.rating.update');
    Route::delete('/courses/rating', 'destroy')->name('courses.rating.destroy');
});

// Unknown addresses go through the web group: without a session they redirect to /login.
// Addresses under api/ stay out of it and answer a plain 404, with or without a session.
Route::fallback(fn () => abort(404))
    ->where('fallbackPlaceholder', '(?!api(/|$)).*');
