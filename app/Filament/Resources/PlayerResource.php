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

            Forms\Components\Section::make('Player details')
                ->schema([

                    Forms\Components\TextInput::make('name')
                        ->label('Full name')
                        ->required()
                        ->maxLength(100)
                        ->placeholder('e.g. Rohit Paudel')
                        ->columnSpanFull(),

                    // League filter — not persisted, just filters team dropdown
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
                        ->dehydrated(false),

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

                            return $query->pluck('name', 'id');
                        })
                        ->helperText('Leave empty for unassigned players'),

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

                    Forms\Components\Toggle::make('is_active')
                        ->label('Active')
                        ->default(true)
                        ->helperText('Inactive players are hidden from fantasy team selection'),

                ])
                ->columns(2),

            Forms\Components\Section::make('Photo')
                ->schema([

                    Forms\Components\FileUpload::make('photo_path')
                        ->label('Player photo')
                        ->image()
                        ->disk('public')
                        ->directory('photos/players')
                        ->imageResizeMode('cover')
                        ->imageCropAspectRatio('1:1')
                        ->imageResizeTargetWidth('300')
                        ->imageResizeTargetHeight('300')
                        ->maxSize(2048)
                        ->helperText('Square image recommended. Max 2MB.'),

                ])
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

                Tables\Columns\ImageColumn::make('photo_path')
                    ->label('')
                    ->disk('public')
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
                    ->options(Team::query()->orderBy('name')->pluck('name', 'id')),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Status')
                    ->trueLabel('Active only')
                    ->falseLabel('Inactive only')
                    ->placeholder('All players'),

            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()->requiresConfirmation(),
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