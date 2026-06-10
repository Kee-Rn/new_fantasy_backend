<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MatchPlayerResource\Pages;
use App\Models\GameMatch;
use App\Models\MatchPlayer;
use App\Models\Player;
use App\Models\Team;
use Filament\Forms;
use Filament\Forms\Form;
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
    // FORM  (used only for single edit — bulk done via custom page)
    // ──────────────────────────────────────────────────────────────────

    public static function form(Form $form): Form
    {
        return $form->schema([

            Forms\Components\Section::make('Player status')
                ->schema([

                    Forms\Components\Select::make('match_id')
                        ->label('Match')
                        ->disabled()
                        ->dehydrated()
                        ->relationship('match', 'id')
                        ->getOptionLabelFromRecordUsing(fn ($record) =>
                            ($record->homeTeam?->name ?? '?')
                            . ' vs '
                            . ($record->awayTeam?->name ?? '?')
                        ),

                    Forms\Components\Select::make('player_id')
                        ->label('Player')
                        ->disabled()
                        ->dehydrated()
                        ->relationship('player', 'name'),

                    Forms\Components\Toggle::make('is_playing_xi')
                        ->label('Playing XI')
                        ->default(false)
                        ->live()
                        ->afterStateUpdated(function (Forms\Set $set, $state) {
                            if ($state) $set('is_bench', false);
                        }),

                    Forms\Components\Toggle::make('is_bench')
                        ->label('Bench / substitute')
                        ->default(false)
                        ->live()
                        ->afterStateUpdated(function (Forms\Set $set, $state) {
                            if ($state) $set('is_playing_xi', false);
                        }),

                ])
                ->columns(2),

        ]);
    }

    // ──────────────────────────────────────────────────────────────────
    // TABLE
    // ──────────────────────────────────────────────────────────────────

    public static function table(Table $table): Table
    {
        return $table
            ->columns([

                Tables\Columns\ImageColumn::make('player.photo_path')
                    ->label('')
                    ->disk('public')
                    ->width(32)
                    ->height(32)
                    ->circular(),

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
                        ($record->match?->homeTeam?->name ?? '?')
                        . ' vs '
                        . ($record->match?->awayTeam?->name ?? '?')
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
                                    ($m->homeTeam?->name ?? '?')
                                    . ' vs '
                                    . ($m->awayTeam?->name ?? '?')
                                    . ($m->start_time ? ' — ' . $m->start_time->format('d M Y') : ''),
                            ])
                    ),

                Tables\Filters\SelectFilter::make('team_id')
                    ->label('Team')
                    ->searchable()
                    ->options(Team::query()->orderBy('name')->pluck('name', 'id')),

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

                    Tables\Actions\BulkAction::make('confirm_xi')
                        ->label('Confirm Playing XI')
                        ->icon('heroicon-o-check-badge')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalDescription('Mark all selected players as Playing XI.')
                        ->action(function ($records) {
                            $records->each->update(['is_playing_xi' => true, 'is_bench' => false]);
                            Notification::make()->title('Playing XI confirmed')->success()->send();
                        }),

                    Tables\Actions\BulkAction::make('mark_bench')
                        ->label('Mark as Bench')
                        ->icon('heroicon-o-archive-box')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->action(function ($records) {
                            $records->each->update(['is_playing_xi' => false, 'is_bench' => true]);
                            Notification::make()->title('Players marked as bench')->warning()->send();
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
            'index'       => Pages\ListMatchPlayers::route('/'),
            'assign'      => Pages\AssignMatchPlayers::route('/assign'),
            'edit'        => Pages\EditMatchPlayer::route('/{record}/edit'),
        ];
    }
}