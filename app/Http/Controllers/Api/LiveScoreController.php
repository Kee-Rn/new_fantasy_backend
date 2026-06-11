<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BallByBall;
use App\Models\GameMatch;
use App\Models\MatchPlayer;
use Illuminate\Http\JsonResponse;

class LiveScoreController extends Controller
{
    // ── Live score snapshot ────────────────────────────────────────────────
    // GET /api/matches/{matchId}/live-score
    //
    // Lightweight polling endpoint — call every 5–10 seconds.
    // Returns current score for both innings, the current over's
    // balls, and who is batting/bowling right now.

    public function snapshot(int $matchId): JsonResponse
    {
        $match = GameMatch::with(['homeTeam', 'awayTeam', 'battingFirstTeam'])
            ->findOrFail($matchId);

        // Determine which innings is active
        $currentInnings = $this->currentInnings($matchId);

        $innings1 = $match->liveScoreForInnings(1);
        $innings2 = $match->liveScoreForInnings(2);

        // Current over balls (last 6 legal deliveries of the active innings)
        $currentOverBalls = $this->currentOverBalls($matchId, $currentInnings);

        // Last 12 balls across the active innings (for recent activity feed)
        $recentBalls = $this->recentBalls($matchId, $currentInnings, 12);

        // Who is at the crease / bowling right now
        $livePlayers = $this->livePlayers($matchId, $currentInnings);

        return response()->json([
            'match_id'        => $match->id,
            'status'          => $match->status,
            'current_innings' => $currentInnings,
            'innings_1'       => [
                'batting_team' => $match->battingFirstTeam ? [
                    'id'   => $match->battingFirstTeam->id,
                    'name' => $match->battingFirstTeam->name,
                ] : null,
                'score' => $innings1,
            ],
            'innings_2'       => [
                'batting_team' => $match->battingFirstTeam ? [
                    'id'   => $match->battingFirstTeam->id === $match->home_team_id
                        ? $match->away_team_id
                        : $match->home_team_id,
                    'name' => $match->battingFirstTeam->id === $match->home_team_id
                        ? $match->awayTeam->name
                        : $match->homeTeam->name,
                ] : null,
                'score' => $innings2,
            ],
            'current_over'    => $currentOverBalls,
            'recent_balls'    => $recentBalls,
            'live_players'    => $livePlayers,
        ]);
    }

    // ── Full scorecard ─────────────────────────────────────────────────────
    // GET /api/matches/{matchId}/live-score/scorecard
    //
    // Heavier endpoint — call every 30 seconds or on demand.
    // Returns batting, bowling, and fielding stats for all players.

    public function scorecard(int $matchId): JsonResponse
    {
        $match = GameMatch::with(['homeTeam', 'awayTeam'])->findOrFail($matchId);

        $matchPlayers = MatchPlayer::with(['player', 'team', 'performance'])
            ->where('match_id', $matchId)
            ->get();

        // Split by team
        $byTeam = $matchPlayers->groupBy('team_id')->map(function ($players, $teamId) {
            $team = $players->first()->team;

            $batting = $players
                ->filter(fn ($mp) => $mp->performance && $mp->performance->balls_faced > 0)
                ->sortByDesc(fn ($mp) => $mp->performance->runs)
                ->map(fn ($mp) => $this->formatBatting($mp))
                ->values();

            $bowling = $players
                ->filter(fn ($mp) => $mp->performance && $mp->performance->overs > 0)
                ->sortByDesc(fn ($mp) => $mp->performance->wickets)
                ->map(fn ($mp) => $this->formatBowling($mp))
                ->values();

            return [
                'team'    => ['id' => $team->id, 'name' => $team->name],
                'batting' => $batting,
                'bowling' => $bowling,
            ];
        })->values();

        return response()->json([
            'match_id'  => $matchId,
            'status'    => $match->status,
            'scorecard' => $byTeam,
        ]);
    }

    // ── Ball-by-ball history for an innings ────────────────────────────────
    // GET /api/matches/{matchId}/live-score/innings/{innings}
    //
    // Full ball-by-ball log for a specific innings.
    // Grouped by over for easy rendering.

    public function inningsBalls(int $matchId, int $innings): JsonResponse
    {
        GameMatch::findOrFail($matchId); // 404 if match doesn't exist

        $balls = BallByBall::with(['batsman', 'bowler', 'dismissedPlayer', 'fielder'])
            ->where('match_id', $matchId)
            ->where('innings', $innings)
            ->orderBy('over_number')
            ->orderBy('ball_number')
            ->get();

        // Group by over
        $overs = $balls->groupBy('over_number')->map(function ($overBalls, $overNumber) {
            $last = $overBalls->last();

            return [
                'over_number'  => $overNumber + 1, // display as 1-based
                'runs_in_over' => $overBalls->sum('runs_off_bat') + $overBalls->sum('extra_runs'),
                'wickets'      => $overBalls->where('is_wicket', true)->count(),
                'score_at_end' => [
                    'runs'    => $last->total_runs_after,
                    'wickets' => $last->total_wickets_after,
                ],
                'balls' => $overBalls->map(fn ($b) => $this->formatBall($b))->values(),
            ];
        })->values();

        return response()->json([
            'match_id' => $matchId,
            'innings'  => $innings,
            'overs'    => $overs,
            'total_balls' => $balls->count(),
        ]);
    }

    // ── Private helpers ────────────────────────────────────────────────────

    /**
     * Detect which innings is currently active based on ball_by_ball data.
     */
    private function currentInnings(int $matchId): int
    {
        $latestBall = BallByBall::where('match_id', $matchId)
            ->latest('id')
            ->first();

        return $latestBall ? $latestBall->innings : 1;
    }

    /**
     * Get the balls of the current (in-progress) over.
     */
    private function currentOverBalls(int $matchId, int $innings): array
    {
        $latestBall = BallByBall::where('match_id', $matchId)
            ->where('innings', $innings)
            ->latest('id')
            ->first();

        if (! $latestBall) {
            return ['over_number' => 1, 'balls' => []];
        }

        $currentOver = $latestBall->over_number;

        $balls = BallByBall::with(['batsman', 'bowler'])
            ->where('match_id', $matchId)
            ->where('innings', $innings)
            ->where('over_number', $currentOver)
            ->orderBy('ball_number')
            ->get();

        return [
            'over_number' => $currentOver + 1,
            'balls'       => $balls->map(fn ($b) => $this->formatBall($b))->values(),
        ];
    }

    /**
     * Get the last N balls across the active innings.
     */
    private function recentBalls(int $matchId, int $innings, int $limit): array
    {
        return BallByBall::with(['batsman', 'bowler'])
            ->where('match_id', $matchId)
            ->where('innings', $innings)
            ->latest('id')
            ->limit($limit)
            ->get()
            ->reverse()
            ->map(fn ($b) => $this->formatBall($b))
            ->values()
            ->toArray();
    }

    /**
     * Who is currently batting and bowling — based on the last ball.
     */
    private function livePlayers(int $matchId, int $innings): array
    {
        $lastBall = BallByBall::with(['batsman', 'bowler'])
            ->where('match_id', $matchId)
            ->where('innings', $innings)
            ->latest('id')
            ->first();

        if (! $lastBall) {
            return ['batsmen' => [], 'bowler' => null];
        }

        // Find the other batsman at the crease (last ball of current over
        // where batsman_id differs from striker)
        $nonStrikerId = BallByBall::where('match_id', $matchId)
            ->where('innings', $innings)
            ->where('batsman_id', '!=', $lastBall->batsman_id)
            ->where('is_wicket', false)
            ->latest('id')
            ->value('batsman_id');

        $batsmen = collect([$lastBall->batsman]);

        if ($nonStrikerId && $nonStrikerId !== $lastBall->batsman_id) {
            $nonStriker = $lastBall->batsman->newQuery()->find($nonStrikerId);
            if ($nonStriker) {
                $batsmen->push($nonStriker);
            }
        }

        return [
            'batsmen' => $batsmen->map(fn ($p) => [
                'id'   => $p->id,
                'name' => $p->name,
                'role' => $p->role,
            ])->values(),
            'bowler' => [
                'id'   => $lastBall->bowler->id,
                'name' => $lastBall->bowler->name,
                'role' => $lastBall->bowler->role,
            ],
        ];
    }

    private function formatBall(BallByBall $ball): array
    {
        return [
            'over_label'          => $ball->over_label,
            'batsman'             => $ball->batsman ? ['id' => $ball->batsman->id, 'name' => $ball->batsman->name] : null,
            'bowler'              => $ball->bowler  ? ['id' => $ball->bowler->id,  'name' => $ball->bowler->name]  : null,
            'runs_off_bat'        => $ball->runs_off_bat,
            'extra_type'          => $ball->extra_type,
            'extra_runs'          => $ball->extra_runs,
            'total_runs'          => $ball->total_runs,
            'is_four'             => $ball->is_four,
            'is_six'              => $ball->is_six,
            'is_wicket'           => $ball->is_wicket,
            'wicket_type'         => $ball->wicket_type,
            'dismissed_player'    => $ball->dismissed_player_id && $ball->dismissedPlayer
                ? ['id' => $ball->dismissedPlayer->id, 'name' => $ball->dismissedPlayer->name]
                : null,
            'score_after'         => [
                'runs'    => $ball->total_runs_after,
                'wickets' => $ball->total_wickets_after,
            ],
            'notes' => $ball->notes,
        ];
    }

    private function formatBatting(MatchPlayer $mp): array
    {
        $p = $mp->performance;

        return [
            'player_id'     => $mp->player->id,
            'name'          => $mp->player->name,
            'runs'          => $p->runs,
            'balls_faced'   => $p->balls_faced,
            'fours'         => $p->fours,
            'sixes'         => $p->sixes,
            'strike_rate'   => $p->batting_strike_rate,
            'out_status'    => $p->out_status,
            'is_duck'       => $p->is_duck,
            'is_fifty'      => $p->is_half_century,
            'is_century'    => $p->is_century,
            'fantasy_points' => $p->fantasy_points,
        ];
    }

    private function formatBowling(MatchPlayer $mp): array
    {
        $p = $mp->performance;

        return [
            'player_id'      => $mp->player->id,
            'name'           => $mp->player->name,
            'overs'          => $p->overs,
            'maidens'        => $p->maidens,
            'runs_conceded'  => $p->bowling_runs,
            'wickets'        => $p->wickets,
            'economy'        => $p->bowling_economy,
            'wides'          => $p->wides,
            'no_balls'       => $p->no_balls,
            'is_fifer'       => $p->is_fifer,
            'fantasy_points' => $p->fantasy_points,
        ];
    }
}