<?php

namespace App\Filament\Resources\MatchPlayerResource\Pages;

use App\Filament\Resources\MatchPlayerResource;
use App\Models\GameMatch;
use App\Models\MatchPlayer;
use App\Models\Player;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Collection;

/**
 * AssignMatchPlayers
 *
 * Custom page for bulk-assigning a squad to a match.
 * Admin picks a match + team, ticks players from that team,
 * sets their status (XI / bench), and saves all at once.
 */
class AssignMatchPlayers extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string $resource = MatchPlayerResource::class;
    protected static string $view     = 'filament.resources.assign-match-players';

    // ── Form state ────────────────────────────────────────────────────

    public ?int    $match_id  = null;
    public ?int    $team_id   = null;
    public array   $selected  = [];   // player_ids checked
    public string  $xi_status = 'playing_xi'; // playing_xi | bench | unconfirmed

    public ?GameMatch $selectedMatch = null;
    public Collection $availablePlayers;

    public function mount(): void
    {
        $this->availablePlayers = collect();
    }

    // ── Reactive: when match or team changes, reload players ──────────

    public function updatedMatchId(): void
    {
        $this->team_id  = null;
        $this->selected = [];
        $this->availablePlayers = collect();
        $this->selectedMatch = $this->match_id
            ? GameMatch::with(['homeTeam', 'awayTeam'])->find($this->match_id)
            : null;
    }

    public function updatedTeamId(): void
    {
        $this->selected = [];
        $this->loadAvailablePlayers();
    }

    private function loadAvailablePlayers(): void
    {
        if (! $this->match_id || ! $this->team_id) {
            $this->availablePlayers = collect();
            return;
        }

        // Already assigned player IDs for this match+team
        $assigned = MatchPlayer::where('match_id', $this->match_id)
            ->where('team_id', $this->team_id)
            ->pluck('player_id')
            ->toArray();

        $this->availablePlayers = Player::where('team_id', $this->team_id)
            ->where('is_active', true)
            ->whereNotIn('id', $assigned)
            ->orderBy('name')
            ->get();
    }

    // ── Save ──────────────────────────────────────────────────────────

    public function save(): void
    {
        if (! $this->match_id || ! $this->team_id) {
            Notification::make()->title('Select a match and team first')->warning()->send();
            return;
        }

        if (empty($this->selected)) {
            Notification::make()->title('No players selected')->warning()->send();
            return;
        }

        $isXi   = $this->xi_status === 'playing_xi';
        $isBench = $this->xi_status === 'bench';

        $inserted = 0;

        foreach ($this->selected as $playerId) {
            // Upsert — safe to re-run
            MatchPlayer::firstOrCreate(
                [
                    'match_id'  => $this->match_id,
                    'player_id' => $playerId,
                ],
                [
                    'team_id'       => $this->team_id,
                    'is_playing_xi' => $isXi,
                    'is_bench'      => $isBench,
                ]
            );
            $inserted++;
        }

        Notification::make()
            ->title("{$inserted} player(s) assigned successfully")
            ->success()
            ->send();

        // Reset selection, reload available players
        $this->selected = [];
        $this->loadAvailablePlayers();
    }

    // ── Helpers for the view ──────────────────────────────────────────

    public function getMatchOptions(): array
    {
        return GameMatch::query()
            ->whereIn('status', ['upcoming', 'live'])
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

    public function getTeamOptions(): array
    {
        if (! $this->match_id) return [];

        $match = $this->selectedMatch
            ?? GameMatch::with(['homeTeam', 'awayTeam'])->find($this->match_id);

        if (! $match) return [];

        return collect([$match->homeTeam, $match->awayTeam])
            ->filter()
            ->mapWithKeys(fn ($t) => [$t->id => $t->name])
            ->toArray();
    }

    public function getRoleColor(string $role): string
    {
        return match ($role) {
            'WK'   => 'text-red-600',
            'BAT'  => 'text-blue-600',
            'ALL'  => 'text-yellow-600',
            'BOWL' => 'text-green-600',
            default => 'text-gray-600',
        };
    }

    public function getTitle(): string
    {
        return 'Assign Squad';
    }
}