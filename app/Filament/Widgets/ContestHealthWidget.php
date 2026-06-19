<?php

namespace App\Filament\Widgets;

use App\Models\FantasyContest;
use App\Services\Cricket\FantasyPointsService;
use Filament\Notifications\Notification;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class ContestHealthWidget extends BaseWidget
{
    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Contest Health';

    protected static ?string $pollingInterval = '30s';

    // Only show when there are contests needing attention
    public static function canView(): bool
    {
        return FantasyContest::whereIn('points_status', ['pending', 'failed', 'calculating'])
            ->where('status', 'completed')
            ->exists();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                FantasyContest::query()
                    ->with(['match.homeTeam', 'match.awayTeam'])
                    ->where('status', 'completed')
                    ->whereIn('points_status', ['pending', 'failed', 'calculating'])
                    ->orderByRaw("FIELD(points_status, 'failed', 'pending', 'calculating')")
                    ->orderBy('updated_at', 'asc')
            )
            ->columns([

                Tables\Columns\TextColumn::make('name')
                    ->label('Contest')
                    ->weight('semibold')
                    ->searchable(false),

                Tables\Columns\TextColumn::make('match')
                    ->label('Match')
                    ->getStateUsing(fn ($record) =>
                        ($record->match?->homeTeam?->name ?? '?')
                        . ' vs '
                        . ($record->match?->awayTeam?->name ?? '?')
                    )
                    ->searchable(false),

                Tables\Columns\TextColumn::make('points_status')
                    ->label('Points status')
                    ->badge()
                    ->color(fn ($state) => match($state) {
                        'failed'      => 'danger',
                        'pending'     => 'warning',
                        'calculating' => 'primary',
                        default       => 'gray',
                    })
                    ->formatStateUsing(fn ($state) => match($state) {
                        'failed'      => '✕ Failed',
                        'pending'     => '⏳ Pending',
                        'calculating' => '⟳ Calculating',
                        default       => $state,
                    })
                    ->searchable(false),

                Tables\Columns\TextColumn::make('total_teams')
                    ->label('Teams')
                    ->alignCenter()
                    ->searchable(false),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Last updated')
                    ->since()
                    ->searchable(false),

            ])
            ->actions([
                Tables\Actions\Action::make('calculate')
                    ->label('Calculate now')
                    ->icon('heroicon-m-calculator')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Calculate fantasy points')
                    ->modalDescription(fn ($record) =>
                        'This will aggregate stats, score all performances, and rank every team in "' . $record->name . '".'
                    )
                    ->modalSubmitActionLabel('Yes, calculate now')
                    ->action(function ($record) {
                        try {
                            app(FantasyPointsService::class)->processContest($record);
                            Notification::make()
                                ->title('Points calculated')
                                ->body($record->name . ': all teams ranked.')
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('Calculation failed')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->paginated(false)
            ->emptyStateHeading('All contests healthy')
            ->emptyStateDescription('No completed contests are awaiting points calculation.')
            ->emptyStateIcon('heroicon-o-check-circle');
    }
}   