<?php

use App\Http\Controllers\CourseController;
use App\Http\Controllers\CourseRatingController;
use App\Http\Controllers\CourseRatingLookupController;
use App\Http\Controllers\HandicapController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\ImportCorrectionController;
use App\Http\Controllers\ImportPhotoController;
use App\Http\Controllers\ImportReadingController;
use App\Http\Controllers\ImportRoundController;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\PlayerAliasController;
use App\Http\Controllers\PlayerController;
use App\Http\Controllers\RoundController;
use App\Http\Controllers\RoundPhotoController;
use App\Http\Controllers\StrokesController;
use App\Http\Middleware\RequirePasscode;
use Illuminate\Support\Facades\Route;

// The only web routes open without the passcode session.
Route::withoutMiddleware(RequirePasscode::class)->controller(LoginController::class)->group(function () {
    Route::get('/login', 'create')->name('login');
    Route::post('/login', 'store')->name('login.store');
});

Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

Route::get('/', HomeController::class)->name('home');

// Handicaps and Strokes (home-and-match-strokes, design D6): the player of the Handicaps page is
// in ?joueur=; a sub-address, such as /strokes/xyz, falls to the 404 below.
Route::get('/handicaps', [HandicapController::class, 'index'])->name('handicaps.index');
Route::get('/strokes', [StrokesController::class, 'index'])->name('strokes.index');

// Imports (scorecard-ai-import, design D6): an address under /import without a route, such as
// /import/xyz, falls to the 404 below.
Route::controller(ImportController::class)->group(function () {
    Route::get('/import', 'index')->name('import.index');
    Route::post('/imports', 'store')->name('imports.store');
    Route::get('/imports/{import}', 'show')->whereUuid('import')->name('imports.show');
    Route::delete('/imports/{import}', 'destroy')->whereUuid('import')->name('imports.destroy');
});
// The photo of an import deleted since answers as a missing file.
Route::get('/imports/{import}/photo', ImportPhotoController::class)
    ->whereUuid('import')
    ->missing(fn () => response()->json(['error' => 'Photo introuvable'], 404))
    ->name('imports.photo');
// JSON only, sent by the autosave of the correction screen.
Route::put('/imports/{import}/correction', ImportCorrectionController::class)
    ->whereUuid('import')
    ->missing(fn () => response()->json(['error' => 'Import introuvable'], 404))
    ->name('imports.correction.update');
Route::post('/imports/{import}/round', ImportRoundController::class)->whereUuid('import')->name('imports.round.store');
Route::post('/imports/{import}/reading', ImportReadingController::class)->whereUuid('import')->name('imports.reading.store');

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
// The lookup of a rating (course-rating-sgt, design D7) is addressed by the id of its row.
// No whereUuid(): the fallback answers GET only, so a POST on « 12 » would get 405; the uuid
// check of HasUuids turns it into a 404 before any query.
Route::controller(CourseRatingLookupController::class)->prefix('/courses/ratings/{courseRating}')->group(function () {
    Route::post('/confirmation', 'confirm')->name('courses.ratings.confirmation.store');
    Route::post('/tee', 'chooseTee')->name('courses.ratings.tee.store');
    Route::post('/candidate', 'chooseCandidate')->name('courses.ratings.candidate.store');
    Route::post('/lookup', 'relaunch')->name('courses.ratings.lookup.store');
});

// Unknown addresses go through the web group: without a session they redirect to /login.
// Addresses under api/ stay out of it and answer a plain 404, with or without a session.
Route::fallback(fn () => abort(404))
    ->where('fallbackPlaceholder', '(?!api(/|$)).*');
