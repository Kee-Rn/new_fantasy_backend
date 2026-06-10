<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GameMatch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MatchController extends Controller
{
    // ── List matches (with optional status filter) ─────────────────────────
    // GET /api/matches
    // GET /api/matches?status=upcoming
    // GET /api/matches?status=live
    // GET /api/matches?status=completed
    // GET /api/matches?featured=1

    public function index(Request $request): JsonResponse
    {
        $query = GameMatch::with(['league', 'homeTeam', 'awayTeam'])
            ->orderBy('start_time', 'asc');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->boolean('featured')) {
            $query->where('is_featured', true);
        }

        $matches = $query->get();

        return response()->json([
            'matches' => $matches->map(fn ($m) => $this->formatMatch($m)),
        ]);
    }

    // ── Single match with its contests ────────────────────────────────────
    // GET /api/matches/{id}

    public function show(int $id): JsonResponse
    {
        $match = GameMatch::with([
            'league',
            'homeTeam',
            'awayTeam',
            'fantasyContests',
        ])->findOrFail($id);

        return response()->json([
            'match'    => $this->formatMatch($match),
            'contests' => $match->fantasyContests->map(fn ($c) => $this->formatContest($c)),
        ]);
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    private function formatMatch(GameMatch $match): array
    {
        return [
            'id'          => $match->id,
            'status'      => $match->status,
            'start_time'  => $match->start_time?->toIso8601String(),
            'is_featured' => $match->is_featured,
            'result'      => $match->result,
            'result_type' => $match->result_type,
            'toss_winner' => $match->toss_winner,
            'toss_decision' => $match->toss_decision,
            'league' => $match->league ? [
                'id'   => $match->league->id,
                'name' => $match->league->name,
            ] : null,
            'home_team' => $match->homeTeam ? [
                'id'       => $match->homeTeam->id,
                'name'     => $match->homeTeam->name,
                'logo_url' => $match->homeTeam->logo_url,
            ] : null,
            'away_team' => $match->awayTeam ? [
                'id'       => $match->awayTeam->id,
                'name'     => $match->awayTeam->name,
                'logo_url' => $match->awayTeam->logo_url,
            ] : null,
        ];
    }

    private function formatContest(mixed $contest): array
    {
        return [
            'id'          => $contest->id,
            'name'        => $contest->name,
            'entry_fee'   => $contest->entry_fee,
            'prize_pool'  => $contest->prize_pool,
            'max_teams'   => $contest->max_teams,
            'total_teams' => $contest->total_teams,
            'status'      => $contest->status,
            'deadline_at' => $contest->deadline_at?->toIso8601String(),
            'slots_left'  => $contest->max_teams === 0
                ? null
                : max(0, $contest->max_teams - $contest->total_teams),
            'is_full'        => ! $contest->hasSlots(),
            'deadline_passed' => $contest->isDeadlinePassed(),
        ];
    }
}