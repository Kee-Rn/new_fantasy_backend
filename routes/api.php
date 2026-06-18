<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\MatchController;
use App\Http\Controllers\Api\ContestController;
use App\Http\Controllers\Api\PlayerController;
use App\Http\Controllers\Api\FantasyTeamController;
use App\Http\Controllers\Api\LeaderboardController;
use App\Http\Controllers\Api\LiveScoreController;

/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
*/

// ── Auth ───────────────────────────────────────────────────────────────────
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login',    [AuthController::class, 'login']);

// ── Matches ────────────────────────────────────────────────────────────────
Route::get('/matches',      [MatchController::class, 'index']);
Route::get('/matches/{id}', [MatchController::class, 'show']);

// ── Contests ───────────────────────────────────────────────────────────────
Route::get('/matches/{matchId}/contests', [ContestController::class, 'forMatch']);
Route::get('/contests/{id}',              [ContestController::class, 'show']);

// ── Players ────────────────────────────────────────────────────────────────
Route::get('/matches/{matchId}/players', [PlayerController::class, 'forMatch']);

// ── Leaderboard (public — standings are visible to everyone) ───────────────
Route::get('/contests/{contestId}/leaderboard', [LeaderboardController::class, 'index']);

// ── Live Score ─────────────────────────────────────────────────────────────
Route::prefix('matches/{matchId}/live-score')->group(function () {
    Route::get('/',                  [LiveScoreController::class, 'snapshot']);     // poll every 5–10s
    Route::get('/scorecard',         [LiveScoreController::class, 'scorecard']);    // poll every 30s
    Route::get('/innings/{innings}', [LiveScoreController::class, 'inningsBalls']); // full ball log
});

/*
|--------------------------------------------------------------------------
| Protected Routes (requires Sanctum token)
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {

    // ── Auth ───────────────────────────────────────────────────────────────
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me',      [AuthController::class, 'me']);

    // ── Fantasy Team ───────────────────────────────────────────────────────
    Route::post('/fantasy-teams',               [FantasyTeamController::class, 'store']);
    Route::get('/contests/{contestId}/my-team', [FantasyTeamController::class, 'myTeam']);

    // ── Team Card (auth required — pre-deadline restricted to own team) ────
    Route::get('/fantasy-teams/{fantasyTeamId}/card', [LeaderboardController::class, 'teamCard']);

});