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
                        ->placeholder('e.g. Indian Premier League'),

                    Forms\Components\TextInput::make('short_name')
                        ->label('Short name')
                        ->maxLength(20)
                        ->placeholder('e.g. IPL')
                        ->helperText('Used in compact views and badges'),

                    Forms\Components\TextInput::make('season')
                        ->label('Season')
                        ->maxLength(20)
                        ->placeholder('e.g. 2025 or 2024-25'),

                    Forms\Components\TextInput::make('country')
                        ->label('Country')
                        ->maxLength(60)
                        ->placeholder('e.g. India'),

                ])
                ->columns(2),

            Forms\Components\Section::make('Format & status')
                ->schema([

                    Forms\Components\Select::make('match_type')
                        ->label('Default match type')
                        ->required()
                        ->options([
                            'T20' => 'T20',
                            'ODI' => 'ODI',
                            'Test' => 'Test',
                            'T10' => 'T10',
                        ])
                        ->default('T20')
                        ->helperText('Individual matches can override this'),

                    Forms\Components\Toggle::make('is_active')
                        ->label('Active')
                        ->default(true)
                        ->helperText('Inactive leagues are hidden from fantasy contest creation'),

                ])
                ->columns(2),

            Forms\Components\Section::make('Logo')
                ->schema([

                    Forms\Components\TextInput::make('logo_url')
                        ->label('Logo URL')
                        ->url()
                        ->maxLength(500)
                        ->placeholder('https://...')
                        ->suffixIcon('heroicon-o-photo'),

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

                Tables\Columns\ImageColumn::make('logo_url')
                    ->label('')
                    ->width(36)
                    ->height(36)
                    ->defaultImageUrl(fn () => null)
                    ->extraImgAttributes(['class' => 'rounded']),

                Tables\Columns\TextColumn::make('name')
                    ->label('League')
                    ->searchable()
                    ->sortable()
                    ->weight('semibold'),

                Tables\Columns\TextColumn::make('short_name')
                    ->label('Code')
                    ->badge()
                    ->color('gray')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('season')
                    ->label('Season')
                    ->placeholder('—')
                    ->sortable(),

                Tables\Columns\TextColumn::make('country')
                    ->label('Country')
                    ->placeholder('—')
                    ->searchable(),

                Tables\Columns\BadgeColumn::make('match_type')
                    ->label('Format')
                    ->colors([
                        'success' => 'T20',
                        'info'    => 'ODI',
                        'warning' => 'Test',
                        'gray'    => 'T10',
                    ]),

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

                Tables\Filters\SelectFilter::make('match_type')
                    ->label('Format')
                    ->options([
                        'T20'  => 'T20',
                        'ODI'  => 'ODI',
                        'Test' => 'Test',
                        'T10'  => 'T10',
                    ]),

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