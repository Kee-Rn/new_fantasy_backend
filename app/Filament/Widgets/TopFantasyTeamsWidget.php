<?php

namespace App\Filament\Widgets;

use App\Models\FantasyTeam;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class TopFantasyTeamsWidget extends BaseWidget
{
    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Top 5 Fantasy Teams';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                FantasyTeam::query()
                    ->with(['user', 'contest.match.homeTeam', 'contest.match.awayTeam'])
                    ->whereNotNull('total_points')
                    ->where('total_points', '>', 0)
                    ->orderByDesc('total_points')
                    ->limit(5)
            )
            ->columns([

                Tables\Columns\TextColumn::make('rank_position')
                    ->label('#')
                    ->alignCenter()
                    ->getStateUsing(fn ($record, $rowLoop) => $rowLoop->iteration)
                    ->formatStateUsing(fn ($state) => match((int) $state) {
                        1 => '🥇',
                        2 => '🥈',
                        3 => '🥉',
                        default => $state,
                    })
                    ->searchable(false),

                Tables\Columns\TextColumn::make('team_name')
                    ->label('Team')
                    ->weight('semibold')
                    ->searchable(false),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('User')
                    ->searchable(false),

                Tables\Columns\TextColumn::make('contest.name')
                    ->label('Contest')
                    ->badge()
                    ->color('gray')
                    ->searchable(false),

                Tables\Columns\TextColumn::make('match')
                    ->label('Match')
                    ->getStateUsing(fn ($record) =>
                        ($record->contest?->match?->homeTeam?->name ?? '?')
                        . ' vs '
                        . ($record->contest?->match?->awayTeam?->name ?? '?')
                    )
                    ->searchable(false),

                Tables\Columns\TextColumn::make('total_points')
                    ->label('Points')
                    ->alignCenter()
                    ->weight('bold')
                    ->color('success')
                    ->formatStateUsing(fn ($state) => number_format($state))
                    ->searchable(false),

            ])
            ->paginated(false)
            ->emptyStateHeading('No ranked teams yet')
            ->emptyStateDescription('Teams will appear here once points are calculated for completed contests.')
            ->emptyStateIcon('heroicon-o-trophy');
    }
}