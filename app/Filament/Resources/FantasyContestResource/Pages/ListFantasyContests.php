<?php

namespace App\Filament\Resources\FantasyContestResource\Pages;

use App\Filament\Resources\FantasyContestResource;
use App\Models\FantasyContest;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListFantasyContests extends ListRecords
{
    protected static string $resource = FantasyContestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All'),

            'active' => Tab::make('Active')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'active'))
                ->badge(fn () => FantasyContest::where('status', 'active')->count())
                ->badgeColor('success'),

            'upcoming' => Tab::make('Upcoming')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'upcoming')),

            'needs_points' => Tab::make('Needs Points')
                ->modifyQueryUsing(fn (Builder $query) => $query
                    ->where('status', 'completed')
                    ->whereIn('points_status', ['pending', 'failed'])
                )
                ->badge(fn () => FantasyContest::where('status', 'completed')
                    ->whereIn('points_status', ['pending', 'failed'])
                    ->count()
                )
                ->badgeColor('danger'),

            'completed' => Tab::make('Completed')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'completed')),
        ];
    }
}