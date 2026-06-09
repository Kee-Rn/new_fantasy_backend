<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Player extends Model
{
    use HasFactory;

    protected $fillable = [
        'team_id',
        'name',
        'role',
        'batting_style',
        'bowling_style',
        'nationality',
        'photo_url',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // ── Relationships ──────────────────────────────────────────────────────

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function matchPlayers(): HasMany
    {
        return $this->hasMany(MatchPlayer::class);
    }

    public function fantasyTeamPlayers(): HasMany
    {
        return $this->hasMany(FantasyTeamPlayer::class);
    }

    /**
     * All ball-by-ball deliveries where this player was the batsman.
     */
    public function deliveriesBatted(): HasMany
    {
        return $this->hasMany(BallByBall::class, 'batsman_id');
    }

    /**
     * All ball-by-ball deliveries where this player was the bowler.
     */
    public function deliveriesBowled(): HasMany
    {
        return $this->hasMany(BallByBall::class, 'bowler_id');
    }

    // ── Scopes ─────────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByRole($query, string $role)
    {
        return $query->where('role', $role);
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    public function isWicketkeeper(): bool
    {
        return $this->role === 'WK';
    }

    public function isBatsman(): bool
    {
        return $this->role === 'BAT';
    }

    public function isBowler(): bool
    {
        return $this->role === 'BOWL';
    }

    public function isAllRounder(): bool
    {
        return $this->role === 'ALL';
    }
}