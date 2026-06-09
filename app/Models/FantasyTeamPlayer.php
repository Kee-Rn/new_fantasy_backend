<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FantasyTeamPlayer extends Model
{
    use HasFactory;

    protected $fillable = [
        'fantasy_team_id',
        'player_id',
        'is_captain',
        'is_vice_captain',
        'base_points',
        'points',
    ];

    protected $casts = [
        'is_captain'      => 'boolean',
        'is_vice_captain' => 'boolean',
    ];

    // ── Relationships ──────────────────────────────────────────────────────

    public function fantasyTeam(): BelongsTo
    {
        return $this->belongsTo(FantasyTeam::class);
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    /**
     * Captain multiplier: 2×.
     * Vice-captain multiplier: 1.5×.
     * Others: 1×.
     */
    public function getMultiplierAttribute(): float
    {
        if ($this->is_captain) {
            return 2.0;
        }

        if ($this->is_vice_captain) {
            return 1.5;
        }

        return 1.0;
    }

    /**
     * Apply the captain/vc multiplier to base_points and store in points.
     * Called by FantasyPointsService.
     */
    public function applyMultiplier(): void
    {
        $this->points = (int) round($this->base_points * $this->multiplier);
        $this->save();
    }
}