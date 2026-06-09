<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class MatchPlayer extends Model
{
    use HasFactory;

    protected $fillable = [
        'match_id',
        'player_id',
        'team_id',
        'is_playing_xi',
        'is_bench',
        'batting_order',
    ];

    protected $casts = [
        'is_playing_xi' => 'boolean',
        'is_bench'      => 'boolean',
    ];

    // ── Relationships ──────────────────────────────────────────────────────

    public function match(): BelongsTo
    {
        return $this->belongsTo(GameMatch::class, 'match_id');
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function performance(): HasOne
    {
        return $this->hasOne(PlayerPerformance::class);
    }

    // ── Scopes ─────────────────────────────────────────────────────────────

    public function scopePlayingXi($query)
    {
        return $query->where('is_playing_xi', true);
    }

    public function scopeForTeam($query, int $teamId)
    {
        return $query->where('team_id', $teamId);
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    /**
     * Shortcut to get the player's role (WK/BAT/ALL/BOWL).
     */
    public function getPlayerRoleAttribute(): ?string
    {
        return $this->player?->role;
    }
}