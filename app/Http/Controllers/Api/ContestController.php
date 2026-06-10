<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FantasyContest;
use Illuminate\Http\JsonResponse;

class ContestController extends Controller
{
    // ── Single contest detail ──────────────────────────────────────────────
    // GET /api/contests/{id}

    public function show(int $id): JsonResponse
    {
        $contest = FantasyContest::with(['match.homeTeam', 'match.awayTeam', 'match.league'])
            ->findOrFail($id);

        return response()->json([
            'contest' => $this->formatContest($contest),
        ]);
    }

    // ── List contests for a match ──────────────────────────────────────────
    // GET /api/matches/{matchId}/contests

    public function forMatch(int $matchId): JsonResponse
    {
        $contests = FantasyContest::where('match_id', $matchId)
            ->whereNotIn('status', ['cancelled'])
            ->orderBy('entry_fee', 'asc')
            ->get();

        return response()->json([
            'contests' => $contests->map(fn ($c) => $this->formatContest($c)),
        ]);
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    private function formatContest(FantasyContest $contest): array
    {
        $data = [
            'id'              => $contest->id,
            'match_id'        => $contest->match_id,
            'name'            => $contest->name,
            'entry_fee'       => $contest->entry_fee,
            'prize_pool'      => $contest->prize_pool,
            'max_teams'       => $contest->max_teams,
            'total_teams'     => $contest->total_teams,
            'status'          => $contest->status,
            'points_status'   => $contest->points_status,
            'deadline_at'     => $contest->deadline_at?->toIso8601String(),
            'slots_left'      => $contest->max_teams === 0
                ? null
                : max(0, $contest->max_teams - $contest->total_teams),
            'is_full'         => ! $contest->hasSlots(),
            'deadline_passed' => $contest->isDeadlinePassed(),
        ];

        // Include match summary if loaded
        if ($contest->relationLoaded('match') && $contest->match) {
            $match = $contest->match;
            $data['match'] = [
                'id'         => $match->id,
                'status'     => $match->status,
                'start_time' => $match->start_time?->toIso8601String(),
                'home_team'  => $match->homeTeam ? [
                    'id'       => $match->homeTeam->id,
                    'name'     => $match->homeTeam->name,
                    'logo_url' => $match->homeTeam->logo_url,
                ] : null,
                'away_team'  => $match->awayTeam ? [
                    'id'       => $match->awayTeam->id,
                    'name'     => $match->awayTeam->name,
                    'logo_url' => $match->awayTeam->logo_url,
                ] : null,
            ];
        }

        return $data;
    }
}