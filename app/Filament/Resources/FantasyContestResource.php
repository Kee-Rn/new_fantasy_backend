<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FantasyContestResource\Pages;
use App\Models\FantasyContest;
use App\Models\GameMatch;
use App\Models\League;
use App\Services\Cricket\FantasyPointsService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class FantasyContestResource extends Resource
{
    protected static ?string $model = FantasyContest::class;

    protected static ?string $navigationIcon  = 'heroicon-o-bolt';
    protected static ?string $navigationLabel = 'Contests';
    protected static ?string $navigationGroup = 'Fantasy';
    protected static ?int    $navigationSort  = 1;

    // ──────────────────────────────────────────────────────────────────
    // FORM
    // ──────────────────────────────────────────────────────────────────

    public static function form(Form $form): Form
    {
        return $form->schema([

            // ── Match assignment ──────────────────────────────────────
            Forms\Components\Section::make('Match')
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
                                        . ($m->start_time ? ' — ' . $m->start_time->format('d M Y, H:i') : ''),
                                ])
                        )
                        ->helperText('Only upcoming and live matches shown')
                        ->columnSpanFull(),

                ])
                ->columns(1),

            // ── Contest details ───────────────────────────────────────
            Forms\Components\Section::make('Contest details')
                ->schema([

                    Forms\Components\TextInput::make('name')
                        ->label('Contest name')
                        ->required()
                        ->maxLength(100)
                        ->placeholder('e.g. Grand League, Head-to-Head, Free Contest'),

                    Forms\Components\Select::make('status')
                        ->label('Status')
                        ->required()
                        ->options([
                            'upcoming'  => 'Upcoming',
                            'active'    => 'Active',
                            'completed' => 'Completed',
                            'cancelled' => 'Cancelled',
                        ])
                        ->default('upcoming'),

                    Forms\Components\TextInput::make('entry_fee')
                        ->label('Entry fee')
                        ->numeric()
                        ->default(0)
                        ->minValue(0)
                        ->prefix('Rs.')
                        ->helperText('Set 0 for free contests'),

                    Forms\Components\TextInput::make('prize_pool')
                        ->label('Prize pool')
                        ->numeric()
                        ->default(0)
                        ->minValue(0)
                        ->prefix('Rs.'),

                    Forms\Components\TextInput::make('max_teams')
                        ->label('Max teams')
                        ->numeric()
                        ->default(0)
                        ->minValue(0)
                        ->helperText('Set 0 for unlimited entries'),

                    Forms\Components\DateTimePicker::make('deadline_at')
                        ->label('Submission deadline')
                        ->nullable()
                        ->seconds(false)
                        ->timezone('Asia/Kathmandu')
                        ->helperText('Users cannot change teams after this time — usually match start time'),

                ])
                ->columns(2),

            // ── Points pipeline ───────────────────────────────────────
            Forms\Components\Section::make('Points pipeline')
                ->description('Managed automatically by the system — only override manually if needed')
                ->schema([

                    Forms\Components\Select::make('points_status')
                        ->label('Points status')
                        ->required()
                        ->options([
                            'pending'     => 'Pending',
                            'calculating' => 'Calculating',
                            'calculated'  => 'Calculated',
                            'failed'      => 'Failed',
                        ])
                        ->default('pending'),

                    Forms\Components\DateTimePicker::make('points_calculated_at')
                        ->label('Points calculated at')
                        ->nullable()
                        ->seconds(false)
                        ->timezone('Asia/Kathmandu')
                        ->disabled(),

                ])
                ->columns(2)
                ->collapsible()
                ->collapsed(),

        ]);
    }

    // ──────────────────────────────────────────────────────────────────
    // TABLE
    // ──────────────────────────────────────────────────────────────────

    public static function table(Table $table): Table
    {
        return $table
            ->columns([

                Tables\Columns\TextColumn::make('name')
                    ->label('Contest')
                    ->searchable()
                    ->sortable()
                    ->weight('semibold'),

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

                Tables\Columns\TextColumn::make('entry_fee')
                    ->label('Entry fee')
                    ->formatStateUsing(fn ($state) => $state == 0 ? 'Free' : 'Rs. ' . number_format($state))
                    ->sortable(),

                Tables\Columns\TextColumn::make('prize_pool')
                    ->label('Prize pool')
                    ->formatStateUsing(fn ($state) => $state == 0 ? '—' : 'Rs. ' . number_format($state))
                    ->sortable(),

                Tables\Columns\TextColumn::make('teams_slots')
                    ->label('Teams')
                    ->getStateUsing(fn ($record) =>
                        $record->total_teams
                        . ($record->max_teams > 0 ? ' / ' . $record->max_teams : ' / ∞')
                    )
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('deadline_at')
                    ->label('Deadline')
                    ->dateTime('d M, H:i')
                    ->sortable()
                    ->placeholder('—'),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'gray'    => 'upcoming',
                        'success' => 'active',
                        'primary' => 'completed',
                        'danger'  => 'cancelled',
                    ])
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('points_status')
                    ->label('Points')
                    ->colors([
                        'gray'    => 'pending',
                        'warning' => 'calculating',
                        'success' => 'calculated',
                        'danger'  => 'failed',
                    ])
                    ->sortable(),

                Tables\Columns\TextColumn::make('points_calculated_at')
                    ->label('Calculated at')
                    ->dateTime('d M, H:i')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

            ])
            ->filters([

                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'upcoming'  => 'Upcoming',
                        'active'    => 'Active',
                        'completed' => 'Completed',
                        'cancelled' => 'Cancelled',
                    ]),

                Tables\Filters\SelectFilter::make('points_status')
                    ->label('Points status')
                    ->options([
                        'pending'     => 'Pending',
                        'calculating' => 'Calculating',
                        'calculated'  => 'Calculated',
                        'failed'      => 'Failed',
                    ]),

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

                Tables\Filters\Filter::make('needs_calculation')
                    ->label('Needs points calculation')
                    ->query(fn ($query) => $query
                        ->where('status', 'completed')
                        ->whereIn('points_status', ['pending', 'failed'])
                    ),

            ])
            ->actions([

                // ── Calculate points ──────────────────────────────────
                Tables\Actions\Action::make('calculate_points')
                    ->label('Calculate points')
                    ->icon('heroicon-o-calculator')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Calculate fantasy points')
                    ->modalDescription(fn ($record) =>
                        'This will aggregate ball-by-ball stats, score all player performances, '
                        . 'and update every fantasy team in "' . $record->name . '". '
                        . 'Match: '
                        . ($record->match?->homeTeam?->name ?? '?')
                        . ' vs '
                        . ($record->match?->awayTeam?->name ?? '?')
                    )
                    ->modalSubmitActionLabel('Yes, calculate now')
                    ->visible(fn ($record) => in_array($record->points_status, ['pending', 'failed']))
                    ->action(function ($record) {
                        try {
                            app(FantasyPointsService::class)->processContest($record);
                            Notification::make()
                                ->title('Points calculated successfully')
                                ->body('All fantasy teams have been ranked.')
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('Calculation failed')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                // ── Recalculate points ────────────────────────────────
                Tables\Actions\Action::make('recalculate_points')
                    ->label('Recalculate')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Recalculate fantasy points')
                    ->modalDescription('Points are already calculated. This will overwrite all existing points and re-rank every team. Use this after correcting a ball-by-ball entry.')
                    ->modalSubmitActionLabel('Yes, recalculate')
                    ->visible(fn ($record) => $record->points_status === 'calculated')
                    ->action(function ($record) {
                        try {
                            app(FantasyPointsService::class)->recalculate($record);
                            Notification::make()
                                ->title('Points recalculated')
                                ->body('All teams have been re-ranked.')
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('Recalculation failed')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->requiresConfirmation()
                    ->modalDescription('Deleting this contest will remove all fantasy teams and their points inside it.'),

            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    // ──────────────────────────────────────────────────────────────────
    // PAGES
    // ──────────────────────────────────────────────────────────────────

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListFantasyContests::route('/'),
            'create' => Pages\CreateFantasyContest::route('/create'),
            'edit'   => Pages\EditFantasyContest::route('/{record}/edit'),
        ];
    }
}