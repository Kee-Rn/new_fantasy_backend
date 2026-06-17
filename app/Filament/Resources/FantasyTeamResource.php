<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FantasyTeamResource\Pages;
use App\Models\FantasyContest;
use App\Models\FantasyTeam;
use App\Models\Player;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class FantasyTeamResource extends Resource
{
    protected static ?string $model = FantasyTeam::class;

    protected static ?string $navigationIcon  = 'heroicon-o-squares-2x2';
    protected static ?string $navigationLabel = 'Fantasy Teams';
    protected static ?string $navigationGroup = 'Fantasy';
    protected static ?int    $navigationSort  = 2;

    // ──────────────────────────────────────────────────────────────────
    // FORM
    // ──────────────────────────────────────────────────────────────────

    public static function form(Form $form): Form
    {
        return $form->schema([

            // ── Ownership ─────────────────────────────────────────────
            Forms\Components\Section::make('Ownership')
                ->schema([

                    Forms\Components\Select::make('user_id')
                        ->label('User')
                        ->required()
                        ->searchable()
                        ->options(
                            User::query()
                                ->orderBy('name')
                                ->get()
                                ->mapWithKeys(fn ($u) => [
                                    $u->id => $u->name . ' (' . $u->email . ')',
                                ])
                        ),

                    Forms\Components\Select::make('contest_id')
                        ->label('Contest')
                        ->required()
                        ->searchable()
                        ->options(
                            FantasyContest::query()
                                ->with('match.homeTeam', 'match.awayTeam')
                                ->orderBy('created_at', 'desc')
                                ->get()
                                ->mapWithKeys(fn ($c) => [
                                    $c->id =>
                                        $c->name
                                        . ' — '
                                        . ($c->match?->homeTeam?->name ?? '?')
                                        . ' vs '
                                        . ($c->match?->awayTeam?->name ?? '?')
                                        . ($c->match?->start_time
                                            ? ' (' . $c->match->start_time->format('d M Y') . ')'
                                            : ''),
                                ])
                        )
                        ->live()
                        ->afterStateUpdated(fn (Forms\Set $set) => $set('players', [])),

                ])
                ->columns(2),

            // ── Team info ─────────────────────────────────────────────
            Forms\Components\Section::make('Team info')
                ->schema([

                    Forms\Components\TextInput::make('team_name')
                        ->label('Team name')
                        ->required()
                        ->maxLength(100)
                        ->placeholder('e.g. Super Strikers XI'),

                ])
                ->columns(1),

            // ── Player selection ──────────────────────────────────────
            Forms\Components\Section::make('Player selection')
                ->description('Select exactly 11 players. Assign one captain (2× points) and one vice-captain (1.5× points).')
                ->schema([

                    Forms\Components\Repeater::make('fantasyTeamPlayers')
                        ->label('Players')
                        ->relationship('fantasyTeamPlayers')
                        ->schema([

                            Forms\Components\Select::make('player_id')
                                ->label('Player')
                                ->required()
                                ->searchable()
                                ->options(function (Get $get) {
                                    $contestId = $get('../../contest_id');
                                    if (! $contestId) return [];

                                    $contest = FantasyContest::with('match')->find($contestId);
                                    if (! $contest?->match) return [];

                                    $matchId = $contest->match->id;

                                    return Player::query()
                                        ->whereHas('matchPlayers', fn ($q) =>
                                            $q->where('match_id', $matchId)
                                              ->where('is_playing_xi', true)
                                        )
                                        ->with('team')
                                        ->orderBy('name')
                                        ->get()
                                        ->mapWithKeys(fn ($p) => [
                                            $p->id => $p->name
                                                . ' (' . $p->role . ')'
                                                . ($p->team ? ' — ' . $p->team->name : ''),
                                        ])
                                        ->toArray();
                                })
                                ->helperText('Only confirmed Playing XI players shown')
                                ->columnSpan(2),

                            Forms\Components\Toggle::make('is_captain')
                                ->label('Captain (2×)')
                                ->default(false)
                                ->live()
                                ->afterStateUpdated(function (Forms\Set $set, $state) {
                                    if ($state) {
                                        $set('is_vice_captain', false);
                                    }
                                }),

                            Forms\Components\Toggle::make('is_vice_captain')
                                ->label('Vice-captain (1.5×)')
                                ->default(false)
                                ->live()
                                ->afterStateUpdated(function (Forms\Set $set, $state) {
                                    if ($state) {
                                        $set('is_captain', false);
                                    }
                                }),

                        ])
                        ->columns(4)
                        ->minItems(11)
                        ->maxItems(11)
                        ->addActionLabel('Add player')
                        ->reorderable(false)
                        ->collapsible()
                        ->itemLabel(fn (array $state): ?string =>
                            $state['player_id']
                                ? (Player::find($state['player_id'])?->name ?? 'Player')
                                : 'New player'
                        ),

                ])
                ->collapsible()
                ->collapsed(fn ($record) => $record === null),

            // ── Points & rank (read-only) ─────────────────────────────
            Forms\Components\Section::make('Points & rank')
                ->description('Set automatically after points are calculated — do not edit manually unless correcting an error')
                ->schema([

                    Forms\Components\TextInput::make('total_points')
                        ->label('Total points')
                        ->numeric()
                        ->default(0)
                        ->disabled()
                        ->dehydrated(),

                    Forms\Components\TextInput::make('rank')
                        ->label('Rank')
                        ->numeric()
                        ->nullable()
                        ->disabled()
                        ->dehydrated()
                        ->placeholder('—'),

                ])
                ->columns(2)
                ->collapsible()
                ->collapsed(fn ($record) => $record?->total_points == 0),

        ]);
    }

    // ──────────────────────────────────────────────────────────────────
    // TABLE
    // ──────────────────────────────────────────────────────────────────

    public static function table(Table $table): Table
    {
        return $table
            ->columns([

                Tables\Columns\TextColumn::make('team_name')
                    ->label('Team name')
                    ->searchable()
                    ->sortable()
                    ->weight('semibold'),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('User')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('user.email')
                    ->label('Email')
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('contest.name')
                    ->label('Contest')
                    ->searchable()
                    ->sortable()
                    ->limit(25),

                Tables\Columns\TextColumn::make('match_label')
                    ->label('Match')
                    ->getStateUsing(fn ($record) =>
                        ($record->contest?->match?->homeTeam?->name ?? '?')
                        . ' vs '
                        . ($record->contest?->match?->awayTeam?->name ?? '?')
                        . ($record->contest?->match?->start_time
                            ? ' · ' . $record->contest->match->start_time->format('d M')
                            : '')
                    ),

                Tables\Columns\TextColumn::make('total_points')
                    ->label('Points')
                    ->sortable()
                    ->alignCenter()
                    ->weight('bold')
                    ->color(fn ($state) => $state > 0 ? 'success' : 'gray'),

                Tables\Columns\TextColumn::make('rank')
                    ->label('Rank')
                    ->sortable()
                    ->alignCenter()
                    ->placeholder('—')
                    ->formatStateUsing(fn ($state) => $state ? '#' . $state : '—'),

                Tables\Columns\TextColumn::make('fantasyTeamPlayers_count')
                    ->label('Players')
                    ->counts('fantasyTeamPlayers')
                    ->alignCenter()
                    ->badge()
                    ->color(fn ($state) => $state == 11 ? 'success' : 'danger'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Submitted')
                    ->dateTime('d M Y, H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

            ])
            ->filters([

                Tables\Filters\SelectFilter::make('contest_id')
                    ->label('Contest')
                    ->searchable()
                    ->options(
                        FantasyContest::query()
                            ->with('match.homeTeam', 'match.awayTeam')
                            ->orderBy('created_at', 'desc')
                            ->get()
                            ->mapWithKeys(fn ($c) => [
                                $c->id =>
                                    $c->name
                                    . ' — '
                                    . ($c->match?->homeTeam?->name ?? '?')
                                    . ' vs '
                                    . ($c->match?->awayTeam?->name ?? '?'),
                            ])
                    ),

                Tables\Filters\SelectFilter::make('user_id')
                    ->label('User')
                    ->searchable()
                    ->options(
                        User::query()->orderBy('name')
                            ->get()
                            ->mapWithKeys(fn ($u) => [
                                $u->id => $u->name . ' (' . $u->email . ')',
                            ])
                    ),

                Tables\Filters\Filter::make('incomplete_teams')
                    ->label('Incomplete teams (< 11 players)')
                    ->query(fn ($query) => $query->withCount('fantasyTeamPlayers')
                        ->having('fantasy_team_players_count', '<', 11)
                    ),

                Tables\Filters\Filter::make('ranked')
                    ->label('Ranked teams only')
                    ->query(fn ($query) => $query->whereNotNull('rank')),

            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()->requiresConfirmation(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('total_points', 'desc');
    }

    // ──────────────────────────────────────────────────────────────────
    // PAGES
    // ──────────────────────────────────────────────────────────────────

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListFantasyTeams::route('/'),
            'create' => Pages\CreateFantasyTeam::route('/create'),
            'edit'   => Pages\EditFantasyTeam::route('/{record}/edit'),
            'view'   => Pages\ViewFantasyTeam::route('/{record}'),
        ];
    }
}