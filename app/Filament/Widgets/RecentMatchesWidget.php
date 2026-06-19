<?php

namespace App\Filament\Widgets;

use App\Models\GameMatch;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class RecentMatchesWidget extends BaseWidget
{
    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Recent Matches';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                GameMatch::query()
                    ->with(['homeTeam', 'awayTeam', 'league'])
                    ->orderByRaw("FIELD(status, 'live', 'upcoming', 'completed', 'cancelled')")
                    ->orderBy('start_time', 'desc')
                    ->limit(8)
            )
            ->columns([

                Tables\Columns\TextColumn::make('match')
                    ->label('Match')
                    ->getStateUsing(fn ($record) =>
                        ($record->homeTeam?->name ?? '?') . ' vs ' . ($record->awayTeam?->name ?? '?')
                    )
                    ->weight('semibold')
                    ->searchable(false),

                Tables\Columns\TextColumn::make('league.name')
                    ->label('League')
                    ->badge()
                    ->color('gray')
                    ->searchable(false),

                Tables\Columns\TextColumn::make('match_type')
                    ->label('Type')
                    ->badge()
                    ->color('primary')
                    ->searchable(false),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn ($state) => match($state) {
                        'live'      => 'danger',
                        'upcoming'  => 'warning',
                        'completed' => 'success',
                        'cancelled' => 'gray',
                        default     => 'gray',
                    })
                    ->searchable(false),

                Tables\Columns\TextColumn::make('start_time')
                    ->label('Date')
                    ->dateTime('d M Y, H:i')
                    ->sortable()
                    ->searchable(false),

                Tables\Columns\TextColumn::make('result')
                    ->label('Result')
                    ->placeholder('—')
                    ->wrap()
                    ->searchable(false),

            ])
            ->actions([
                Tables\Actions\Action::make('score')
                    ->label('Score')
                    ->icon('heroicon-m-play-circle')
                    ->color('success')
                    ->url(fn ($record) =>
                        route('filament.admin.resources.ball-by-balls.score', []) . '?match_id=' . $record->id
                    )
                    ->visible(fn ($record) => $record->status === 'live'),

                Tables\Actions\Action::make('view')
                    ->label('View')
                    ->icon('heroicon-m-eye')
                    ->color('gray')
                    ->url(fn ($record) =>
                        route('filament.admin.resources.game-matches.edit', $record)
                    ),
            ])
            ->paginated(false);
    }
}