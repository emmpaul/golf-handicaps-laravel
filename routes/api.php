<?php

use App\Http\Controllers\VersionController;
use Illuminate\Support\Facades\Route;

Route::get('/version', VersionController::class)->name('api.version');
