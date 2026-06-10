<?php

namespace App\Filament\Resources\PlayerPerformanceResource\Pages;

use App\Filament\Resources\PlayerPerformanceResource;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListPlayerPerformances extends ListRecords
{
    protected static string $resource = PlayerPerformanceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Add performance'),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All'),

            'batsmen' => Tab::make('Batted')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('balls_faced', '>', 0)),

            'bowlers' => Tab::make('Bowled')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('overs', '>', 0)),

            'dnb' => Tab::make('DNB')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('out_status', 'dnb')),
        ];
    }
}