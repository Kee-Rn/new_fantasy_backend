<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlayerPerformance extends Model
{
    use HasFactory;

    protected $fillable = [
        'match_player_id',
        // Batting
        'runs',
        'balls_faced',
        'fours',
        'sixes',
        'out_status',
        // Bowling
        'overs',
        'bowling_runs',
        'wickets',
        'maidens',
        'lbw_or_bowled',
        'no_balls',
        'wides',
        // Fielding
        'catches',
        'stumpings',
        'run_outs',
        // WK extras
        'byes',
        'leg_byes',
        // Computed
        'fantasy_points',
    ];

    protected $casts = [
        'lbw_or_bowled' => 'boolean',
        'overs'         => 'decimal:1',
    ];

    // ── Relationships ──────────────────────────────────────────────────────

    public function matchPlayer(): BelongsTo
    {
        return $this->belongsTo(MatchPlayer::class);
    }

    // ── Convenience pass-throughs ──────────────────────────────────────────

    /**
     * The actual Player model, via MatchPlayer.
     */
    public function getPlayerAttribute(): ?Player
    {
        return $this->matchPlayer?->player;
    }

    /**
     * The match this performance belongs to, via MatchPlayer.
     */
    public function getMatchAttribute(): ?GameMatch
    {
        return $this->matchPlayer?->match;
    }

    // ── Computed stats ─────────────────────────────────────────────────────

    /**
     * Batting strike rate. Returns null if no balls faced.
     */
    public function getBattingStrikeRateAttribute(): ?float
    {
        if (! $this->balls_faced) {
            return null;
        }

        return round(($this->runs / $this->balls_faced) * 100, 2);
    }

    /**
     * Bowling economy rate. Returns null if no overs bowled.
     */
    public function getBowlingEconomyAttribute(): ?float
    {
        if (! $this->overs) {
            return null;
        }

        return round($this->bowling_runs / $this->overs, 2);
    }

    /**
     * Bowling average. Returns null if no wickets.
     */
    public function getBowlingAverageAttribute(): ?float
    {
        if (! $this->wickets) {
            return null;
        }

        return round($this->bowling_runs / $this->wickets, 2);
    }

    /**
     * Whether the batsman scored a duck (out for 0).
     */
    public function getIsDuckAttribute(): bool
    {
        return $this->runs === 0 && $this->out_status === 'out';
    }

    /**
     * Whether the batsman scored a half-century.
     */
    public function getIsHalfCenturyAttribute(): bool
    {
        return $this->runs >= 50 && $this->runs < 100;
    }

    /**
     * Whether the batsman scored a century.
     */
    public function getIsCenturyAttribute(): bool
    {
        return $this->runs >= 100;
    }

    /**
     * Whether the bowler took a five-wicket haul.
     */
    public function getIsFiferAttribute(): bool
    {
        return $this->wickets >= 5;
    }
}