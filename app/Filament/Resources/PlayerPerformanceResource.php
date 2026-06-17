<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PlayerPerformanceResource\Pages;
use App\Models\GameMatch;
use App\Models\MatchPlayer;
use App\Models\PlayerPerformance;
use App\Models\Team;
use App\Services\Cricket\PointsCalculator;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PlayerPerformanceResource extends Resource
{
    protected static ?string $model = PlayerPerformance::class;

    protected static ?string $navigationIcon  = 'heroicon-o-chart-bar';
    protected static ?string $navigationLabel = 'Performances';
    protected static ?string $navigationGroup = 'Match Management';
    protected static ?int    $navigationSort  = 2;

    // ──────────────────────────────────────────────────────────────────
    // FORM
    // ──────────────────────────────────────────────────────────────────

    public static function form(Form $form): Form
    {
        return $form->schema([

            // ── Match player assignment ───────────────────────────────
            Forms\Components\Section::make('Player & Match')
                ->schema([

                    Forms\Components\Select::make('match_id_filter')
                        ->label('Match')
                        ->searchable()
                        ->options(
                            GameMatch::query()
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
                        )
                        ->live()
                        ->afterStateUpdated(fn (Forms\Set $set) => $set('match_player_id', null))
                        ->dehydrated(false)
                        ->helperText('Filter to find the correct match player'),

                    Forms\Components\Select::make('match_player_id')
                        ->label('Player')
                        ->required()
                        ->searchable()
                        ->options(function (Get $get) {
                            $matchId = $get('match_id_filter');

                            $query = MatchPlayer::query()
                                ->with(['player', 'team'])
                                ->whereDoesntHave('performance');  // exclude already-scored

                            if ($matchId) {
                                $query->where('match_id', $matchId);
                            }

                            return $query->get()->mapWithKeys(fn ($mp) => [
                                $mp->id =>
                                    ($mp->player?->name ?? '?')
                                    . ' (' . ($mp->player?->role ?? '?') . ')'
                                    . ' — ' . ($mp->team?->name ?? '?'),
                            ]);
                        })
                        ->helperText('Only match players without a performance record are shown'),

                ])
                ->columns(2),

            // ── Batting ───────────────────────────────────────────────
            Forms\Components\Section::make('Batting')
                ->schema([

                    Forms\Components\TextInput::make('runs')
                        ->numeric()->default(0)->minValue(0),

                    Forms\Components\TextInput::make('balls_faced')
                        ->label('Balls faced')
                        ->numeric()->default(0)->minValue(0),

                    Forms\Components\TextInput::make('fours')
                        ->numeric()->default(0)->minValue(0),

                    Forms\Components\TextInput::make('sixes')
                        ->numeric()->default(0)->minValue(0),

                    Forms\Components\Select::make('out_status')
                        ->label('Out status')
                        ->required()
                        ->options([
                            'out'     => 'Out',
                            'not_out' => 'Not out',
                            'dnb'     => 'Did not bat',
                        ])
                        ->default('dnb'),

                ])
                ->columns(5),

            // ── Bowling ───────────────────────────────────────────────
            Forms\Components\Section::make('Bowling')
                ->schema([

                    Forms\Components\TextInput::make('overs')
                        ->numeric()->default(0)->minValue(0)->step(0.1)
                        ->helperText('e.g. 3.4 = 3 overs 4 balls'),

                    Forms\Components\TextInput::make('bowling_runs')
                        ->label('Runs conceded')
                        ->numeric()->default(0)->minValue(0),

                    Forms\Components\TextInput::make('wickets')
                        ->numeric()->default(0)->minValue(0)->maxValue(10),

                    Forms\Components\TextInput::make('maidens')
                        ->numeric()->default(0)->minValue(0),

                    Forms\Components\Toggle::make('lbw_or_bowled')
                        ->label('LBW / Bowled wicket')
                        ->default(false)
                        ->helperText('Any wicket was LBW or bowled'),

                    Forms\Components\TextInput::make('no_balls')
                        ->label('No balls')
                        ->numeric()->default(0)->minValue(0),

                    Forms\Components\TextInput::make('wides')
                        ->numeric()->default(0)->minValue(0),

                ])
                ->columns(4),

            // ── Fielding ──────────────────────────────────────────────
            Forms\Components\Section::make('Fielding')
                ->schema([

                    Forms\Components\TextInput::make('catches')
                        ->numeric()->default(0)->minValue(0),

                    Forms\Components\TextInput::make('stumpings')
                        ->numeric()->default(0)->minValue(0),

                    Forms\Components\TextInput::make('run_outs')
                        ->label('Run outs')
                        ->numeric()->default(0)->minValue(0),

                    Forms\Components\TextInput::make('byes')
                        ->numeric()->default(0)->minValue(0),

                    Forms\Components\TextInput::make('leg_byes')
                        ->label('Leg byes')
                        ->numeric()->default(0)->minValue(0),

                ])
                ->columns(5),

            // ── Fantasy points (computed) ─────────────────────────────
            Forms\Components\Section::make('Fantasy points')
                ->description('Calculated automatically — edit only if you need to override')
                ->schema([

                    Forms\Components\TextInput::make('fantasy_points')
                        ->label('Fantasy points')
                        ->numeric()
                        ->default(0)
                        ->helperText('Use the "Recalculate" button on the list page to recompute from stats'),

                ])
                ->columns(1)
                ->collapsible()
                ->collapsed(),

        ]);
    }

    // ──────────────────────────────────────────────────────────────────
    // TABLE
    // ──────────────────────────────────────────────────────────────────

    public static function table(Table $table): Table
    {
        return $table
            ->columns([

                Tables\Columns\TextColumn::make('matchPlayer.player.name')
                    ->label('Player')
                    ->searchable()
                    ->sortable()
                    ->weight('semibold'),

                Tables\Columns\BadgeColumn::make('matchPlayer.player.role')
                    ->label('Role')
                    ->colors([
                        'danger'  => 'WK',
                        'info'    => 'BAT',
                        'warning' => 'ALL',
                        'success' => 'BOWL',
                    ]),

                Tables\Columns\TextColumn::make('matchPlayer.team.name')
                    ->label('Team')
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('match_label')
                    ->label('Match')
                    ->getStateUsing(fn ($record) =>
                        ($record->matchPlayer?->match?->homeTeam?->name ?? '?')
                        . ' vs '
                        . ($record->matchPlayer?->match?->awayTeam?->name ?? '?')
                        . ($record->matchPlayer?->match?->start_time
                            ? ' · ' . $record->matchPlayer->match->start_time->format('d M')
                            : '')
                    ),

                // ── Batting ───────────────────────────────────────────
                Tables\Columns\TextColumn::make('runs')
                    ->alignCenter()->sortable(),

                Tables\Columns\TextColumn::make('balls_faced')
                    ->label('Balls')
                    ->alignCenter()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('fours')
                    ->label('4s')
                    ->alignCenter()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('sixes')
                    ->label('6s')
                    ->alignCenter()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\BadgeColumn::make('out_status')
                    ->label('Status')
                    ->colors([
                        'danger'  => 'out',
                        'success' => 'not_out',
                        'gray'    => 'dnb',
                    ])
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'out'     => 'Out',
                        'not_out' => 'Not out',
                        'dnb'     => 'DNB',
                        default   => $state,
                    }),

                // ── Bowling ───────────────────────────────────────────
                Tables\Columns\TextColumn::make('overs')
                    ->label('Ov')
                    ->alignCenter()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('wickets')
                    ->label('Wkts')
                    ->alignCenter()
                    ->sortable(),

                Tables\Columns\TextColumn::make('bowling_runs')
                    ->label('Runs (Bowl)')
                    ->alignCenter()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('maidens')
                    ->label('Mdn')
                    ->alignCenter()
                    ->toggleable(isToggledHiddenByDefault: true),

                // ── Fielding ──────────────────────────────────────────
                Tables\Columns\TextColumn::make('catches')
                    ->label('Ct')
                    ->alignCenter()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('stumpings')
                    ->label('St')
                    ->alignCenter()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('run_outs')
                    ->label('RO')
                    ->alignCenter()
                    ->toggleable(isToggledHiddenByDefault: true),

                // ── Fantasy points ────────────────────────────────────
                Tables\Columns\TextColumn::make('fantasy_points')
                    ->label('Pts')
                    ->sortable()
                    ->alignCenter()
                    ->weight('bold')
                    ->color(fn ($state) => match (true) {
                        $state >= 100 => 'success',
                        $state >= 50  => 'warning',
                        $state > 0    => 'primary',
                        default       => 'gray',
                    }),

            ])
            ->filters([

                Tables\Filters\SelectFilter::make('match_id')
                    ->label('Match')
                    ->searchable()
                    ->options(
                        GameMatch::query()
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
                    )
                    ->query(fn ($query, array $data) =>
                        $data['value']
                            ? $query->whereHas('matchPlayer', fn ($q) => $q->where('match_id', $data['value']))
                            : $query
                    ),

                Tables\Filters\SelectFilter::make('team_id')
                    ->label('Team')
                    ->searchable()
                    ->options(Team::query()->orderBy('name')->pluck('name', 'id'))
                    ->query(fn ($query, array $data) =>
                        $data['value']
                            ? $query->whereHas('matchPlayer', fn ($q) => $q->where('team_id', $data['value']))
                            : $query
                    ),

                Tables\Filters\SelectFilter::make('role')
                    ->label('Role')
                    ->options([
                        'WK'   => 'Wicket-keeper',
                        'BAT'  => 'Batsman',
                        'ALL'  => 'All-rounder',
                        'BOWL' => 'Bowler',
                    ])
                    ->query(fn ($query, array $data) =>
                        $data['value']
                            ? $query->whereHas('matchPlayer.player', fn ($q) => $q->where('role', $data['value']))
                            : $query
                    ),

                Tables\Filters\SelectFilter::make('out_status')
                    ->label('Bat status')
                    ->options([
                        'out'     => 'Out',
                        'not_out' => 'Not out',
                        'dnb'     => 'Did not bat',
                    ]),

                Tables\Filters\Filter::make('high_scorers')
                    ->label('High scorers (50+ pts)')
                    ->query(fn ($query) => $query->where('fantasy_points', '>=', 50)),

            ])
            ->actions([

                Tables\Actions\Action::make('recalculate')
                    ->label('Recalculate pts')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalDescription('This will recompute fantasy_points from the current stats for this player only.')
                    ->action(function ($record) {
                        $points = app(PointsCalculator::class)->calculate($record);
                        $record->update(['fantasy_points' => $points]);

                        Notification::make()
                            ->title('Points recalculated')
                            ->body($record->matchPlayer?->player?->name . ': ' . $points . ' pts')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()->requiresConfirmation(),

            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([

                    Tables\Actions\BulkAction::make('bulk_recalculate')
                        ->label('Recalculate points')
                        ->icon('heroicon-o-arrow-path')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->modalDescription('This will recompute fantasy_points from current stats for all selected players.')
                        ->action(function ($records) {
                            $calculator = app(PointsCalculator::class);
                            $records->each(function ($record) use ($calculator) {
                                $points = $calculator->calculate($record);
                                $record->update(['fantasy_points' => $points]);
                            });

                            Notification::make()
                                ->title('Points recalculated')
                                ->body('Updated ' . $records->count() . ' performances.')
                                ->success()
                                ->send();
                        }),

                    Tables\Actions\DeleteBulkAction::make(),

                ]),
            ])
            ->defaultSort('fantasy_points', 'desc');
    }

    // ──────────────────────────────────────────────────────────────────
    // PAGES
    // ──────────────────────────────────────────────────────────────────

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListPlayerPerformances::route('/'),
            'create' => Pages\CreatePlayerPerformance::route('/create'),
            'edit'   => Pages\EditPlayerPerformance::route('/{record}/edit'),
        ];
    }
}