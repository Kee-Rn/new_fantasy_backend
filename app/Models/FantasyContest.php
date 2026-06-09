<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class FantasyContest extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'match_id',
        'name',
        'entry_fee',
        'prize_pool',
        'max_teams',
        'total_teams',
        'status',
        'deadline_at',
        'points_status',
        'points_calculated_at',
    ];

    protected $casts = [
        'deadline_at'           => 'datetime',
        'points_calculated_at'  => 'datetime',
    ];

    // ── Relationships ──────────────────────────────────────────────────────

    public function match(): BelongsTo
    {
        return $this->belongsTo(GameMatch::class, 'match_id');
    }

    public function fantasyTeams(): HasMany
    {
        return $this->hasMany(FantasyTeam::class, 'contest_id');
    }

    // ── Scopes ─────────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeUpcoming($query)
    {
        return $query->where('status', 'upcoming');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopePointsReady($query)
    {
        return $query->where('points_status', 'calculated');
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    /**
     * Whether the deadline has passed (submissions closed).
     */
    public function isDeadlinePassed(): bool
    {
        return $this->deadline_at && $this->deadline_at->isPast();
    }

    /**
     * Whether the contest still has open slots.
     */
    public function hasSlots(): bool
    {
        if ($this->max_teams === 0) {
            return true; // unlimited
        }

        return $this->total_teams < $this->max_teams;
    }

    /**
     * Whether points have been fully calculated.
     */
    public function pointsCalculated(): bool
    {
        return $this->points_status === 'calculated';
    }

    /**
     * Leaderboard: fantasy teams ordered by total_points descending.
     */
    public function leaderboard()
    {
        return $this->fantasyTeams()
            ->with('user')
            ->orderByDesc('total_points')
            ->get();
    }
}