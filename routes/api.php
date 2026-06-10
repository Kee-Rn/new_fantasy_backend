<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\MatchController;
use App\Http\Controllers\Api\ContestController;

/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
*/

// ── Auth ───────────────────────────────────────────────────────────────────
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login',    [AuthController::class, 'login']);

// ── Matches ────────────────────────────────────────────────────────────────
Route::get('/matches',                          [MatchController::class, 'index']);
Route::get('/matches/{id}',                     [MatchController::class, 'show']);

// ── Contests ───────────────────────────────────────────────────────────────
Route::get('/matches/{matchId}/contests',       [ContestController::class, 'forMatch']);
Route::get('/contests/{id}',                    [ContestController::class, 'show']);

/*
|--------------------------------------------------------------------------
| Protected Routes (requires Sanctum token)
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {

    // ── Auth ───────────────────────────────────────────────────────────────
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me',      [AuthController::class, 'me']);

});