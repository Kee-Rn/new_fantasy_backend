<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GameMatch;
use App\Models\MatchPlayer;
use Illuminate\Http\JsonResponse;

class PlayerController extends Controller
{
    // ── All squad players for a match, grouped by team ─────────────────────
    // GET /api/matches/{matchId}/players
    //
    // This is the main endpoint the frontend uses when a user is building
    // their fantasy team. Returns both teams' squads with playing XI status.

    public function forMatch(int $matchId): JsonResponse
    {
        $match = GameMatch::with(['homeTeam', 'awayTeam'])->findOrFail($matchId);

        $matchPlayers = MatchPlayer::with(['player', 'team'])
            ->where('match_id', $matchId)
            ->get();

        // Group by team
        $grouped = $matchPlayers->groupBy('team_id');

        $teams = $grouped->map(function ($players, $teamId) {
            $team = $players->first()->team;

            return [
                'team' => [
                    'id'       => $team->id,
                    'name'     => $team->name,
                    'logo_url' => $team->logo_url,
                ],
                'players' => $players->map(fn ($mp) => $this->formatMatchPlayer($mp))
                    ->sortBy('player.name')
                    ->values(),
            ];
        })->values();

        return response()->json([
            'match_id' => $matchId,
            'teams'    => $teams,
            'summary'  => [
                'total_players'    => $matchPlayers->count(),
                'playing_xi_count' => $matchPlayers->where('is_playing_xi', true)->count(),
                'xi_confirmed'     => $matchPlayers->where('is_playing_xi', true)->count() > 0,
            ],
        ]);
    }

    // ── Players for a match filtered by role ──────────────────────────────
    // GET /api/matches/{matchId}/players?role=WK
    // GET /api/matches/{matchId}/players?role=BAT
    // GET /api/matches/{matchId}/players?role=ALL
    // GET /api/matches/{matchId}/players?role=BOWL

    public function forMatchByRole(int $matchId, string $role): JsonResponse
    {
        $matchPlayers = MatchPlayer::with(['player', 'team'])
            ->where('match_id', $matchId)
            ->whereHas('player', fn ($q) => $q->where('role', strtoupper($role)))
            ->get();

        return response()->json([
            'match_id' => $matchId,
            'role'     => strtoupper($role),
            'players'  => $matchPlayers->map(fn ($mp) => $this->formatMatchPlayer($mp))->values(),
        ]);
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    private function formatMatchPlayer(MatchPlayer $mp): array
    {
        return [
            'id'             => $mp->player->id,
            'name'           => $mp->player->name,
            'role'           => $mp->player->role,
            'photo_url'      => $mp->player->photo_url,
            'team_id'        => $mp->team_id,
            'team_name'      => $mp->team->name ?? null,
            'is_playing_xi'  => $mp->is_playing_xi,
            'is_bench'       => $mp->is_bench,
        ];
    }
}