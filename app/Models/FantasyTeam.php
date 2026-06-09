<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class FantasyTeam extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'contest_id',
        'team_name',
        'total_points',
        'rank',
    ];

    // ── Relationships ──────────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function contest(): BelongsTo
    {
        return $this->belongsTo(FantasyContest::class, 'contest_id');
    }

    public function fantasyTeamPlayers(): HasMany
    {
        return $this->hasMany(FantasyTeamPlayer::class);
    }

    /**
     * The actual Player models selected in this team (via fantasy_team_players pivot).
     */
    public function players(): BelongsToMany
    {
        return $this->belongsToMany(Player::class, 'fantasy_team_players')
                    ->withPivot(['is_captain', 'is_vice_captain', 'base_points', 'points'])
                    ->withTimestamps();
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    public function getCaptain(): ?Player
    {
        return $this->players()
            ->wherePivot('is_captain', true)
            ->first();
    }

    public function getViceCaptain(): ?Player
    {
        return $this->players()
            ->wherePivot('is_vice_captain', true)
            ->first();
    }

    /**
     * Recalculate and persist total_points from fantasy_team_players.
     * Called by FantasyPointsService after player_performances are finalised.
     */
    public function recalculateTotalPoints(): void
    {
        $this->total_points = $this->fantasyTeamPlayers()->sum('points');
        $this->save();
    }

    /**
     * Count players by role in this team (for team-composition validation).
     * Returns ['WK' => 1, 'BAT' => 3, 'ALL' => 2, 'BOWL' => 4]
     */
    public function playerCountByRole(): array
    {
        return $this->players()
            ->get()
            ->groupBy('role')
            ->map(fn ($group) => $group->count())
            ->toArray();
    }
}