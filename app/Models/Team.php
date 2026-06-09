<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Team extends Model
{
    use HasFactory;

    protected $fillable = [
        'league_id',
        'name',
        'short_name',
        'country',
        'city',
        'logo_url',
    ];

    // ── Relationships ──────────────────────────────────────────────────────

    public function league(): BelongsTo
    {
        return $this->belongsTo(League::class);
    }

    public function players(): HasMany
    {
        return $this->hasMany(Player::class);
    }

    public function matchPlayers(): HasMany
    {
        return $this->hasMany(MatchPlayer::class);
    }

    /**
     * Matches where this team is the home side.
     */
    public function homeMatches(): HasMany
    {
        return $this->hasMany(GameMatch::class, 'home_team_id');
    }

    /**
     * Matches where this team is the away side.
     */
    public function awayMatches(): HasMany
    {
        return $this->hasMany(GameMatch::class, 'away_team_id');
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    /**
     * All matches (home + away) for this team.
     */
    public function allMatches()
    {
        return GameMatch::where('home_team_id', $this->id)
            ->orWhere('away_team_id', $this->id);
    }
}
