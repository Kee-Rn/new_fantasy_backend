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
    // Returns all teams ranked by total_points descending.
    // If the logged-in user has a team in this contest, their entry is
    // flagged with is_my_team: true so the frontend can highlight it.

    public function index(Request $request, int $contestId): JsonResponse
    {
        $contest = FantasyContest::findOrFail($contestId);

        $teams = FantasyTeam::with('user')
            ->where('contest_id', $contestId)
            ->orderByDesc('total_points')
            ->get();

        $authUserId = optional($request->user())->id;

        // Assign live ranks (ties get the same rank)
        $ranked = $this->assignRanks($teams);

        return response()->json([
            'contest_id'    => $contestId,
            'points_status' => $contest->points_status,
            'total_teams'   => $teams->count(),
            'leaderboard'   => $ranked->map(function ($team) use ($authUserId) {
                return [
                    'rank'         => $team->_rank,
                    'fantasy_team_id' => $team->id,
                    'team_name'    => $team->team_name,
                    'total_points' => $team->total_points,
                    'user'         => [
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
    // Auth: not required (any team can be viewed)
    //
    // Returns the full team card: all 11 players with their individual
    // fantasy points, captain/VC flags, and performance stats.

    public function teamCard(int $fantasyTeamId): JsonResponse
    {
        $fantasyTeam = FantasyTeam::with([
            'user',
            'contest.match',
            'players.team',
        ])->findOrFail($fantasyTeamId);

        $matchId = $fantasyTeam->contest->match_id;

        // Load performances for all players in this match in one query
        $performances = MatchPlayer::with('performance')
            ->where('match_id', $matchId)
            ->whereIn('player_id', $fantasyTeam->players->pluck('id'))
            ->get()
            ->keyBy('player_id');

        $players = $fantasyTeam->players->map(function ($player) use ($performances) {
            $matchPlayer  = $performances->get($player->id);
            $performance  = $matchPlayer?->performance;

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
                'captain'      => $players->firstWhere('is_captain', true),
                'vice_captain' => $players->firstWhere('is_vice_captain', true),
                'players'      => $players,
            ],
        ]);
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    /**
     * Assign ranks to an ordered collection of teams.
     * Teams with equal points share the same rank.
     */
    private function assignRanks($teams)
    {
        $rank        = 1;
        $prevPoints  = null;
        $sameRankCount = 0;

        return $teams->map(function ($team, $index) use (&$rank, &$prevPoints, &$sameRankCount) {
            if ($team->total_points === $prevPoints) {
                $sameRankCount++;
            } else {
                $rank         = $rank + $sameRankCount;
                $sameRankCount = 0;

                if ($prevPoints !== null) {
                    $rank++;
                }
            }

            $team->_rank  = $rank;
            $prevPoints   = $team->total_points;

            return $team;
        });
    }

    private function formatPerformance($p): array
    {
        return [
            // Batting
            'runs'                 => $p->runs,
            'balls_faced'          => $p->balls_faced,
            'fours'                => $p->fours,
            'sixes'                => $p->sixes,
            'out_status'           => $p->out_status,
            'batting_strike_rate'  => $p->batting_strike_rate,
            'is_duck'              => $p->is_duck,
            'is_half_century'      => $p->is_half_century,
            'is_century'           => $p->is_century,
            // Bowling
            'overs'                => $p->overs,
            'bowling_runs'         => $p->bowling_runs,
            'wickets'              => $p->wickets,
            'maidens'              => $p->maidens,
            'bowling_economy'      => $p->bowling_economy,
            'is_fifer'             => $p->is_fifer,
            // Fielding
            'catches'              => $p->catches,
            'stumpings'            => $p->stumpings,
            'run_outs'             => $p->run_outs,
            // Total
            'fantasy_points'       => $p->fantasy_points,
        ];
    }
}