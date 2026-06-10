<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TeamResource\Pages;
use App\Models\League;
use App\Models\Team;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class TeamResource extends Resource
{
    protected static ?string $model = Team::class;

    protected static ?string $navigationIcon  = 'heroicon-o-user-group';
    protected static ?string $navigationLabel = 'Teams';
    protected static ?string $navigationGroup = 'Foundation';
    protected static ?int    $navigationSort  = 2;

    // ──────────────────────────────────────────────────────────────────
    // FORM
    // ──────────────────────────────────────────────────────────────────

    public static function form(Form $form): Form
    {
        return $form->schema([

            Forms\Components\Section::make('Team details')
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
                        ->helperText('Only active leagues are shown')
                        ->columnSpanFull(),

                    Forms\Components\TextInput::make('name')
                        ->label('Team name')
                        ->required()
                        ->maxLength(100)
                        ->placeholder('e.g. Karnali Yaks')
                        ->columnSpanFull(),

                ])
                ->columns(2),

            Forms\Components\Section::make('Logo')
                ->schema([

                    Forms\Components\FileUpload::make('logo_path')
                        ->label('Team logo')
                        ->image()
                        ->disk('public')
                        ->directory('logos/teams')
                        ->imageResizeMode('cover')
                        ->imageCropAspectRatio('1:1')
                        ->imageResizeTargetWidth('200')
                        ->imageResizeTargetHeight('200')
                        ->maxSize(1024)
                        ->helperText('Square image recommended. Max 1MB.'),

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

                Tables\Columns\ImageColumn::make('logo_path')
                    ->label('')
                    ->disk('public')
                    ->width(36)
                    ->height(36)
                    ->extraImgAttributes(['class' => 'rounded']),

                Tables\Columns\TextColumn::make('name')
                    ->label('Team')
                    ->searchable()
                    ->sortable()
                    ->weight('semibold'),

                Tables\Columns\TextColumn::make('league.name')
                    ->label('League')
                    ->searchable()
                    ->sortable()
                    ->limit(30),

                Tables\Columns\TextColumn::make('players_count')
                    ->label('Players')
                    ->counts('players')
                    ->alignCenter()
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->date('d M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

            ])
            ->filters([

                Tables\Filters\SelectFilter::make('league_id')
                    ->label('League')
                    ->searchable()
                    ->options(
                        League::query()
                            ->orderBy('name')
                            ->get()
                            ->mapWithKeys(fn ($l) => [
                                $l->id => $l->name . ($l->season ? ' (' . $l->season . ')' : ''),
                            ])
                    ),

            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->requiresConfirmation()
                    ->modalDescription('Deleting this team will also remove all its players and match assignments.'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
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
            'index'  => Pages\ListTeams::route('/'),
            'create' => Pages\CreateTeam::route('/create'),
            'edit'   => Pages\EditTeam::route('/{record}/edit'),
        ];
    }
}