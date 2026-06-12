<?php

namespace App\Filament\Resources\BallByBallResource\Pages;

use App\Filament\Resources\BallByBallResource;
use App\Models\BallByBall;
use App\Models\GameMatch;
use App\Models\MatchPlayer;
use App\Models\Player;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Collection;

/**
 * LiveScore — custom Filament page for real-time ball-by-ball score entry.
 *
 * Features:
 *  - No page reloads (Livewire reactive)
 *  - Live scorecard (score, wickets, overs, recent balls)
 *  - Tab switching: Ball-by-Ball vs Over-by-Over entry
 *  - Auto-increments over/ball numbers
 *  - Batsman/bowler sticky between balls (no re-selecting each time)
 */
class LiveScore extends Page
{
    protected static string $resource = BallByBallResource::class;
    protected static string $view     = 'filament.resources.live-score';

    // ── Match context ─────────────────────────────────────────────────
    public ?int $match_id = null;
    public int  $innings  = 1;
    public string $entry_mode = 'ball'; // 'ball' | 'over'

    // ── Sticky selects (persist between balls) ────────────────────────
    public ?int $batsman_id  = null;
    public ?int $bowler_id   = null;
    public ?int $striker_id  = null;  // current striker (for over mode)

    // ── Ball-by-ball form fields ───────────────────────────────────────
    public int    $runs_off_bat  = 0;
    public bool   $is_four       = false;
    public bool   $is_six        = false;
    public ?string $extra_type   = null;
    public int    $extra_runs    = 0;
    public bool   $is_wicket     = false;
    public ?string $wicket_type  = null;
    public ?int   $dismissed_player_id = null;
    public ?int   $fielder_id    = null;
    public string $notes         = '';

    // ── Over-by-over mode ─────────────────────────────────────────────
    // Each ball in the over is stored as an array entry
    public array $over_balls = [];

    // ── Computed state ────────────────────────────────────────────────
    public int $current_over   = 0;
    public int $current_ball   = 1;
    public int $total_runs     = 0;
    public int $total_wickets  = 0;

    public function mount(): void
    {
        $this->initOverBalls();
    }

    // ── Watchers ──────────────────────────────────────────────────────

    public function updatedMatchId(): void
    {
        $this->resetScoreState();
        $this->loadCurrentPosition();
    }

    public function updatedInnings(): void
    {
        $this->resetScoreState();
        $this->loadCurrentPosition();
    }

    public function updatedRunsOffBat($value): void
    {
        $this->is_four = (int)$value === 4;
        $this->is_six  = (int)$value === 6;
    }

    public function updatedIsWicket($value): void
    {
        if (! $value) {
            $this->wicket_type         = null;
            $this->dismissed_player_id = null;
            $this->fielder_id          = null;
        }
    }

    public function updatedExtraType($value): void
    {
        if (! $value) {
            $this->extra_runs = 0;
        }
    }

    // ── Load current over/ball position from DB ───────────────────────

    private function loadCurrentPosition(): void
    {
        if (! $this->match_id) return;

        $last = BallByBall::where('match_id', $this->match_id)
            ->where('innings', $this->innings)
            ->orderBy('over_number', 'desc')
            ->orderBy('ball_number', 'desc')
            ->first();

        if (! $last) {
            $this->current_over  = 0;
            $this->current_ball  = 1;
            $this->total_runs    = 0;
            $this->total_wickets = 0;
            return;
        }

        $this->total_runs    = $last->total_runs_after;
        $this->total_wickets = $last->total_wickets_after;

        // Next legal delivery position
        $legalBalls = BallByBall::where('match_id', $this->match_id)
            ->where('innings', $this->innings)
            ->where('over_number', $last->over_number)
            ->whereNotIn('extra_type', ['wide', 'no_ball'])
            ->orWhereNull('extra_type')
            ->where('innings', $this->innings)
            ->where('match_id', $this->match_id)
            ->where('over_number', $last->over_number)
            ->count();

        if ($legalBalls >= 6) {
            $this->current_over = $last->over_number + 1;
            $this->current_ball = 1;
        } else {
            $this->current_over = $last->over_number;
            $this->current_ball = $last->ball_number + 1;
        }
    }

    // ── Save a single ball ────────────────────────────────────────────

    public function saveBall(): void
    {
        $this->validateBallEntry();
        if (! $this->match_id) return;

        $totalRunsNow    = $this->total_runs + $this->runs_off_bat + $this->extra_runs;
        $totalWicketsNow = $this->total_wickets + ($this->is_wicket ? 1 : 0);

        BallByBall::create([
            'match_id'            => $this->match_id,
            'innings'             => $this->innings,
            'over_number'         => $this->current_over,
            'ball_number'         => $this->current_ball,
            'batsman_id'          => $this->batsman_id,
            'bowler_id'           => $this->bowler_id,
            'runs_off_bat'        => $this->runs_off_bat,
            'is_four'             => $this->is_four,
            'is_six'              => $this->is_six,
            'extra_type'          => $this->extra_type ?: null,
            'extra_runs'          => $this->extra_runs,
            'is_wicket'           => $this->is_wicket,
            'wicket_type'         => $this->wicket_type,
            'dismissed_player_id' => $this->dismissed_player_id,
            'fielder_id'          => $this->fielder_id,
            'total_runs_after'    => $totalRunsNow,
            'total_wickets_after' => $totalWicketsNow,
            'notes'               => $this->notes ?: null,
        ]);

        $this->total_runs    = $totalRunsNow;
        $this->total_wickets = $totalWicketsNow;

        $this->advanceBallNumber();
        $this->resetBallFields();

        // Auto-recalculate fantasy points for all active contests on this match
        try {
            $match = GameMatch::find($this->match_id);
            if ($match) {
                app(\App\Services\Cricket\FantasyPointsService::class)->processMatch($match);
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Auto points recalc failed: ' . $e->getMessage());
        }

        Notification::make()
            ->title('Ball saved — ' . ($this->current_over) . '.' . ($this->current_ball - 1))
            ->success()
            ->send();
    }

    // ── Over-by-over mode ─────────────────────────────────────────────

    private function initOverBalls(): void
    {
        $this->over_balls = array_fill(0, 6, $this->emptyBall());
    }

    public function saveOver(): void
    {
        if (! $this->match_id) {
            Notification::make()->title('Select a match first')->warning()->send();
            return;
        }

        $match = GameMatch::find($this->match_id);
        if (! $match || $match->status !== 'live') {
            Notification::make()
                ->title('Match is not live')
                ->body('Set the match status to "Live" before entering scores.')
                ->danger()
                ->send();
            return;
        }

        $hasActiveContest = $match->fantasyContests()
            ->whereIn('status', ['upcoming', 'active'])
            ->exists();

        if (! $hasActiveContest) {
            Notification::make()
                ->title('No active contest')
                ->body('There is no active or upcoming contest for this match. Activate a contest first.')
                ->danger()
                ->send();
            return;
        }

        $ballsToSave = collect($this->over_balls)->filter(
            fn ($b) => $b['batsman_id'] && $b['bowler_id']
        );

        if ($ballsToSave->isEmpty()) {
            Notification::make()->title('No balls entered')->warning()->send();
            return;
        }

        $runningRuns    = $this->total_runs;
        $runningWickets = $this->total_wickets;
        $ballNum        = 1;

        foreach ($ballsToSave as $ball) {
            $runningRuns    += (int)$ball['runs_off_bat'] + (int)$ball['extra_runs'];
            $runningWickets += $ball['is_wicket'] ? 1 : 0;

            BallByBall::create([
                'match_id'            => $this->match_id,
                'innings'             => $this->innings,
                'over_number'         => $this->current_over,
                'ball_number'         => $ballNum,
                'batsman_id'          => $ball['batsman_id'],
                'bowler_id'           => $ball['bowler_id'],
                'runs_off_bat'        => (int)$ball['runs_off_bat'],
                'is_four'             => (bool)$ball['is_four'],
                'is_six'              => (bool)$ball['is_six'],
                'extra_type'          => $ball['extra_type'] ?: null,
                'extra_runs'          => (int)$ball['extra_runs'],
                'is_wicket'           => (bool)$ball['is_wicket'],
                'wicket_type'         => $ball['wicket_type'] ?: null,
                'dismissed_player_id' => $ball['dismissed_player_id'] ?: null,
                'fielder_id'          => $ball['fielder_id'] ?: null,
                'total_runs_after'    => $runningRuns,
                'total_wickets_after' => $runningWickets,
                'notes'               => $ball['notes'] ?: null,
            ]);

            $ballNum++;
        }

        $this->total_runs    = $runningRuns;
        $this->total_wickets = $runningWickets;
        $this->current_over++;
        $this->current_ball = 1;

        $this->initOverBalls();

        // Auto-recalculate fantasy points after over is saved
        try {
            $match = GameMatch::find($this->match_id);
            if ($match) {
                app(\App\Services\Cricket\FantasyPointsService::class)->processMatch($match);
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Auto points recalc failed: ' . $e->getMessage());
        }

        Notification::make()
            ->title('Over ' . $this->current_over . ' saved')
            ->success()
            ->send();
    }

    // ── Advance ball counter ──────────────────────────────────────────

    private function advanceBallNumber(): void
    {
        // Count legal deliveries in this over
        $legalInOver = BallByBall::where('match_id', $this->match_id)
            ->where('innings', $this->innings)
            ->where('over_number', $this->current_over)
            ->whereNull('extra_type')
            ->orWhere(function ($q) {
                $q->where('match_id', $this->match_id)
                  ->where('innings', $this->innings)
                  ->where('over_number', $this->current_over)
                  ->whereNotIn('extra_type', ['wide', 'no_ball']);
            })
            ->count();

        if ($legalInOver >= 6) {
            $this->current_over++;
            $this->current_ball = 1;
        } else {
            $this->current_ball++;
        }
    }

    // ── Reset helpers ─────────────────────────────────────────────────

    private function resetBallFields(): void
    {
        $this->runs_off_bat         = 0;
        $this->is_four              = false;
        $this->is_six               = false;
        $this->extra_type           = null;
        $this->extra_runs           = 0;
        $this->is_wicket            = false;
        $this->wicket_type          = null;
        $this->dismissed_player_id  = null;
        $this->fielder_id           = null;
        $this->notes                = '';
        // batsman_id and bowler_id intentionally kept — sticky
    }

    private function resetScoreState(): void
    {
        $this->batsman_id  = null;
        $this->bowler_id   = null;
        $this->current_over  = 0;
        $this->current_ball  = 1;
        $this->total_runs    = 0;
        $this->total_wickets = 0;
        $this->resetBallFields();
        $this->initOverBalls();
    }

    private function validateBallEntry(): void
    {
        if (! $this->match_id) {
            Notification::make()->title('Select a match first')->warning()->send();
            return;
        }

        // Guard: match must be live
        $match = GameMatch::find($this->match_id);
        if (! $match || $match->status !== 'live') {
            Notification::make()
                ->title('Match is not live')
                ->body('Set the match status to "Live" before entering scores.')
                ->danger()
                ->send();
            return;
        }

        // Guard: at least one contest must be active
        $hasActiveContest = $match->fantasyContests()
            ->whereIn('status', ['upcoming', 'active'])
            ->exists();

        if (! $hasActiveContest) {
            Notification::make()
                ->title('No active contest')
                ->body('There is no active or upcoming contest for this match. Activate a contest first.')
                ->danger()
                ->send();
            return;
        }

        if (! $this->batsman_id) {
            Notification::make()->title('Select a batsman')->warning()->send();
            return;
        }
        if (! $this->bowler_id) {
            Notification::make()->title('Select a bowler')->warning()->send();
            return;
        }
    }

    private function emptyBall(): array
    {
        return [
            'batsman_id'          => null,
            'bowler_id'           => null,
            'runs_off_bat'        => 0,
            'is_four'             => false,
            'is_six'              => false,
            'extra_type'          => null,
            'extra_runs'          => 0,
            'is_wicket'           => false,
            'wicket_type'         => null,
            'dismissed_player_id' => null,
            'fielder_id'          => null,
            'notes'               => '',
        ];
    }

    // ── Data helpers for the view ─────────────────────────────────────

    public function getMatchOptions(): array
    {
        return GameMatch::query()
            ->whereIn('status', ['live', 'upcoming'])
            ->with(['homeTeam', 'awayTeam'])
            ->orderBy('start_time', 'desc')
            ->get()
            ->mapWithKeys(fn ($m) => [
                $m->id =>
                    ($m->homeTeam?->name ?? '?')
                    . ' vs '
                    . ($m->awayTeam?->name ?? '?')
                    . ($m->start_time ? ' — ' . $m->start_time->format('d M Y') : ''),
            ])
            ->toArray();
    }

    public function getBatsmanOptions(): array
    {
        if (! $this->match_id) return [];

        $match = GameMatch::find($this->match_id);
        if (! $match || ! $match->batting_first_team_id) return [];

        $battingTeamId = $this->innings === 1
            ? $match->batting_first_team_id
            : ($match->home_team_id === $match->batting_first_team_id
                ? $match->away_team_id
                : $match->home_team_id);

        return MatchPlayer::where('match_id', $this->match_id)
            ->where('is_playing_xi', true)
            ->where('team_id', $battingTeamId)
            ->with('player')
            ->get()
            ->mapWithKeys(fn ($mp) => [
                $mp->player_id => $mp->player->name . ' (' . $mp->player->role . ')',
            ])
            ->toArray();
    }

    public function getBowlerOptions(): array
    {
        if (! $this->match_id) return [];

        $match = GameMatch::find($this->match_id);
        if (! $match || ! $match->batting_first_team_id) return [];

        $fieldingTeamId = $this->innings === 1
            ? ($match->home_team_id === $match->batting_first_team_id
                ? $match->away_team_id
                : $match->home_team_id)
            : $match->batting_first_team_id;

        return MatchPlayer::where('match_id', $this->match_id)
            ->where('is_playing_xi', true)
            ->where('team_id', $fieldingTeamId)
            ->with('player')
            ->get()
            ->mapWithKeys(fn ($mp) => [
                $mp->player_id => $mp->player->name . ' (' . $mp->player->role . ')',
            ])
            ->toArray();
    }

    public function getAllPlayersOptions(): array
    {
        if (! $this->match_id) return [];

        return MatchPlayer::where('match_id', $this->match_id)
            ->where('is_playing_xi', true)
            ->with('player')
            ->get()
            ->mapWithKeys(fn ($mp) => [
                $mp->player_id => $mp->player->name . ' (' . $mp->player->role . ')',
            ])
            ->toArray();
    }

    public function getRecentBalls(): Collection
    {
        if (! $this->match_id) return collect();

        return BallByBall::where('match_id', $this->match_id)
            ->where('innings', $this->innings)
            ->with(['batsman', 'bowler'])
            ->orderBy('id', 'desc')
            ->limit(12)
            ->get();
    }

    public function getCurrentOverDisplay(): string
    {
        $legal = BallByBall::where('match_id', $this->match_id)
            ->where('innings', $this->innings)
            ->where('over_number', $this->current_over)
            ->whereNull('extra_type')
            ->count();

        return ($this->current_over) . '.' . $legal;
    }

    public function getTitle(): string
    {
        return 'Live Score Entry';
    }
}