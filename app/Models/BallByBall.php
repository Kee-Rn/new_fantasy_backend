<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BallByBall extends Model
{
    use HasFactory;

    protected $table = 'ball_by_ball';

    protected $fillable = [
        'match_id',
        'innings',
        'over_number',
        'ball_number',
        'batsman_id',
        'bowler_id',
        'runs_off_bat',
        'is_four',
        'is_six',
        'extra_type',
        'extra_runs',
        'is_wicket',
        'wicket_type',
        'dismissed_player_id',
        'fielder_id',
        'total_runs_after',
        'total_wickets_after',
        'notes',
    ];

    protected $casts = [
        'is_four'    => 'boolean',
        'is_six'     => 'boolean',
        'is_wicket'  => 'boolean',
    ];

    // ── Relationships ──────────────────────────────────────────────────────

    public function match(): BelongsTo
    {
        return $this->belongsTo(GameMatch::class, 'match_id');
    }

    public function batsman(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'batsman_id');
    }

    public function bowler(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'bowler_id');
    }

    public function dismissedPlayer(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'dismissed_player_id');
    }

    public function fielder(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'fielder_id');
    }

    // ── Scopes ─────────────────────────────────────────────────────────────

    public function scopeForInnings($query, int $innings)
    {
        return $query->where('innings', $innings);
    }

    public function scopeForOver($query, int $innings, int $over)
    {
        return $query->where('innings', $innings)->where('over_number', $over);
    }

    public function scopeWickets($query)
    {
        return $query->where('is_wicket', true);
    }

    public function scopeBoundaries($query)
    {
        return $query->where('is_four', true)->orWhere('is_six', true);
    }

    public function scopeExtras($query)
    {
        return $query->whereNotNull('extra_type');
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    /**
     * Total runs on this delivery (bat + extras).
     */
    public function getTotalRunsAttribute(): int
    {
        return $this->runs_off_bat + $this->extra_runs;
    }

    /**
     * Human-readable over.ball label, e.g. "3.4" = over 3, ball 4.
     * Note: over_number is 0-based so we add 1 for display.
     */
    public function getOverLabelAttribute(): string
    {
        return ($this->over_number + 1) . '.' . $this->ball_number;
    }

    /**
     * Whether this was a legal (counting) delivery.
     */
    public function getIsLegalDeliveryAttribute(): bool
    {
        return ! in_array($this->extra_type, ['wide', 'no_ball']);
    }
}