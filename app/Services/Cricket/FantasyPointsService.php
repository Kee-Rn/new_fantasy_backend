<?php

namespace App\Services\Cricket;

use App\Models\FantasyContest;
use App\Models\FantasyTeam;
use App\Models\FantasyTeamPlayer;
use App\Models\GameMatch;
use App\Models\MatchPlayer;
use App\Models\PlayerPerformance;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * FantasyPointsService
 *
 * Orchestrates the full points pipeline for a match's fantasy contests:
 *
 *  Step 1 — Aggregate ball_by_ball → player_performances  (BallByBallStatsService)
 *  Step 2 — Score each performance                         (PointsCalculator)
 *  Step 3 — Distribute points to every fantasy_team_player (captain 2×, vc 1.5×)
 *  Step 4 — Sum fantasy_teams.total_points
 *  Step 5 — Rank teams within each contest
 *  Step 6 — Mark contest points_status = 'calculated'
 *
 * Usage (e.g. from a Filament action or Artisan command):
 *
 *   app(FantasyPointsService::class)->processMatch($match);
 *
 *   // Or for a single contest only:
 *   app(FantasyPointsService::class)->processContest($contest);
 */
class FantasyPointsService
{
    public function __construct(
        protected BallByBallStatsService $statsService,
        protected PointsCalculator       $calculator,
    ) {}

    // ── Public API ────────────────────────────────────────────────────────────

    /**
     * Run the full pipeline for all contests tied to a match.
     * Safe to re-run — everything is upserted / overwritten.
     */
    public function processMatch(GameMatch $match): void
    {
        $contests = $match->fantasyContests()
            ->whereIn('status', ['active', 'completed'])
            ->get();

        if ($contests->isEmpty()) {
            Log::info("FantasyPointsService: no eligible contests for match {$match->id}");
            return;
        }

        // Step 1 & 2: aggregate stats + compute fantasy_points on performances
        $this->aggregateAndScore($match);

        // Steps 3-6: distribute to every contest
        foreach ($contests as $contest) {
            $this->processContest($contest);
        }
    }

    /**
     * Run the points pipeline for a single contest.
     * Assumes player_performances are already populated (call processMatch
     * if you want stats aggregation included).
     */
    public function processContest(FantasyContest $contest): void
    {
        $contest->update(['points_status' => 'calculating']);

        try {
            DB::transaction(function () use ($contest) {

                // Step 3: distribute base_points + apply multipliers
                $this->distributePointsToTeams($contest);

                // Step 4: sum each fantasy team's total
                $this->sumFantasyTeamTotals($contest);

                // Step 5: assign ranks
                $this->assignRanks($contest);

            });

            // Step 6: mark done
            $contest->update([
                'points_status'        => 'calculated',
                'points_calculated_at' => now(),
            ]);

            Log::info("FantasyPointsService: contest {$contest->id} calculated successfully.");

        } catch (Throwable $e) {
            $contest->update(['points_status' => 'failed']);
            Log::error("FantasyPointsService: contest {$contest->id} failed — {$e->getMessage()}");
            throw $e;
        }
    }

    // ── Step 1 & 2: Aggregate stats and score performances ───────────────────

    /**
     * Aggregate ball_by_ball into player_performances, then run
     * PointsCalculator on each and persist fantasy_points.
     */
    public function aggregateAndScore(GameMatch $match): void
    {
        // Step 1: aggregate deliveries → player_performances (upsert)
        $performances = $this->statsService->aggregateForMatch($match);

        // Step 2: score each performance
        foreach ($performances as $performance) {
            $points = $this->calculator->calculate($performance);

            $performance->update(['fantasy_points' => $points]);
        }

        Log::info("FantasyPointsService: scored {$performances->count()} performances for match {$match->id}.");
    }

    // ── Step 3: Distribute points to fantasy_team_players ────────────────────

    /**
     * For every fantasy_team_player in the contest:
     *  - Look up the player's fantasy_points from player_performances
     *  - Store as base_points
     *  - Apply captain (2×) / vice-captain (1.5×) multiplier → points
     */
    private function distributePointsToTeams(FantasyContest $contest): void
    {
        // Build a map: player_id → fantasy_points
        // We resolve via: contest → match → match_players → player_performances
        $match = $contest->match;

        $performanceMap = PlayerPerformance::whereHas('matchPlayer', function ($q) use ($match) {
                $q->where('match_id', $match->id);
            })
            ->with('matchPlayer')
            ->get()
            ->keyBy(fn ($p) => $p->matchPlayer->player_id);
        // [ player_id => PlayerPerformance ]

        // Load all fantasy_team_players for this contest in one query
        $fantasyTeamPlayerRows = FantasyTeamPlayer::whereHas('fantasyTeam', function ($q) use ($contest) {
                $q->where('contest_id', $contest->id);
            })
            ->get();

        foreach ($fantasyTeamPlayerRows as $ftp) {
            $performance = $performanceMap[$ftp->player_id] ?? null;

            $basePoints = $performance?->fantasy_points ?? 0;

            $multiplier = match (true) {
                $ftp->is_captain      => 2.0,
                $ftp->is_vice_captain => 1.5,
                default               => 1.0,
            };

            $ftp->update([
                'base_points' => $basePoints,
                'points'      => (int) round($basePoints * $multiplier),
            ]);
        }
    }

    // ── Step 4: Sum fantasy team totals ──────────────────────────────────────

    private function sumFantasyTeamTotals(FantasyContest $contest): void
    {
        // Use a single UPDATE … SELECT for performance on large contests
        DB::statement("
            UPDATE fantasy_teams ft
            SET total_points = (
                SELECT COALESCE(SUM(ftp.points), 0)
                FROM fantasy_team_players ftp
                WHERE ftp.fantasy_team_id = ft.id
            )
            WHERE ft.contest_id = ?
        ", [$contest->id]);
    }

    // ── Step 5: Assign ranks ──────────────────────────────────────────────────

    /**
     * Rank teams by total_points descending.
     * Ties receive the same rank (standard competition ranking: 1,1,3,4…).
     */
    private function assignRanks(FantasyContest $contest): void
    {
        $teams = FantasyTeam::where('contest_id', $contest->id)
            ->orderByDesc('total_points')
            ->get();

        $rank        = 1;
        $prevPoints  = null;
        $sameRankCount = 0;

        foreach ($teams as $team) {
            if ($prevPoints !== null && $team->total_points < $prevPoints) {
                $rank += $sameRankCount;
                $sameRankCount = 1;
            } else {
                $sameRankCount++;
            }

            $team->update(['rank' => $rank]);
            $prevPoints = $team->total_points;
        }
    }

    // ── Utilities ─────────────────────────────────────────────────────────────

    /**
     * Get the full leaderboard for a contest with user + player details.
     * Returns a paginated result — pass $perPage = null for all rows.
     */
    public function leaderboard(FantasyContest $contest, ?int $perPage = 50)
    {
        $query = FantasyTeam::where('contest_id', $contest->id)
            ->with([
                'user',
                'fantasyTeamPlayers.player',
            ])
            ->orderBy('rank')
            ->orderByDesc('total_points');

        return $perPage ? $query->paginate($perPage) : $query->get();
    }

    /**
     * Get a single user's team card for a contest — their players,
     * points breakdown per player, and final rank.
     */
    public function teamCard(FantasyTeam $fantasyTeam): array
    {
        $fantasyTeam->load([
            'user',
            'contest.match',
            'fantasyTeamPlayers.player.team',
        ]);

        $players = $fantasyTeam->fantasyTeamPlayers->map(function (FantasyTeamPlayer $ftp) {
            return [
                'player_id'       => $ftp->player_id,
                'name'            => $ftp->player->name,
                'role'            => $ftp->player->role,
                'team'            => $ftp->player->team?->name,
                'is_captain'      => $ftp->is_captain,
                'is_vice_captain' => $ftp->is_vice_captain,
                'base_points'     => $ftp->base_points,
                'multiplier'      => $ftp->multiplier,
                'points'          => $ftp->points,
            ];
        });

        return [
            'fantasy_team_id' => $fantasyTeam->id,
            'team_name'       => $fantasyTeam->team_name,
            'user'            => $fantasyTeam->user->name,
            'total_points'    => $fantasyTeam->total_points,
            'rank'            => $fantasyTeam->rank,
            'players'         => $players,
        ];
    }

    /**
     * Recalculate a single contest from scratch — useful when CMS
     * corrects a ball-by-ball entry after points were already calculated.
     */
    public function recalculate(FantasyContest $contest): void
    {
        Log::info("FantasyPointsService: recalculating contest {$contest->id}");

        // Re-aggregate stats from ball_by_ball (overwrites player_performances)
        $this->aggregateAndScore($contest->match);

        // Re-run contest pipeline
        $this->processContest($contest);
    }
}