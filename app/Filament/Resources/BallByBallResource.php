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
                    ->getStateUsing(fn ($record) => ($record->over_number + 1) . '.' . $record->ball_number)
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
                Tables\Actions\DeleteAction::make()->requiresConfirmation(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
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