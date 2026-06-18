<?php

namespace App\Filament\Resources\PlayerPerformanceResource\Pages;

use App\Filament\Resources\PlayerPerformanceResource;
use App\Models\GameMatch;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListPlayerPerformances extends ListRecords
{
    protected static string $resource = PlayerPerformanceResource::class;

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
            $query->whereHas('matchPlayer', fn ($q) => $q->where('match_id', $this->selectedMatchId));
        } else {
            $query->whereRaw('0 = 1');
        }

        return $query;
    }

    // ── Header actions ─────────────────────────────────────────────────────

    protected function getHeaderActions(): array
    {
        if (! $this->selectedMatchId) {
            return [];
        }

        return [
            Actions\CreateAction::make()
                ->label('Add performance')
                ->url(PlayerPerformanceResource::getUrl('create') . '?match_id=' . $this->selectedMatchId),
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

            'batsmen' => Tab::make('Batted')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('balls_faced', '>', 0)),

            'bowlers' => Tab::make('Bowled')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('overs', '>', 0)),

            'dnb' => Tab::make('DNB')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('out_status', 'dnb')),
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
        return 'filament.resources.player-performances.list-player-performances';
    }
}