<?php

namespace App\Filament\Resources\BallByBallResource\Pages;

use App\Filament\Resources\BallByBallResource;
use App\Models\GameMatch;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListBallByBall extends ListRecords
{
    protected static string $resource = BallByBallResource::class;

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
        // "Enter Scores" is always shown — if a match is selected it deep-links
        // directly into that match's live score page, otherwise opens the scorer
        // which has its own match picker.
        $scoreUrl = $this->selectedMatchId
            ? BallByBallResource::getUrl('score') . '?match_id=' . $this->selectedMatchId
            : BallByBallResource::getUrl('score');

        return [
            Actions\Action::make('live_score')
                ->label('Enter Scores')
                ->icon('heroicon-o-play-circle')
                ->color('success')
                ->url($scoreUrl),
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
        return 'filament.resources.ball-by-ball.list-ball-by-balls';
    }
}