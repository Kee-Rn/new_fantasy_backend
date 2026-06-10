<?php

namespace App\Filament\Resources;

use App\Filament\Resources\GameMatchResource\Pages;
use App\Models\GameMatch;
use App\Models\League;
use App\Models\Team;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class GameMatchResource extends Resource
{
    protected static ?string $model = GameMatch::class;

    protected static ?string $navigationIcon  = 'heroicon-o-calendar-days';
    protected static ?string $navigationLabel = 'Matches';
    protected static ?string $navigationGroup = 'Foundation';
    protected static ?int    $navigationSort  = 4;

    // ──────────────────────────────────────────────────────────────────
    // FORM
    // ──────────────────────────────────────────────────────────────────

    public static function form(Form $form): Form
    {
        return $form->schema([

            // ── League & Teams ────────────────────────────────────────
            Forms\Components\Section::make('League & Teams')
                ->schema([

                    Forms\Components\Select::make('league_id')
                        ->label('League')
                        ->required()
                        ->searchable()
                        ->options(
                            League::query()
                                ->where('is_active', true)
                                ->orderBy('name')
                                ->get()
                                ->mapWithKeys(fn ($l) => [
                                    $l->id => $l->name . ($l->season ? ' (' . $l->season . ')' : ''),
                                ])
                        )
                        ->live()
                        ->afterStateUpdated(function (Forms\Set $set) {
                            $set('home_team_id', null);
                            $set('away_team_id', null);
                            $set('batting_first_team_id', null);
                        })
                        ->columnSpanFull(),

                    Forms\Components\Select::make('home_team_id')
                        ->label('Home team')
                        ->required()
                        ->searchable()
                        ->options(fn (Get $get) => self::getTeamOptions($get('league_id')))
                        ->live()
                        ->afterStateUpdated(fn (Forms\Set $set) => $set('batting_first_team_id', null))
                        ->helperText('Select league first to filter teams'),

                    Forms\Components\Select::make('away_team_id')
                        ->label('Away team')
                        ->required()
                        ->searchable()
                        ->options(fn (Get $get) => self::getTeamOptions($get('league_id')))
                        ->live()
                        ->afterStateUpdated(fn (Forms\Set $set) => $set('batting_first_team_id', null))
                        ->helperText('Select league first to filter teams'),

                ])
                ->columns(2),

            // ── Schedule ──────────────────────────────────────────────
            Forms\Components\Section::make('Schedule')
                ->schema([

                    Forms\Components\TextInput::make('match_number')
                        ->label('Match number')
                        ->numeric()
                        ->nullable()
                        ->placeholder('e.g. 1'),

                    Forms\Components\Select::make('match_type')
                        ->label('Match type')
                        ->required()
                        ->options([
                            'T20'  => 'T20',
                            'ODI'  => 'ODI',
                            'Test' => 'Test',
                            'T10'  => 'T10',
                        ])
                        ->default('T20'),

                    Forms\Components\DateTimePicker::make('start_time')
                        ->label('Start time')
                        ->nullable()
                        ->seconds(false)
                        ->timezone('Asia/Kathmandu'),

                    Forms\Components\Select::make('status')
                        ->label('Status')
                        ->required()
                        ->options([
                            'upcoming'  => 'Upcoming',
                            'live'      => 'Live',
                            'completed' => 'Completed',
                            'cancelled' => 'Cancelled',
                        ])
                        ->default('upcoming'),

                    Forms\Components\TextInput::make('venue')
                        ->label('Venue')
                        ->maxLength(150)
                        ->placeholder('e.g. Wankhede Stadium'),

                    Forms\Components\TextInput::make('city')
                        ->label('City')
                        ->maxLength(60)
                        ->placeholder('e.g. Mumbai'),

                    Forms\Components\Toggle::make('is_featured')
                        ->label('Featured match')
                        ->default(false)
                        ->helperText('Show this match prominently on the home screen'),

                ])
                ->columns(2),

            // ── Toss (filled after toss) ──────────────────────────────
            Forms\Components\Section::make('Toss')
                ->description('Fill in after the toss is done')
                ->schema([

                    Forms\Components\Select::make('batting_first_team_id')
                        ->label('Batting first')
                        ->searchable()
                        ->nullable()
                        ->options(function (Get $get) {
                            $home = $get('home_team_id');
                            $away = $get('away_team_id');

                            if (! $home && ! $away) {
                                return [];
                            }

                            return Team::query()
                                ->whereIn('id', array_filter([$home, $away]))
                                ->pluck('name', 'id');
                        })
                        ->helperText('Set after toss — determines batting/fielding team in ball-by-ball entry'),

                    Forms\Components\Select::make('toss_winner')
                        ->label('Toss won by')
                        ->nullable()
                        ->options(function (Get $get) {
                            $home = $get('home_team_id');
                            $away = $get('away_team_id');

                            if (! $home && ! $away) {
                                return [];
                            }

                            return Team::query()
                                ->whereIn('id', array_filter([$home, $away]))
                                ->pluck('name', 'id');
                        }),

                    Forms\Components\Select::make('toss_decision')
                        ->label('Toss decision')
                        ->nullable()
                        ->options([
                            'bat'  => 'Elected to bat',
                            'bowl' => 'Elected to bowl',
                        ]),

                ])
                ->columns(3)
                ->collapsible(),

            // ── Result (filled after match) ───────────────────────────
            Forms\Components\Section::make('Result')
                ->description('Fill in after the match is completed')
                ->schema([

                    Forms\Components\TextInput::make('result')
                        ->label('Result summary')
                        ->maxLength(200)
                        ->placeholder('e.g. Mumbai Indians won by 6 wickets')
                        ->columnSpanFull(),

                    Forms\Components\Select::make('result_type')
                        ->label('Result type')
                        ->nullable()
                        ->options([
                            'runs'      => 'Won by runs',
                            'wickets'   => 'Won by wickets',
                            'super_over'=> 'Super over',
                            'dls'       => 'DLS method',
                            'tie'       => 'Tie',
                            'no_result' => 'No result',
                            'abandoned' => 'Abandoned',
                        ]),

                ])
                ->columns(2)
                ->collapsible()
                ->collapsed(fn ($record) => $record?->status !== 'completed'),

        ]);
    }

    // ──────────────────────────────────────────────────────────────────
    // TABLE
    // ──────────────────────────────────────────────────────────────────

    public static function table(Table $table): Table
    {
        return $table
            ->columns([

                Tables\Columns\TextColumn::make('match_number')
                    ->label('#')
                    ->sortable()
                    ->alignCenter()
                    ->placeholder('—')
                    ->width(50),

                Tables\Columns\TextColumn::make('match_label')
                    ->label('Match')
                    ->getStateUsing(fn ($record) =>
                        ($record->homeTeam?->short_name ?? $record->homeTeam?->name ?? '?')
                        . ' vs '
                        . ($record->awayTeam?->short_name ?? $record->awayTeam?->name ?? '?')
                    )
                    ->weight('semibold')
                    ->searchable(query: fn ($query, $search) =>
                        $query->whereHas('homeTeam', fn ($q) => $q->where('name', 'like', "%{$search}%"))
                              ->orWhereHas('awayTeam', fn ($q) => $q->where('name', 'like', "%{$search}%"))
                    ),

                Tables\Columns\TextColumn::make('league.name')
                    ->label('League')
                    ->sortable()
                    ->limit(25),

                Tables\Columns\BadgeColumn::make('match_type')
                    ->label('Format')
                    ->colors([
                        'success' => 'T20',
                        'info'    => 'ODI',
                        'warning' => 'Test',
                        'gray'    => 'T10',
                    ]),

                Tables\Columns\TextColumn::make('start_time')
                    ->label('Start time')
                    ->dateTime('d M Y, H:i')
                    ->sortable()
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('venue')
                    ->label('Venue')
                    ->placeholder('—')
                    ->limit(25)
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'gray'    => 'upcoming',
                        'success' => 'live',
                        'primary' => 'completed',
                        'danger'  => 'cancelled',
                    ])
                    ->sortable(),

                Tables\Columns\TextColumn::make('battingFirstTeam.short_name')
                    ->label('Bats first')
                    ->placeholder('—')
                    ->alignCenter()
                    ->badge()
                    ->color('warning'),

                Tables\Columns\TextColumn::make('result')
                    ->label('Result')
                    ->placeholder('—')
                    ->limit(40)
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\IconColumn::make('is_featured')
                    ->label('Featured')
                    ->boolean()
                    ->alignCenter()
                    ->trueColor('warning')
                    ->falseColor('gray'),

                Tables\Columns\TextColumn::make('fantasyContests_count')
                    ->label('Contests')
                    ->counts('fantasyContests')
                    ->alignCenter()
                    ->sortable(),

            ])
            ->filters([

                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'upcoming'  => 'Upcoming',
                        'live'      => 'Live',
                        'completed' => 'Completed',
                        'cancelled' => 'Cancelled',
                    ]),

                Tables\Filters\SelectFilter::make('league_id')
                    ->label('League')
                    ->searchable()
                    ->options(
                        League::query()->orderBy('name')
                            ->get()
                            ->mapWithKeys(fn ($l) => [
                                $l->id => $l->name . ($l->season ? ' (' . $l->season . ')' : ''),
                            ])
                    ),

                Tables\Filters\SelectFilter::make('match_type')
                    ->label('Format')
                    ->options([
                        'T20'  => 'T20',
                        'ODI'  => 'ODI',
                        'Test' => 'Test',
                        'T10'  => 'T10',
                    ]),

                Tables\Filters\TernaryFilter::make('is_featured')
                    ->label('Featured')
                    ->trueLabel('Featured only')
                    ->falseLabel('Not featured')
                    ->placeholder('All matches'),

                Tables\Filters\Filter::make('today')
                    ->label('Today\'s matches')
                    ->query(fn ($query) => $query->whereDate('start_time', today())),

            ])
            ->actions([

                Tables\Actions\Action::make('go_to_ball_by_ball')
                    ->label('Score')
                    ->icon('heroicon-o-play-circle')
                    ->color('success')
                    ->url(fn ($record) => BallByBallResource::getUrl('create') . '?match_id=' . $record->id)
                    ->visible(fn ($record) => $record->status === 'live')
                    ->openUrlInNewTab(),

                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()->requiresConfirmation(),

            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('start_time', 'desc');
    }

    // ──────────────────────────────────────────────────────────────────
    // HELPERS
    // ──────────────────────────────────────────────────────────────────

    /**
     * Teams filtered by league_id, or all teams if no league selected.
     */
    private static function getTeamOptions(?int $leagueId): array
    {
        $query = Team::query()->orderBy('name');

        if ($leagueId) {
            $query->where('league_id', $leagueId);
        }

        return $query->get()->mapWithKeys(fn ($t) => [
            $t->id => $t->name . ($t->short_name ? ' (' . $t->short_name . ')' : ''),
        ])->toArray();
    }

    // ──────────────────────────────────────────────────────────────────
    // PAGES
    // ──────────────────────────────────────────────────────────────────

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListGameMatches::route('/'),
            'create' => Pages\CreateGameMatch::route('/create'),
            'edit'   => Pages\EditGameMatch::route('/{record}/edit'),
        ];
    }
}