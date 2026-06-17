<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Player extends Model
{
    use HasFactory;

    protected $fillable = [
        'team_id',
        'name',
        'role',
        'photo_path',
        'is_active',
        'price',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'price'     => 'float',
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

    public function deliveriesBatted(): HasMany
    {
        return $this->hasMany(BallByBall::class, 'batsman_id');
    }

    public function deliveriesBowled(): HasMany
    {
        return $this->hasMany(BallByBall::class, 'bowler_id');
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    public function getPhotoUrlAttribute(): ?string
    {
        return $this->photo_path
            ? Storage::url($this->photo_path)
            : null;
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByRole($query, string $role)
    {
        return $query->where('role', $role);
    }

    public function isWicketkeeper(): bool { return $this->role === 'WK'; }
    public function isBatsman(): bool      { return $this->role === 'BAT'; }
    public function isBowler(): bool       { return $this->role === 'BOWL'; }
    public function isAllRounder(): bool   { return $this->role === 'ALL'; }
}