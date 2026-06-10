<?php

namespace App\Filament\Resources\GameMatchResource\Pages;

use App\Filament\Resources\GameMatchResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListGameMatches extends ListRecords
{
    protected static string $resource = GameMatchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    // Quick-filter tabs at the top of the list
    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All'),

            'live' => Tab::make('Live')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'live'))
                ->badge(fn () => \App\Models\GameMatch::where('status', 'live')->count())
                ->badgeColor('success'),

            'upcoming' => Tab::make('Upcoming')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'upcoming')),

            'completed' => Tab::make('Completed')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'completed')),
        ];
    }
}