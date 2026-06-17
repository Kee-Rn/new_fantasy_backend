<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BallByBallResource\Pages;
use App\Models\BallByBall;
use App\Models\GameMatch;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class BallByBallResource extends Resource
{
    protected static ?string $model = BallByBall::class;

    protected static ?string $navigationIcon  = 'heroicon-o-play-circle';
    protected static ?string $navigationLabel = 'Ball by Ball';
    protected static ?string $navigationGroup = 'Match Management';
    protected static ?int    $navigationSort  = 3;

    // No form() — entry is done via the custom LiveScore page

    public static function table(Table $table): Table
    {
        return $table
            ->columns([

                Tables\Columns\TextColumn::make('match_label')
                    ->label('Match')
                    ->getStateUsing(fn ($record) =>
                        ($record->match?->homeTeam?->name ?? '?')
                        . ' vs '
                        . ($record->match?->awayTeam?->name ?? '?')
                    ),

                Tables\Columns\TextColumn::make('over_label')
                    ->label('Over.Ball')
                    ->getStateUsing(function ($record) {
                        $isExtra = in_array($record->extra_type, ['wide', 'no_ball']);

                        $legalBallsBefore = \App\Models\BallByBall::where('match_id', $record->match_id)
                            ->where('innings', $record->innings)
                            ->where('over_number', $record->over_number)
                            ->where('id', '<', $record->id)
                            ->where(function ($q) {
                                $q->whereNull('extra_type')
                                  ->orWhere(function ($q2) {
                                      $q2->whereNotNull('extra_type')
                                         ->whereNotIn('extra_type', ['wide', 'no_ball']);
                                  });
                            })
                            ->count();

                        // Both legal balls and extras display as legalBallsBefore + 1 —
                        // a legal ball completes that slot, an extra is the attempt at it.
                        $displayBall = $legalBallsBefore + 1;

                        $label = ($record->over_number + 1) . '.' . $displayBall;
                        return $isExtra ? $label . ' (' . $record->extra_type . ')' : $label;
                    })
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('innings')
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('batsman.name')
                    ->label('Batsman')
                    ->searchable(),

                Tables\Columns\TextColumn::make('bowler.name')
                    ->label('Bowler')
                    ->searchable(),

                Tables\Columns\TextColumn::make('runs_off_bat')
                    ->label('Runs')
                    ->alignCenter(),

                Tables\Columns\IconColumn::make('is_four')
                    ->label('4')
                    ->boolean()
                    ->alignCenter()
                    ->trueColor('info')
                    ->falseColor('gray'),

                Tables\Columns\IconColumn::make('is_six')
                    ->label('6')
                    ->boolean()
                    ->alignCenter()
                    ->trueColor('success')
                    ->falseColor('gray'),

                Tables\Columns\TextColumn::make('extra_type')
                    ->label('Extra')
                    ->badge()
                    ->color('warning')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('extra_runs')
                    ->label('Ext Runs')
                    ->alignCenter()
                    ->placeholder('—'),

                Tables\Columns\IconColumn::make('is_wicket')
                    ->label('Wkt')
                    ->boolean()
                    ->alignCenter()
                    ->trueColor('danger')
                    ->falseColor('gray'),

                Tables\Columns\TextColumn::make('wicket_type')
                    ->label('Wicket type')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('total_runs_after')
                    ->label('Score')
                    ->getStateUsing(fn ($record) => $record->total_runs_after . "/" . $record->total_wickets_after)
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('notes')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

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

                Tables\Filters\SelectFilter::make('innings')
                    ->options([1 => '1st Innings', 2 => '2nd Innings']),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->modalHeading('Edit Delivery')
                    ->form([
                        \Filament\Forms\Components\Select::make('batsman_id')
                            ->label('Batsman')
                            ->relationship('batsman', 'name')
                            ->searchable()
                            ->required(),

                        \Filament\Forms\Components\Select::make('bowler_id')
                            ->label('Bowler')
                            ->relationship('bowler', 'name')
                            ->searchable()
                            ->required(),

                        \Filament\Forms\Components\Select::make('runs_off_bat')
                            ->label('Runs off bat')
                            ->options(array_combine(range(0,6), range(0,6)))
                            ->required()
                            ->live()
                            ->afterStateUpdated(function ($state, callable $set) {
                                $set('is_four', (int)$state === 4);
                                $set('is_six', (int)$state === 6);
                            }),

                        \Filament\Forms\Components\Toggle::make('is_four')
                            ->label('Four')
                            ->disabled()
                            ->dehydrated()
                            ->afterStateHydrated(function ($state, callable $set, $get) {
                                $set('is_four', (int)$get('runs_off_bat') === 4);
                            }),

                        \Filament\Forms\Components\Toggle::make('is_six')
                            ->label('Six')
                            ->disabled()
                            ->dehydrated()
                            ->afterStateHydrated(function ($state, callable $set, $get) {
                                $set('is_six', (int)$get('runs_off_bat') === 6);
                            }),

                        \Filament\Forms\Components\Select::make('extra_type')
                            ->label('Extra type')
                            ->options([
                                'wide'    => 'Wide',
                                'no_ball' => 'No Ball',
                                'bye'     => 'Bye',
                                'leg_bye' => 'Leg Bye',
                            ])
                            ->nullable()
                            ->live(),

                        \Filament\Forms\Components\TextInput::make('extra_runs')
                            ->label('Extra runs')
                            ->numeric()
                            ->default(0)
                            ->nullable(),

                        \Filament\Forms\Components\Toggle::make('is_wicket')
                            ->label('Wicket fell')
                            ->live(),

                        \Filament\Forms\Components\Select::make('wicket_type')
                            ->label('Wicket type')
                            ->options([
                                'bowled'             => 'Bowled',
                                'caught'             => 'Caught',
                                'caught_and_bowled'  => 'Caught & Bowled',
                                'lbw'                => 'LBW',
                                'stumped'            => 'Stumped',
                                'run_out'            => 'Run Out',
                                'hit_wicket'         => 'Hit Wicket',
                                'retired_hurt'       => 'Retired Hurt',
                            ])
                            ->visible(fn ($get) => $get('is_wicket'))
                            ->nullable(),

                        \Filament\Forms\Components\Select::make('dismissed_player_id')
                            ->label('Dismissed player')
                            ->relationship('dismissedPlayer', 'name')
                            ->searchable()
                            ->visible(fn ($get) => $get('is_wicket'))
                            ->nullable(),

                        \Filament\Forms\Components\Select::make('fielder_id')
                            ->label('Fielder (catch/stumping/run-out)')
                            ->relationship('fielder', 'name')
                            ->searchable()
                            ->visible(fn ($get) => in_array($get('wicket_type'), ['caught', 'stumped', 'run_out']))
                            ->nullable(),

                        \Filament\Forms\Components\Textarea::make('notes')
                            ->label('Notes')
                            ->nullable(),
                    ])
                    ->mutateFormDataUsing(function (array $data): array {
                        // Safety net: always derive is_four/is_six from runs_off_bat,
                        // regardless of what the disabled toggles submitted.
                        $data['is_four'] = (int)($data['runs_off_bat'] ?? 0) === 4;
                        $data['is_six']  = (int)($data['runs_off_bat'] ?? 0) === 6;
                        return $data;
                    })
                    ->after(function ($record) {
                        $match = \App\Models\GameMatch::find($record->match_id);
                        if ($match) {
                            app(\App\Services\Cricket\FantasyPointsService::class)->processMatch($match);
                        }
                    }),

                Tables\Actions\DeleteAction::make()
                    ->requiresConfirmation()
                    ->after(function ($record) {
                        $match = \App\Models\GameMatch::find($record->match_id);
                        if ($match) {
                            app(\App\Services\Cricket\FantasyPointsService::class)->processMatch($match);
                        }
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->after(function ($records) {
                            $matchId = $records->first()?->match_id;
                            if ($matchId) {
                                $match = \App\Models\GameMatch::find($matchId);
                                if ($match) {
                                    app(\App\Services\Cricket\FantasyPointsService::class)->processMatch($match);
                                }
                            }
                        }),
                ]),
            ])
            ->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBallByBall::route('/'),
            'score' => Pages\LiveScore::route('/score'),
        ];
    }
}