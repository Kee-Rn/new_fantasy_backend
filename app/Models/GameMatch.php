<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Named GameMatch to avoid conflict with PHP's reserved word `match`.
 * Map this to the `matches` table explicitly via $table.
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
        'match_number',
        'match_type',
        'status',
        'start_time',
        'venue',
        'city',
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

    public function scopeUpcoming($query)
    {
        return $query->where('status', 'upcoming');
    }

    public function scopeLive($query)
    {
        return $query->where('status', 'live');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    public function isUpcoming(): bool
    {
        return $this->status === 'upcoming';
    }

    public function isLive(): bool
    {
        return $this->status === 'live';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * The fielding team in a given innings based on batting_first_team_id.
     */
    public function fieldingTeamForInnings(int $innings): ?Team
    {
        if (! $this->batting_first_team_id) {
            return null;
        }

        $battingTeamId = $this->batting_first_team_id;

        if ($innings === 1) {
            // Innings 1: batting_first bats, the other team fields
            return $this->home_team_id === $battingTeamId
                ? $this->awayTeam
                : $this->homeTeam;
        }

        // Innings 2: the other team bats, batting_first fields
        return $this->home_team_id === $battingTeamId
            ? $this->homeTeam
            : $this->awayTeam;
    }

    /**
     * The batting team in a given innings.
     */
    public function battingTeamForInnings(int $innings): ?Team
    {
        if (! $this->batting_first_team_id) {
            return null;
        }

        if ($innings === 1) {
            return $this->battingFirstTeam;
        }

        // Innings 2: the other team bats
        return $this->home_team_id === $this->batting_first_team_id
            ? $this->awayTeam
            : $this->homeTeam;
    }

    /**
     * Live score summary for a given innings from ball_by_ball.
     * Returns ['runs' => int, 'wickets' => int, 'overs' => string]
     */
    public function liveScoreForInnings(int $innings): array
    {
        $last = $this->ballByBall()
            ->where('innings', $innings)
            ->latest('id')
            ->first();

        if (! $last) {
            return ['runs' => 0, 'wickets' => 0, 'overs' => '0.0'];
        }

        $overs = $last->over_number . '.' . $last->ball_number;

        return [
            'runs'    => $last->total_runs_after,
            'wickets' => $last->total_wickets_after,
            'overs'   => $overs,
        ];
    }
}