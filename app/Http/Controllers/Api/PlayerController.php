<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GameMatch;
use App\Models\Player;
use Illuminate\Http\JsonResponse;

class PlayerController extends Controller
{
    // ── All players from both teams in a match ─────────────────────────────
    // GET /api/matches/{matchId}/players
    //
    // Returns every active player belonging to the home team and away team
    // of this match, grouped by team. No match_players assignment needed.

    public function forMatch(int $matchId): JsonResponse
    {
        $match = GameMatch::with(['homeTeam', 'awayTeam'])->findOrFail($matchId);

        $teamIds = [$match->home_team_id, $match->away_team_id];

        $players = Player::with('team')
            ->whereIn('team_id', $teamIds)
            ->where('is_active', true)
            ->orderBy('team_id')
            ->orderBy('name')
            ->get();

        // Group by team
        $grouped = $players->groupBy('team_id');

        $teams = $grouped->map(function ($teamPlayers) {
            $team = $teamPlayers->first()->team;

            return [
                'team' => [
                    'id'       => $team->id,
                    'name'     => $team->name,
                    'logo_url' => $team->logo_url ?? null,
                ],
                'players' => $teamPlayers->map(fn ($p) => $this->formatPlayer($p))->values(),
            ];
        })->values();

        return response()->json([
            'match_id' => $matchId,
            'teams'    => $teams,
            'summary'  => [
                'total_players' => $players->count(),
                'budget'        => 100,
                'home_team'     => $match->homeTeam->name ?? null,
                'away_team'     => $match->awayTeam->name ?? null,
            ],
        ]);
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    private function formatPlayer(Player $player): array
    {
        return [
            'id'        => $player->id,
            'name'      => $player->name,
            'role'      => $player->role,
            'photo_url' => $player->photo_url,
            'team_id'   => $player->team_id,
            'team_name' => $player->team->name ?? null,
            'price'     => $player->price,
        ];
    }
}   