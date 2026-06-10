<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PlayerResource\Pages;
use App\Models\League;
use App\Models\Player;
use App\Models\Team;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PlayerResource extends Resource
{
    protected static ?string $model = Player::class;

    protected static ?string $navigationIcon  = 'heroicon-o-user';
    protected static ?string $navigationLabel = 'Players';
    protected static ?string $navigationGroup = 'Foundation';
    protected static ?int    $navigationSort  = 3;

    // ──────────────────────────────────────────────────────────────────
    // FORM
    // ──────────────────────────────────────────────────────────────────

    public static function form(Form $form): Form
    {
        return $form->schema([

            Forms\Components\Section::make('Player identity')
                ->schema([

                    Forms\Components\TextInput::make('name')
                        ->label('Full name')
                        ->required()
                        ->maxLength(100)
                        ->placeholder('e.g. Virat Kohli')
                        ->columnSpanFull(),

                    // League picker — not persisted, just used to filter teams below
                    Forms\Components\Select::make('league_id_filter')
                        ->label('League')
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
                        ->afterStateUpdated(fn (Forms\Set $set) => $set('team_id', null))
                        ->helperText('Filter teams by league')
                        ->dehydrated(false),   // not saved to DB

                    Forms\Components\Select::make('team_id')
                        ->label('Team')
                        ->searchable()
                        ->nullable()
                        ->options(function (Get $get) {
                            $leagueId = $get('league_id_filter');

                            $query = Team::query()->orderBy('name');

                            if ($leagueId) {
                                $query->where('league_id', $leagueId);
                            }

                            return $query->get()->mapWithKeys(fn ($t) => [
                                $t->id => $t->name . ($t->short_name ? ' (' . $t->short_name . ')' : ''),
                            ]);
                        })
                        ->helperText('Leave empty for unsold / unassigned players'),

                ])
                ->columns(2),

            Forms\Components\Section::make('Role & playing style')
                ->schema([

                    Forms\Components\Select::make('role')
                        ->label('Role')
                        ->required()
                        ->options([
                            'WK'   => 'Wicket-keeper (WK)',
                            'BAT'  => 'Batsman (BAT)',
                            'ALL'  => 'All-rounder (ALL)',
                            'BOWL' => 'Bowler (BOWL)',
                        ])
                        ->helperText('Used for fantasy team composition rules'),

                    Forms\Components\TextInput::make('nationality')
                        ->label('Nationality')
                        ->maxLength(60)
                        ->placeholder('e.g. Indian'),

                    Forms\Components\Select::make('batting_style')
                        ->label('Batting style')
                        ->nullable()
                        ->options([
                            'Right-hand bat' => 'Right-hand bat',
                            'Left-hand bat'  => 'Left-hand bat',
                        ]),

                    Forms\Components\Select::make('bowling_style')
                        ->label('Bowling style')
                        ->nullable()
                        ->options([
                            'Right-arm fast'         => 'Right-arm fast',
                            'Right-arm medium'       => 'Right-arm medium',
                            'Right-arm off-break'    => 'Right-arm off-break',
                            'Right-arm leg-break'    => 'Right-arm leg-break',
                            'Left-arm fast'          => 'Left-arm fast',
                            'Left-arm medium'        => 'Left-arm medium',
                            'Left-arm orthodox'      => 'Left-arm orthodox',
                            'Left-arm wrist-spin'    => 'Left-arm wrist-spin',
                        ]),

                ])
                ->columns(2),

            Forms\Components\Section::make('Photo & status')
                ->schema([

                    Forms\Components\TextInput::make('photo_url')
                        ->label('Photo URL')
                        ->url()
                        ->maxLength(500)
                        ->placeholder('https://...')
                        ->suffixIcon('heroicon-o-photo')
                        ->columnSpanFull(),

                    Forms\Components\Toggle::make('is_active')
                        ->label('Active')
                        ->default(true)
                        ->helperText('Inactive players are hidden from fantasy team selection'),

                ])
                ->columns(2)
                ->collapsible()
                ->collapsed(fn ($record) => $record === null),

        ]);
    }

    // ──────────────────────────────────────────────────────────────────
    // TABLE
    // ──────────────────────────────────────────────────────────────────

    public static function table(Table $table): Table
    {
        return $table
            ->columns([

                Tables\Columns\ImageColumn::make('photo_url')
                    ->label('')
                    ->width(36)
                    ->height(36)
                    ->circular(),

                Tables\Columns\TextColumn::make('name')
                    ->label('Player')
                    ->searchable()
                    ->sortable()
                    ->weight('semibold'),

                Tables\Columns\BadgeColumn::make('role')
                    ->label('Role')
                    ->colors([
                        'danger'  => 'WK',
                        'info'    => 'BAT',
                        'warning' => 'ALL',
                        'success' => 'BOWL',
                    ]),

                Tables\Columns\TextColumn::make('team.name')
                    ->label('Team')
                    ->searchable()
                    ->sortable()
                    ->placeholder('Unassigned'),

                Tables\Columns\TextColumn::make('team.league.name')
                    ->label('League')
                    ->searchable()
                    ->limit(25)
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('nationality')
                    ->label('Nationality')
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('batting_style')
                    ->label('Batting')
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'Right-hand bat' => 'RHB',
                        'Left-hand bat'  => 'LHB',
                        default          => '—',
                    })
                    ->alignCenter()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('bowling_style')
                    ->label('Bowling style')
                    ->placeholder('—')
                    ->limit(22)
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->alignCenter()
                    ->trueColor('success')
                    ->falseColor('gray'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->date('d M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

            ])
            ->filters([

                Tables\Filters\SelectFilter::make('role')
                    ->label('Role')
                    ->options([
                        'WK'   => 'Wicket-keeper',
                        'BAT'  => 'Batsman',
                        'ALL'  => 'All-rounder',
                        'BOWL' => 'Bowler',
                    ]),

                Tables\Filters\SelectFilter::make('team_id')
                    ->label('Team')
                    ->searchable()
                    ->options(
                        Team::query()->orderBy('name')->pluck('name', 'id')
                    ),

                Tables\Filters\SelectFilter::make('league')
                    ->label('League')
                    ->searchable()
                    ->relationship('team.league', 'name')
                    ->options(
                        League::query()->orderBy('name')
                            ->get()
                            ->mapWithKeys(fn ($l) => [
                                $l->id => $l->name . ($l->season ? ' (' . $l->season . ')' : ''),
                            ])
                    ),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Status')
                    ->trueLabel('Active only')
                    ->falseLabel('Inactive only')
                    ->placeholder('All players'),

                Tables\Filters\SelectFilter::make('batting_style')
                    ->options([
                        'Right-hand bat' => 'Right-hand bat',
                        'Left-hand bat'  => 'Left-hand bat',
                    ]),

            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->requiresConfirmation(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([

                    Tables\Actions\BulkAction::make('activate')
                        ->label('Mark active')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(fn ($records) => $records->each->update(['is_active' => true])),

                    Tables\Actions\BulkAction::make('deactivate')
                        ->label('Mark inactive')
                        ->icon('heroicon-o-x-circle')
                        ->color('gray')
                        ->requiresConfirmation()
                        ->action(fn ($records) => $records->each->update(['is_active' => false])),

                    Tables\Actions\DeleteBulkAction::make(),

                ]),
            ])
            ->defaultSort('name');
    }

    // ──────────────────────────────────────────────────────────────────
    // PAGES
    // ──────────────────────────────────────────────────────────────────

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListPlayers::route('/'),
            'create' => Pages\CreatePlayer::route('/create'),
            'edit'   => Pages\EditPlayer::route('/{record}/edit'),
        ];
    }
}