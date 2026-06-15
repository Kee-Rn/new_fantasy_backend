<?php

namespace App\Services\Cricket;

use App\Models\BallByBall;
use App\Models\GameMatch;
use App\Models\MatchPlayer;
use App\Models\PlayerPerformance;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * BallByBallStatsService
 *
 * Reads all ball_by_ball rows for a match and aggregates them into
 * player_performances rows. Safe to call multiple times — it upserts,
 * so re-running after a correction in the CMS just overwrites.
 *
 * Usage:
 *   app(BallByBallStatsService::class)->aggregateForMatch($match);
 */
class BallByBallStatsService
{
    /**
     * Aggregate all deliveries for a match and upsert player_performances.
     *
     * @return Collection<PlayerPerformance>  The upserted performance rows.
     */
    public function aggregateForMatch(GameMatch $match): Collection
    {
        $deliveries = BallByBall::where('match_id', $match->id)
            ->with(['batsman', 'bowler', 'dismissedPlayer', 'fielder'])
            ->orderBy('innings')
            ->orderBy('over_number')
            ->orderBy('ball_number')
            ->get();

        if ($deliveries->isEmpty()) {
            return collect();
        }

        // Build a lookup: player_id → match_player_id
        // so we can link stats to the correct match_player row.
        $matchPlayerMap = MatchPlayer::where('match_id', $match->id)
            ->pluck('id', 'player_id');   // [player_id => match_player_id]

        // Auto-create match_player entries for any player that appeared in
        // ball_by_ball but was never manually assigned in the CMS.
        $allBallPlayerIds = collect()
            ->merge($deliveries->pluck('batsman_id'))
            ->merge($deliveries->pluck('bowler_id'))
            ->merge($deliveries->pluck('dismissed_player_id'))
            ->merge($deliveries->pluck('fielder_id'))
            ->filter()
            ->unique();

        $missingPlayerIds = $allBallPlayerIds->diff($matchPlayerMap->keys());

        if ($missingPlayerIds->isNotEmpty()) {
            $players = \App\Models\Player::whereIn('id', $missingPlayerIds)->get()->keyBy('id');

            foreach ($missingPlayerIds as $playerId) {
                $player = $players[$playerId] ?? null;
                if (! $player) continue;

                $mp = MatchPlayer::firstOrCreate(
                    ['match_id' => $match->id, 'player_id' => $playerId],
                    [
                        'team_id'        => $player->team_id,
                        'is_playing_xi'  => true,
                        'batting_order'  => null,
                    ]
                );

                $matchPlayerMap[$playerId] = $mp->id;
            }
        }

        // ── Accumulators ────────────────────────────────────────────────────
        // Keyed by player_id.
        $batting  = [];   // batting stats
        $bowling  = [];   // bowling stats
        $fielding = [];   // catches, stumpings, run_outs

        foreach ($deliveries as $ball) {
            $this->accumulateBatting($ball, $batting);
            $this->accumulateBowling($ball, $bowling);
            $this->accumulateFielding($ball, $fielding);
        }

        // Finalise bowling: convert _balls → overs, compute maidens, remove _balls
        $this->finaliseBowling($bowling, $match);

        // ── Merge and upsert ─────────────────────────────────────────────────
        $performances = collect();

        $allPlayerIds = collect(array_keys($batting))
            ->merge(array_keys($bowling))
            ->merge(array_keys($fielding))
            ->unique();

        // If no deliveries exist at all, zero out every existing performance
        // for this match so points reflect the empty state correctly.
        if ($allPlayerIds->isEmpty()) {
            $matchPlayerIds = MatchPlayer::where('match_id', $match->id)->pluck('id');

            if ($matchPlayerIds->isNotEmpty()) {
                PlayerPerformance::whereIn('match_player_id', $matchPlayerIds)
                    ->update(array_merge(
                        $this->emptyBatting(),
                        $this->emptyBowling(),
                        $this->emptyFielding(),
                        [
                            'fantasy_points' => 0,
                            'out_status'     => 'dnb', // no balls = did not bat/bowl
                        ]
                    ));
            }

            return $performances; // empty collection
        }

        DB::transaction(function () use (
            $allPlayerIds, $batting, $bowling, $fielding,
            $matchPlayerMap, &$performances, $match
        ) {
            foreach ($allPlayerIds as $playerId) {
                $matchPlayerId = $matchPlayerMap[$playerId] ?? null;

                if (! $matchPlayerId) {
                    Log::warning("BallByBallStatsService: player {$playerId} in ball_by_ball but match_player auto-create failed for match {$match->id}.");
                    continue;
                }

                $bat  = $batting[$playerId]  ?? $this->emptyBatting();
                $bowl = $bowling[$playerId]  ?? $this->emptyBowling();
                $fld  = $fielding[$playerId] ?? $this->emptyFielding();

                $data = array_merge($bat, $bowl, $fld, [
                    'match_player_id' => $matchPlayerId,
                ]);

                $performance = PlayerPerformance::updateOrCreate(
                    ['match_player_id' => $matchPlayerId],
                    $data
                );

                $performances->push($performance);
            }

            // Zero out any player who had a performance row but no longer
            // appears in any ball (e.g. fielder whose caught ball was deleted)
            $processedMatchPlayerIds = collect($allPlayerIds->toArray())
                ->map(fn ($pid) => $matchPlayerMap[$pid] ?? null)
                ->filter()
                ->values();

            $staleMpIds = collect($matchPlayerMap->values())
                ->diff($processedMatchPlayerIds);

            if ($staleMpIds->isNotEmpty()) {
                PlayerPerformance::whereIn('match_player_id', $staleMpIds)
                    ->update(array_merge(
                        $this->emptyBatting(),
                        $this->emptyBowling(),
                        $this->emptyFielding(),
                        ['fantasy_points' => 0, 'out_status' => 'dnb']
                    ));
            }
        });

        return $performances;
    }

    // ── Private accumulators ─────────────────────────────────────────────────

    private function accumulateBatting(BallByBall $ball, array &$batting): void
    {
        $id = $ball->batsman_id;

        if (! isset($batting[$id])) {
            $batting[$id] = $this->emptyBatting();
        }

        $b = &$batting[$id];

        // Only count legal deliveries toward balls_faced
        if ($ball->is_legal_delivery) {
            $b['balls_faced']++;
        }

        $b['runs'] += $ball->runs_off_bat;

        if ($ball->is_four) {
            $b['fours']++;
        }

        if ($ball->is_six) {
            $b['sixes']++;
        }

        // Track dismissal
        if ($ball->is_wicket && $ball->dismissed_player_id === $id) {
            $b['out_status'] = 'out';
        }
    }

    private function accumulateBowling(BallByBall $ball, array &$bowling): void
    {
        $id = $ball->bowler_id;

        if (! isset($bowling[$id])) {
            $bowling[$id] = $this->emptyBowling();
            $bowling[$id]['_balls'] = 0; // internal counter, removed before DB upsert
        }

        $b = &$bowling[$id];

        $b['bowling_runs'] += $ball->runs_off_bat + $ball->extra_runs;

        if ($ball->extra_type === 'no_ball') {
            $b['no_balls']++;
        }

        if ($ball->extra_type === 'wide') {
            $b['wides']++;
            // Wides don't count as legal deliveries — don't increment ball count
            return;
        }

        if ($ball->extra_type !== 'no_ball') {
            // Legal delivery: increment ball count toward overs
            $b['_balls']++;
        }

        if ($ball->is_wicket && ! in_array($ball->wicket_type, ['run_out', 'retired_hurt', 'obstructing_field'])) {
            $b['wickets']++;

            if (in_array($ball->wicket_type, ['lbw', 'bowled'])) {
                $b['lbw_or_bowled'] = true;
            }
        }
    }

    private function accumulateFielding(BallByBall $ball, array &$fielding): void
    {
        if (! $ball->is_wicket) {
            return;
        }

        $fielderId = $ball->fielder_id;

        // ── Stumping ───────────────────────────────────────────────────────
        if ($ball->wicket_type === 'stumped' && $fielderId) {
            $fielding[$fielderId] ??= $this->emptyFielding();
            $fielding[$fielderId]['stumpings']++;
        }

        // ── Catch ──────────────────────────────────────────────────────────
        if (in_array($ball->wicket_type, ['caught', 'caught_and_bowled'])) {
            // For caught_and_bowled the bowler takes the catch
            $catcherId = $ball->wicket_type === 'caught_and_bowled'
                ? $ball->bowler_id
                : $fielderId;

            if ($catcherId) {
                $fielding[$catcherId] ??= $this->emptyFielding();
                $fielding[$catcherId]['catches']++;
            }
        }

        // ── Run out ────────────────────────────────────────────────────────
        if ($ball->wicket_type === 'run_out' && $fielderId) {
            $fielding[$fielderId] ??= $this->emptyFielding();
            $fielding[$fielderId]['run_outs']++;
        }
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Resolve balls accumulated in bowling into decimal overs (e.g. 22 balls → 3.4).
     * Called before persisting.
     */
    public function ballsToOvers(int $balls): float
    {
        $fullOvers   = intdiv($balls, 6);
        $remainder   = $balls % 6;

        return (float) "{$fullOvers}.{$remainder}";
    }

    /**
     * Compute maiden overs for a bowler in a match.
     * A maiden over = 6 legal deliveries, 0 runs conceded, no extras.
     */
    public function computeMaidens(GameMatch $match, int $bowlerId): int
    {
        $maidens = 0;

        foreach ([1, 2] as $innings) {
            $maxOver = BallByBall::where('match_id', $match->id)
                ->where('innings', $innings)
                ->where('bowler_id', $bowlerId)
                ->max('over_number');

            if ($maxOver === null) {
                continue;
            }

            for ($over = 0; $over <= $maxOver; $over++) {
                $balls = BallByBall::where('match_id', $match->id)
                    ->where('innings', $innings)
                    ->where('bowler_id', $bowlerId)
                    ->where('over_number', $over)
                    ->get();

                $legalCount = $balls->filter->is_legal_delivery->count();

                if ($legalCount < 6) {
                    continue; // Incomplete over
                }

                $runsInOver = $balls->sum(fn ($b) => $b->runs_off_bat + $b->extra_runs);

                if ($runsInOver === 0) {
                    $maidens++;
                }
            }
        }

        return $maidens;
    }

    // ── Empty stat arrays ─────────────────────────────────────────────────────

    private function emptyBatting(): array
    {
        return [
            'runs'        => 0,
            'balls_faced' => 0,
            'fours'       => 0,
            'sixes'       => 0,
            'out_status'  => 'not_out',
        ];
    }

    private function emptyBowling(): array
    {
        return [
            'overs'         => 0.0,
            'bowling_runs'  => 0,
            'wickets'       => 0,
            'maidens'       => 0,
            'lbw_or_bowled' => false,
            'no_balls'      => 0,
            'wides'         => 0,
        ];
    }

    private function emptyFielding(): array
    {
        return [
            'catches'    => 0,
            'stumpings'  => 0,
            'run_outs'   => 0,
        ];
    }

    /**
     * Post-process bowling accumulators before upsert —
     * convert _balls to overs, compute maidens.
     */
    private function finaliseBowling(array &$bowling, GameMatch $match): void
    {
        foreach ($bowling as $playerId => &$bowl) {
            $bowl['overs']   = $this->ballsToOvers($bowl['_balls']);
            $bowl['maidens'] = $this->computeMaidens($match, $playerId);
            unset($bowl['_balls']);
        }
    }
}