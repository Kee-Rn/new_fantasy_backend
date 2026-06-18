<?php

namespace App\Filament\Resources\MatchPlayerResource\Pages;

use App\Filament\Resources\MatchPlayerResource;
use App\Models\GameMatch;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListMatchPlayers extends ListRecords
{
    protected static string $resource = MatchPlayerResource::class;

    // ── Match gate ─────────────────────────────────────────────────────────

    public ?int $selectedMatchId = null;

    public function mount(): void
    {
        parent::mount();

        $this->selectedMatchId = (int) request()->query('match_id') ?: null;
    }

    public function selectMatch(int $matchId): void
    {
        $this->selectedMatchId = $matchId;
    }

    public function clearMatch(): void
    {
        $this->selectedMatchId = null;
    }

    // ── Scope table to selected match ──────────────────────────────────────

    protected function getTableQuery(): Builder
    {
        $query = parent::getTableQuery();

        if ($this->selectedMatchId) {
            $query->where('match_id', $this->selectedMatchId);
        } else {
            $query->whereRaw('0 = 1');
        }

        return $query;
    }

    // ── Header actions — always visible ────────────────────────────────────

    protected function getHeaderActions(): array
    {
        // "Assign Squad" always shown — deep-links with match pre-selected if available
        $assignUrl = $this->selectedMatchId
            ? MatchPlayerResource::getUrl('assign') . '?match_id=' . $this->selectedMatchId
            : MatchPlayerResource::getUrl('assign');

        return [
            Actions\Action::make('assign_squad')
                ->label('Assign Squad')
                ->icon('heroicon-o-user-plus')
                ->color('primary')
                ->url($assignUrl),
        ];
    }

    // ── Tabs ───────────────────────────────────────────────────────────────

    public function getTabs(): array
    {
        if (! $this->selectedMatchId) {
            return [];
        }

        return [
            'all' => Tab::make('All'),

            'playing_xi' => Tab::make('Playing XI')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('is_playing_xi', true)),

            'bench' => Tab::make('Bench')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('is_bench', true)),

            'unconfirmed' => Tab::make('Unconfirmed')
                ->modifyQueryUsing(fn (Builder $query) => $query
                    ->where('is_playing_xi', false)
                    ->where('is_bench', false)
                ),
        ];
    }

    // ── Match selector data ────────────────────────────────────────────────

    public function getMatchOptions(): array
    {
        return GameMatch::query()
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

    public function getSelectedMatch(): ?GameMatch
    {
        return $this->selectedMatchId
            ? GameMatch::with(['homeTeam', 'awayTeam'])->find($this->selectedMatchId)
            : null;
    }

    // ── View ───────────────────────────────────────────────────────────────

    public function getView(): string
    {
        return 'filament.resources.match-players.list-match-players';
    }
}