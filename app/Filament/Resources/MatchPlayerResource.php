<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MatchPlayerResource\Pages;
use App\Models\GameMatch;
use App\Models\League;
use App\Models\MatchPlayer;
use App\Models\Player;
use App\Models\Team;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class MatchPlayerResource extends Resource
{
    protected static ?string $model = MatchPlayer::class;

    protected static ?string $navigationIcon  = 'heroicon-o-clipboard-document-list';
    protected static ?string $navigationLabel = 'Match Players';
    protected static ?string $navigationGroup = 'Match Management';
    protected static ?int    $navigationSort  = 1;

    // ──────────────────────────────────────────────────────────────────
    // FORM
    // ──────────────────────────────────────────────────────────────────

    public static function form(Form $form): Form
    {
        return $form->schema([

            // ── Match & Team ──────────────────────────────────────────
            Forms\Components\Section::make('Match & Team')
                ->schema([

                    Forms\Components\Select::make('match_id')
                        ->label('Match')
                        ->required()
                        ->searchable()
                        ->options(
                            GameMatch::query()
                                ->whereIn('status', ['upcoming', 'live'])
                                ->with(['homeTeam', 'awayTeam'])
                                ->orderBy('start_time', 'desc')
                                ->get()
                                ->mapWithKeys(fn ($m) => [
                                    $m->id =>
                                        ($m->homeTeam?->short_name ?? $m->homeTeam?->name ?? '?')
                                        . ' vs '
                                        . ($m->awayTeam?->short_name ?? $m->awayTeam?->name ?? '?')
                                        . ($m->start_time ? ' — ' . $m->start_time->format('d M Y') : ''),
                                ])
                        )
                        ->live()
                        ->afterStateUpdated(function (Forms\Set $set) {
                            $set('team_id', null);
                            $set('player_id', null);
                        })
                        ->helperText('Only upcoming and live matches shown')
                        ->columnSpanFull(),

                    Forms\Components\Select::make('team_id')
                        ->label('Team')
                        ->required()
                        ->searchable()
                        ->options(function (Get $get) {
                            $matchId = $get('match_id');
                            if (! $matchId) return [];

                            $match = GameMatch::with(['homeTeam', 'awayTeam'])->find($matchId);
                            if (! $match) return [];

                            return collect([
                                $match->homeTeam,
                                $match->awayTeam,
                            ])
                            ->filter()
                            ->mapWithKeys(fn ($t) => [
                                $t->id => $t->name . ($t->short_name ? ' (' . $t->short_name . ')' : ''),
                            ])
                            ->toArray();
                        })
                        ->live()
                        ->afterStateUpdated(fn (Forms\Set $set) => $set('player_id', null))
                        ->helperText('Only the two teams in the selected match are shown'),

                    Forms\Components\Select::make('player_id')
                        ->label('Player')
                        ->required()
                        ->searchable()
                        ->options(function (Get $get) {
                            $teamId  = $get('team_id');
                            $matchId = $get('match_id');
                            if (! $teamId) return [];

                            // Exclude players already added to this match
                            $alreadyAdded = $matchId
                                ? MatchPlayer::where('match_id', $matchId)->pluck('player_id')->toArray()
                                : [];

                            return Player::query()
                                ->where('team_id', $teamId)
                                ->where('is_active', true)
                                ->whereNotIn('id', $alreadyAdded)
                                ->orderBy('name')
                                ->get()
                                ->mapWithKeys(fn ($p) => [
                                    $p->id => $p->name . ' (' . $p->role . ')',
                                ])
                                ->toArray();
                        })
                        ->helperText('Active players from the selected team — already added players are excluded'),

                ])
                ->columns(2),

            // ── Playing status ────────────────────────────────────────
            Forms\Components\Section::make('Playing status')
                ->description('Set before the match starts. Playing XI drives fantasy team eligibility.')
                ->schema([

                    Forms\Components\Toggle::make('is_playing_xi')
                        ->label('Playing XI')
                        ->default(false)
                        ->live()
                        ->afterStateUpdated(function (Forms\Set $set, $state) {
                            // Playing XI and bench are mutually exclusive
                            if ($state) {
                                $set('is_bench', false);
                            }
                        }),

                    Forms\Components\Toggle::make('is_bench')
                        ->label('Bench / substitute')
                        ->default(false)
                        ->live()
                        ->afterStateUpdated(function (Forms\Set $set, $state) {
                            if ($state) {
                                $set('is_playing_xi', false);
                            }
                        }),

                    Forms\Components\TextInput::make('batting_order')
                        ->label('Batting order')
                        ->numeric()
                        ->nullable()
                        ->minValue(1)
                        ->maxValue(11)
                        ->placeholder('1–11')
                        ->helperText('Set once Playing XI is confirmed'),

                ])
                ->columns(3),

        ]);
    }

    // ──────────────────────────────────────────────────────────────────
    // TABLE
    // ──────────────────────────────────────────────────────────────────

    public static function table(Table $table): Table
    {
        return $table
            ->columns([

                Tables\Columns\TextColumn::make('player.name')
                    ->label('Player')
                    ->searchable()
                    ->sortable()
                    ->weight('semibold'),

                Tables\Columns\BadgeColumn::make('player.role')
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
                    ->sortable(),

                Tables\Columns\TextColumn::make('match_label')
                    ->label('Match')
                    ->getStateUsing(fn ($record) =>
                        ($record->match?->homeTeam?->short_name ?? '?')
                        . ' vs '
                        . ($record->match?->awayTeam?->short_name ?? '?')
                        . ($record->match?->start_time
                            ? ' · ' . $record->match->start_time->format('d M')
                            : '')
                    )
                    ->searchable(query: fn ($query, $search) =>
                        $query->whereHas('match', function ($q) use ($search) {
                            $q->whereHas('homeTeam', fn ($q2) => $q2->where('name', 'like', "%{$search}%"))
                              ->orWhereHas('awayTeam', fn ($q2) => $q2->where('name', 'like', "%{$search}%"));
                        })
                    ),

                Tables\Columns\TextColumn::make('batting_order')
                    ->label('Bat #')
                    ->alignCenter()
                    ->sortable()
                    ->placeholder('—'),

                Tables\Columns\IconColumn::make('is_playing_xi')
                    ->label('Playing XI')
                    ->boolean()
                    ->alignCenter()
                    ->trueColor('success')
                    ->falseColor('gray'),

                Tables\Columns\IconColumn::make('is_bench')
                    ->label('Bench')
                    ->boolean()
                    ->alignCenter()
                    ->trueColor('warning')
                    ->falseColor('gray'),

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
                                    ($m->homeTeam?->short_name ?? '?')
                                    . ' vs '
                                    . ($m->awayTeam?->short_name ?? '?')
                                    . ($m->start_time ? ' — ' . $m->start_time->format('d M Y') : ''),
                            ])
                    ),

                Tables\Filters\SelectFilter::make('team_id')
                    ->label('Team')
                    ->searchable()
                    ->options(Team::query()->orderBy('name')->pluck('name', 'id')),

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
                            ? $query->whereHas('player', fn ($q) => $q->where('role', $data['value']))
                            : $query
                    ),

                Tables\Filters\TernaryFilter::make('is_playing_xi')
                    ->label('Playing XI')
                    ->trueLabel('Playing XI only')
                    ->falseLabel('Not in XI')
                    ->placeholder('All'),

                Tables\Filters\TernaryFilter::make('is_bench')
                    ->label('Bench')
                    ->trueLabel('Bench only')
                    ->falseLabel('Not on bench')
                    ->placeholder('All'),

            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()->requiresConfirmation(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([

                    // Bulk confirm playing XI
                    Tables\Actions\BulkAction::make('confirm_xi')
                        ->label('Confirm Playing XI')
                        ->icon('heroicon-o-check-badge')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('Confirm selected as Playing XI')
                        ->modalDescription('This will mark all selected players as Playing XI and remove their bench status.')
                        ->action(function ($records) {
                            $records->each->update([
                                'is_playing_xi' => true,
                                'is_bench'      => false,
                            ]);
                            Notification::make()
                                ->title('Playing XI confirmed')
                                ->success()
                                ->send();
                        }),

                    // Bulk mark as bench
                    Tables\Actions\BulkAction::make('mark_bench')
                        ->label('Mark as Bench')
                        ->icon('heroicon-o-archive-box')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->action(function ($records) {
                            $records->each->update([
                                'is_playing_xi' => false,
                                'is_bench'      => true,
                            ]);
                            Notification::make()
                                ->title('Players marked as bench')
                                ->warning()
                                ->send();
                        }),

                    Tables\Actions\DeleteBulkAction::make(),

                ]),
            ])
            ->defaultSort('id', 'desc');
    }

    // ──────────────────────────────────────────────────────────────────
    // PAGES
    // ──────────────────────────────────────────────────────────────────

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListMatchPlayers::route('/'),
            'create' => Pages\CreateMatchPlayer::route('/create'),
            'edit'   => Pages\EditMatchPlayer::route('/{record}/edit'),
        ];
    }
}