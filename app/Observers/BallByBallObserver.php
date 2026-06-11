<?php

namespace App\Observers;

use App\Models\BallByBall;
use App\Models\FantasyContest;
use App\Services\Cricket\FantasyPointsService;
use Illuminate\Support\Facades\Log;

class BallByBallObserver
{
    public function __construct(
        protected FantasyPointsService $pointsService
    ) {}

    /**
     * Fires when admin saves a new ball via the CMS.
     * Recalculates fantasy points for all active contests in that match.
     */
    public function created(BallByBall $ball): void
    {
        $this->recalculate($ball);
    }

    /**
     * Fires when admin corrects an existing ball entry.
     * Recalculates from scratch so corrections propagate instantly.
     */
    public function updated(BallByBall $ball): void
    {
        $this->recalculate($ball);
    }

    /**
     * Fires when admin deletes a ball (e.g. entered by mistake).
     */
    public function deleted(BallByBall $ball): void
    {
        $this->recalculate($ball);
    }

    // ── Core logic ─────────────────────────────────────────────────────────

    private function recalculate(BallByBall $ball): void
    {
        $match = $ball->match;

        if (! $match) {
            return;
        }

        // Find all eligible contests for this match
        $contests = FantasyContest::where('match_id', $match->id)
            ->whereIn('status', ['active', 'completed'])
            ->get();

        if ($contests->isEmpty()) {
            return;
        }

        try {
            // Step 1 & 2: re-aggregate ball_by_ball → player_performances
            // and re-score all performances
            $this->pointsService->aggregateAndScore($match);

            // Step 3–6: distribute points, sum totals, rank teams
            foreach ($contests as $contest) {
                $this->pointsService->processContest($contest);
            }

            Log::info("BallByBallObserver: points recalculated for match {$match->id} after ball {$ball->id}");

        } catch (\Throwable $e) {
            // Never let a points error crash the admin's ball entry
            Log::error("BallByBallObserver: failed for match {$match->id} — {$e->getMessage()}");
        }
    }
}