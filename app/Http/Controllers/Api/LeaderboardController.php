<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FantasyContest;
use App\Models\FantasyTeam;
use App\Models\MatchPlayer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeaderboardController extends Controller
{
    // ── Contest leaderboard ────────────────────────────────────────────────
    // GET /api/contests/{contestId}/leaderboard
    // Auth: not required (public standings)
    //
    // Returns teams ranked by rank asc / total_points desc, paginated.
    // Supports ?per_page=N (default 50, hard cap 100) and ?page=N.
    // If the logged-in user has a team in this contest, their entry is
    // flagged with is_my_team: true so the frontend can highlight it.

    public function index(Request $request, int $contestId): JsonResponse
    {
        $contest = FantasyContest::findOrFail($contestId);

        $perPage = min((int) $request->input('per_page', 50), 100);

        $teams = FantasyTeam::with('user')
            ->where('contest_id', $contestId)
            ->orderByRaw('rank IS NULL, rank ASC') // ranked teams first, nulls last
            ->orderByDesc('total_points')           // tiebreak within same rank
            ->paginate($perPage);

        $authUserId = optional($request->user())->id;

        return response()->json([
            'contest_id'    => $contestId,
            'points_status' => $contest->points_status,
            'total_teams'   => $teams->total(),
            'current_page'  => $teams->currentPage(),
            'last_page'     => $teams->lastPage(),
            'per_page'      => $teams->perPage(),
            'leaderboard'   => collect($teams->items())->map(function ($team) use ($authUserId) {
                return [
                    'rank'            => $team->rank ?? null, // null when points not yet calculated
                    'fantasy_team_id' => $team->id,
                    'team_name'       => $team->team_name,
                    'total_points'    => $team->total_points,
                    'user'            => [
                        'id'   => $team->user->id,
                        'name' => $team->user->name,
                    ],
                    'is_my_team' => $authUserId && $team->user_id === $authUserId,
                ];
            })->values(),
        ]);
    }

    // ── Team card ──────────────────────────────────────────────────────────
    // GET /api/fantasy-teams/{fantasyTeamId}/card
    // Auth: required
    //
    // Pre-deadline:  only the team owner can view their own team.
    // Post-deadline: any authenticated user can view any team.
    //
    // This prevents copying another user's captain/VC selection and
    // full 11-player lineup before the contest locks.

    public function teamCard(Request $request, int $fantasyTeamId): JsonResponse
    {
        $fantasyTeam = FantasyTeam::with([
            'user',
            'contest.match',
            'players.team',
        ])->findOrFail($fantasyTeamId);

        $contest        = $fantasyTeam->contest;
        $deadlinePassed = $contest->isDeadlinePassed();
        $isOwner        = $request->user()->id === $fantasyTeam->user_id;

        // Pre-deadline: block anyone who isn't the owner
        if (! $deadlinePassed && ! $isOwner) {
            return response()->json([
                'message' => 'Team details are hidden until the contest deadline passes.',
            ], 403);
        }

        $matchId = $contest->match_id;

        // Load performances for all 11 players in one query
        $performances = MatchPlayer::with('performance')
            ->where('match_id', $matchId)
            ->whereIn('player_id', $fantasyTeam->players->pluck('id'))
            ->get()
            ->keyBy('player_id');

        $players = $fantasyTeam->players->map(function ($player) use ($performances) {
            $matchPlayer = $performances->get($player->id);
            $performance = $matchPlayer?->performance;

            return [
                'id'              => $player->id,
                'name'            => $player->name,
                'role'            => $player->role,
                'photo_url'       => $player->photo_url,
                'team_id'         => $player->team_id,
                'team_name'       => $player->team->name ?? null,
                'is_captain'      => (bool) $player->pivot->is_captain,
                'is_vice_captain' => (bool) $player->pivot->is_vice_captain,
                'base_points'     => $player->pivot->base_points,
                'points'          => $player->pivot->points,
                'performance'     => $performance ? $this->formatPerformance($performance) : null,
            ];
        })->sortByDesc('points')->values();

        return response()->json([
            'fantasy_team' => [
                'id'           => $fantasyTeam->id,
                'team_name'    => $fantasyTeam->team_name,
                'total_points' => $fantasyTeam->total_points,
                'rank'         => $fantasyTeam->rank,
                'user'         => [
                    'id'   => $fantasyTeam->user->id,
                    'name' => $fantasyTeam->user->name,
                ],
                'contest_id'   => $fantasyTeam->contest_id,
                // captain/VC always present here — the 403 gate above ensures
                // only the owner sees this pre-deadline, everyone post-deadline
                'captain'      => $players->firstWhere('is_captain', true),
                'vice_captain' => $players->firstWhere('is_vice_captain', true),
                'players'      => $players,
            ],
        ]);
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    private function formatPerformance($p): array
    {
        return [
            // Batting
            'runs'                => $p->runs,
            'balls_faced'         => $p->balls_faced,
            'fours'               => $p->fours,
            'sixes'               => $p->sixes,
            'out_status'          => $p->out_status,
            'batting_strike_rate' => $p->batting_strike_rate,
            'is_duck'             => $p->is_duck,
            'is_half_century'     => $p->is_half_century,
            'is_century'          => $p->is_century,
            // Bowling
            'overs'               => $p->overs,
            'bowling_runs'        => $p->bowling_runs,
            'wickets'             => $p->wickets,
            'maidens'             => $p->maidens,
            'bowling_economy'     => $p->bowling_economy,
            'is_fifer'            => $p->is_fifer,
            // Fielding
            'catches'             => $p->catches,
            'stumpings'           => $p->stumpings,
            'run_outs'            => $p->run_outs,
            // Total
            'fantasy_points'      => $p->fantasy_points,
        ];
    }
}