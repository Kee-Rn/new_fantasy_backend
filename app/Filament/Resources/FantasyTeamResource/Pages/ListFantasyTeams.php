<?php

namespace App\Filament\Resources\FantasyTeamResource\Pages;

use App\Filament\Resources\FantasyTeamResource;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListFantasyTeams extends ListRecords
{
    protected static string $resource = FantasyTeamResource::class;

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

            'ranked' => Tab::make('Ranked')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereNotNull('rank')),

            'unranked' => Tab::make('Awaiting points')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereNull('rank')),
        ];
    }
}