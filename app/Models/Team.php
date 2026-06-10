<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Team extends Model
{
    use HasFactory;

    protected $fillable = [
        'league_id',
        'name',
        'logo_path',
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

    public function homeMatches(): HasMany
    {
        return $this->hasMany(GameMatch::class, 'home_team_id');
    }

    public function awayMatches(): HasMany
    {
        return $this->hasMany(GameMatch::class, 'away_team_id');
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    public function getLogoUrlAttribute(): ?string
    {
        return $this->logo_path
            ? Storage::url($this->logo_path)
            : null;
    }

    public function allMatches()
    {
        return GameMatch::where('home_team_id', $this->id)
            ->orWhere('away_team_id', $this->id);
    }
}