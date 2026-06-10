<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Named GameMatch to avoid conflict with PHP's reserved word `match`.
 * Maps to the `matches` table via $table.
 */
class GameMatch extends Model
{
    use HasFactory;

    protected $table = 'matches';

    protected $fillable = [
        'league_id',
        'home_team_id',
        'away_team_id',
        'batting_first_team_id',
        'status',
        'start_time',
        'toss_winner',
        'toss_decision',
        'result',
        'result_type',
        'is_featured',
    ];

    protected $casts = [
        'start_time'  => 'datetime',
        'is_featured' => 'boolean',
    ];

    // ── Relationships ──────────────────────────────────────────────────────

    public function league(): BelongsTo
    {
        return $this->belongsTo(League::class);
    }

    public function homeTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'home_team_id');
    }

    public function awayTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'away_team_id');
    }

    public function battingFirstTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'batting_first_team_id');
    }

    public function matchPlayers(): HasMany
    {
        return $this->hasMany(MatchPlayer::class, 'match_id');
    }

    public function ballByBall(): HasMany
    {
        return $this->hasMany(BallByBall::class, 'match_id');
    }

    public function fantasyContests(): HasMany
    {
        return $this->hasMany(FantasyContest::class, 'match_id');
    }

    // ── Scopes ─────────────────────────────────────────────────────────────

    public function scopeUpcoming($query)  { return $query->where('status', 'upcoming'); }
    public function scopeLive($query)      { return $query->where('status', 'live'); }
    public function scopeCompleted($query) { return $query->where('status', 'completed'); }
    public function scopeFeatured($query)  { return $query->where('is_featured', true); }

    // ── Helpers ────────────────────────────────────────────────────────────

    public function isUpcoming(): bool  { return $this->status === 'upcoming'; }
    public function isLive(): bool      { return $this->status === 'live'; }
    public function isCompleted(): bool { return $this->status === 'completed'; }

    public function fieldingTeamForInnings(int $innings): ?Team
    {
        if (! $this->batting_first_team_id) return null;

        $battingTeamId = $this->batting_first_team_id;

        if ($innings === 1) {
            return $this->home_team_id === $battingTeamId ? $this->awayTeam : $this->homeTeam;
        }

        return $this->home_team_id === $battingTeamId ? $this->homeTeam : $this->awayTeam;
    }

    public function battingTeamForInnings(int $innings): ?Team
    {
        if (! $this->batting_first_team_id) return null;

        if ($innings === 1) return $this->battingFirstTeam;

        return $this->home_team_id === $this->batting_first_team_id
            ? $this->awayTeam
            : $this->homeTeam;
    }

    public function liveScoreForInnings(int $innings): array
    {
        $last = $this->ballByBall()
            ->where('innings', $innings)
            ->latest('id')
            ->first();

        if (! $last) return ['runs' => 0, 'wickets' => 0, 'overs' => '0.0'];

        return [
            'runs'    => $last->total_runs_after,
            'wickets' => $last->total_wickets_after,
            'overs'   => ($last->over_number + 1) . '.' . $last->ball_number,
        ];
    }
}