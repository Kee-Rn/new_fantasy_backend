<?php

namespace App\Services\Cricket;

use App\Models\PlayerPerformance;

/**
 * PointsCalculator
 *
 * Pure scoring engine — takes a PlayerPerformance and returns an integer
 * fantasy points total. No DB writes. No side effects.
 *
 * All point values are defined as constants at the top so they are easy
 * to tweak from the CMS or a config file later.
 *
 * Usage:
 *   $points = app(PointsCalculator::class)->calculate($performance);
 */
class PointsCalculator
{
    // ── Batting ──────────────────────────────────────────────────────────────
    const POINTS_PER_RUN            = 1;
    const POINTS_PER_FOUR           = 1;    // boundary bonus
    const POINTS_PER_SIX            = 2;    // boundary bonus
    const POINTS_HALF_CENTURY       = 8;    // 50–99 runs milestone
    const POINTS_CENTURY            = 16;   // 100+ runs milestone
    const POINTS_DUCK               = -2;   // out for 0 (only BAT, ALL, WK)

    // Strike rate bonus/penalty (min 10 balls faced)
    const SR_BONUS_ABOVE_170        = 6;
    const SR_BONUS_150_TO_170       = 4;
    const SR_BONUS_130_TO_150       = 2;
    const SR_PENALTY_BELOW_50       = -6;
    const SR_PENALTY_50_TO_60       = -4;
    const SR_PENALTY_60_TO_70       = -2;
    const SR_MIN_BALLS_FOR_RATE     = 10;   // must face at least 10 balls for SR bonus/penalty

    // ── Bowling ──────────────────────────────────────────────────────────────
    const POINTS_PER_WICKET         = 25;
    const POINTS_LBW_OR_BOWLED      = 8;    // bonus per LBW/bowled wicket
    const POINTS_PER_MAIDEN         = 4;
    const POINTS_THREE_WICKET_HAUL  = 4;
    const POINTS_FOUR_WICKET_HAUL   = 8;
    const POINTS_FIVE_WICKET_HAUL   = 16;

    // Economy rate bonus/penalty (min 2 overs bowled)
    const ECO_BONUS_BELOW_5         = 6;
    const ECO_BONUS_5_TO_6          = 4;
    const ECO_BONUS_6_TO_7          = 2;
    const ECO_PENALTY_ABOVE_12      = -6;
    const ECO_PENALTY_10_TO_12      = -4;
    const ECO_PENALTY_9_TO_10       = -2;
    const ECO_MIN_OVERS             = 2.0;

    // ── Fielding ─────────────────────────────────────────────────────────────
    const POINTS_PER_CATCH          = 8;
    const POINTS_THREE_CATCHES      = 4;    // bonus for 3+ catches in a match
    const POINTS_PER_STUMPING       = 12;
    const POINTS_PER_RUN_OUT        = 6;

    // ── Appearance ───────────────────────────────────────────────────────────
    const POINTS_PLAYING_XI         = 4;    // awarded just for being in the playing 11

    // ── Entry point ──────────────────────────────────────────────────────────

    /**
     * Calculate total fantasy points for a player's performance.
     */
    public function calculate(PlayerPerformance $performance): int
    {
        // Player did not participate at all
        if ($performance->out_status === 'dnb' && $performance->overs == 0) {
            return 0;
        }

        $points = self::POINTS_PLAYING_XI;

        $points += $this->battingPoints($performance);
        $points += $this->bowlingPoints($performance);
        $points += $this->fieldingPoints($performance);

        return max(0, $points); // never go below 0
    }

    // ── Batting ──────────────────────────────────────────────────────────────

    private function battingPoints(PlayerPerformance $p): int
    {
        $points = 0;

        // Base runs
        $points += $p->runs * self::POINTS_PER_RUN;

        // Boundary bonuses
        $points += $p->fours * self::POINTS_PER_FOUR;
        $points += $p->sixes * self::POINTS_PER_SIX;

        // Milestone bonuses (only one applies — pick the higher)
        if ($p->runs >= 100) {
            $points += self::POINTS_CENTURY;
        } elseif ($p->runs >= 50) {
            $points += self::POINTS_HALF_CENTURY;
        }

        // Duck penalty — only for recognised batting roles, not tailenders
        if ($p->is_duck) {
            $role = $p->matchPlayer?->player?->role;
            if (in_array($role, ['WK', 'BAT', 'ALL'])) {
                $points += self::POINTS_DUCK;
            }
        }

        // Strike rate bonus/penalty
        if ($p->balls_faced >= self::SR_MIN_BALLS_FOR_RATE) {
            $points += $this->strikeRatePoints($p->batting_strike_rate);
        }

        return $points;
    }

    private function strikeRatePoints(float $sr): int
    {
        if ($sr > 170) {
            return self::SR_BONUS_ABOVE_170;
        }
        if ($sr >= 150) {
            return self::SR_BONUS_150_TO_170;
        }
        if ($sr >= 130) {
            return self::SR_BONUS_130_TO_150;
        }
        if ($sr < 50) {
            return self::SR_PENALTY_BELOW_50;
        }
        if ($sr < 60) {
            return self::SR_PENALTY_50_TO_60;
        }
        if ($sr < 70) {
            return self::SR_PENALTY_60_TO_70;
        }

        return 0; // 70–129: no bonus/penalty
    }

    // ── Bowling ──────────────────────────────────────────────────────────────

    private function bowlingPoints(PlayerPerformance $p): int
    {
        if ($p->overs == 0) {
            return 0;
        }

        $points = 0;

        // Base wicket points
        $points += $p->wickets * self::POINTS_PER_WICKET;

        // LBW / bowled bonus — per qualifying wicket
        if ($p->lbw_or_bowled) {
            // We store only a boolean, not the count of lbw/bowled wickets.
            // Award the bonus once. If you later store a count, multiply here.
            $points += self::POINTS_LBW_OR_BOWLED;
        }

        // Maiden overs
        $points += $p->maidens * self::POINTS_PER_MAIDEN;

        // Wicket haul milestones (non-cumulative — pick the highest bracket)
        if ($p->wickets >= 5) {
            $points += self::POINTS_FIVE_WICKET_HAUL;
        } elseif ($p->wickets >= 4) {
            $points += self::POINTS_FOUR_WICKET_HAUL;
        } elseif ($p->wickets >= 3) {
            $points += self::POINTS_THREE_WICKET_HAUL;
        }

        // Economy rate bonus/penalty (min 2 overs)
        if ($p->overs >= self::ECO_MIN_OVERS) {
            $points += $this->economyRatePoints($p->bowling_economy);
        }

        return $points;
    }

    private function economyRatePoints(float $eco): int
    {
        if ($eco < 5) {
            return self::ECO_BONUS_BELOW_5;
        }
        if ($eco < 6) {
            return self::ECO_BONUS_5_TO_6;
        }
        if ($eco < 7) {
            return self::ECO_BONUS_6_TO_7;
        }
        if ($eco > 12) {
            return self::ECO_PENALTY_ABOVE_12;
        }
        if ($eco > 10) {
            return self::ECO_PENALTY_10_TO_12;
        }
        if ($eco > 9) {
            return self::ECO_PENALTY_9_TO_10;
        }

        return 0; // 7–9: no bonus/penalty
    }

    // ── Fielding ─────────────────────────────────────────────────────────────

    private function fieldingPoints(PlayerPerformance $p): int
    {
        $points = 0;

        $points += $p->catches   * self::POINTS_PER_CATCH;
        $points += $p->stumpings * self::POINTS_PER_STUMPING;
        $points += $p->run_outs  * self::POINTS_PER_RUN_OUT;

        // 3-catch bonus in the same match
        if ($p->catches >= 3) {
            $points += self::POINTS_THREE_CATCHES;
        }

        return $points;
    }

    // ── Breakdown (for CMS display) ───────────────────────────────────────────

    /**
     * Returns an itemised breakdown of points so CMS can show the user
     * exactly where each point came from.
     *
     * Returns array<string, int>  e.g. ['runs' => 45, 'six_bonus' => 6, ...]
     */
    public function breakdown(PlayerPerformance $p): array
    {
        $items = ['playing_xi' => self::POINTS_PLAYING_XI];

        // Batting
        $items['runs']          = $p->runs * self::POINTS_PER_RUN;
        $items['four_bonus']    = $p->fours * self::POINTS_PER_FOUR;
        $items['six_bonus']     = $p->sixes * self::POINTS_PER_SIX;
        $items['milestone']     = $p->runs >= 100
            ? self::POINTS_CENTURY
            : ($p->runs >= 50 ? self::POINTS_HALF_CENTURY : 0);

        $role = $p->matchPlayer?->player?->role;
        $items['duck_penalty']  = ($p->is_duck && in_array($role, ['WK', 'BAT', 'ALL']))
            ? self::POINTS_DUCK : 0;

        $items['strike_rate']   = ($p->balls_faced >= self::SR_MIN_BALLS_FOR_RATE && $p->batting_strike_rate)
            ? $this->strikeRatePoints($p->batting_strike_rate) : 0;

        // Bowling
        $items['wickets']       = $p->wickets * self::POINTS_PER_WICKET;
        $items['lbw_bowled']    = $p->lbw_or_bowled ? self::POINTS_LBW_OR_BOWLED : 0;
        $items['maidens']       = $p->maidens * self::POINTS_PER_MAIDEN;
        $items['wicket_haul']   = match (true) {
            $p->wickets >= 5 => self::POINTS_FIVE_WICKET_HAUL,
            $p->wickets >= 4 => self::POINTS_FOUR_WICKET_HAUL,
            $p->wickets >= 3 => self::POINTS_THREE_WICKET_HAUL,
            default          => 0,
        };
        $items['economy']       = ($p->overs >= self::ECO_MIN_OVERS && $p->bowling_economy)
            ? $this->economyRatePoints($p->bowling_economy) : 0;

        // Fielding
        $items['catches']       = $p->catches * self::POINTS_PER_CATCH;
        $items['three_catch']   = $p->catches >= 3 ? self::POINTS_THREE_CATCHES : 0;
        $items['stumpings']     = $p->stumpings * self::POINTS_PER_STUMPING;
        $items['run_outs']      = $p->run_outs  * self::POINTS_PER_RUN_OUT;

        $items['total']         = array_sum($items);

        return $items;
    }
}