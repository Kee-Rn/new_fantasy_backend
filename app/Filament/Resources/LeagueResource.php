<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LeagueResource\Pages;
use App\Models\League;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class LeagueResource extends Resource
{
    protected static ?string $model = League::class;

    protected static ?string $navigationIcon  = 'heroicon-o-trophy';
    protected static ?string $navigationLabel = 'Leagues';
    protected static ?string $navigationGroup = 'Foundation';
    protected static ?int    $navigationSort  = 1;

    // ──────────────────────────────────────────────────────────────────
    // FORM
    // ──────────────────────────────────────────────────────────────────

    public static function form(Form $form): Form
    {
        return $form->schema([

            Forms\Components\Section::make('League details')
                ->schema([

                    Forms\Components\TextInput::make('name')
                        ->label('League name')
                        ->required()
                        ->maxLength(100)
                        ->placeholder('e.g. Nepal Premier League'),

                    Forms\Components\TextInput::make('season')
                        ->label('Season')
                        ->maxLength(20)
                        ->placeholder('e.g. 2025'),

                    Forms\Components\Toggle::make('is_active')
                        ->label('Active')
                        ->default(true)
                        ->helperText('Inactive leagues are hidden from fantasy contest creation'),

                ])
                ->columns(2),

            Forms\Components\Section::make('Logo')
                ->schema([

                    Forms\Components\FileUpload::make('logo_path')
                        ->label('League logo')
                        ->image()
                        ->disk('public')
                        ->directory('logos/leagues')
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
                    ->label('League')
                    ->searchable()
                    ->sortable()
                    ->weight('semibold'),

                Tables\Columns\TextColumn::make('season')
                    ->label('Season')
                    ->placeholder('—')
                    ->sortable(),

                Tables\Columns\TextColumn::make('teams_count')
                    ->label('Teams')
                    ->counts('teams')
                    ->alignCenter()
                    ->sortable(),

                Tables\Columns\TextColumn::make('matches_count')
                    ->label('Matches')
                    ->counts('matches')
                    ->alignCenter()
                    ->sortable(),

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

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Status')
                    ->trueLabel('Active only')
                    ->falseLabel('Inactive only')
                    ->placeholder('All leagues'),

            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->requiresConfirmation()
                    ->modalDescription('Deleting this league will also delete all its teams and matches. This cannot be undone.'),
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
            'index'  => Pages\ListLeagues::route('/'),
            'create' => Pages\CreateLeague::route('/create'),
            'edit'   => Pages\EditLeague::route('/{record}/edit'),
        ];
    }
}