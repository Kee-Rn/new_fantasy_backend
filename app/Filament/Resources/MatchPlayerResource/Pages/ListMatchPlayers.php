<?php

namespace App\Filament\Resources\MatchPlayerResource\Pages;

use App\Filament\Resources\MatchPlayerResource;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListMatchPlayers extends ListRecords
{
    protected static string $resource = MatchPlayerResource::class;

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

            'playing_xi' => Tab::make('Playing XI')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('is_playing_xi', true)),

            'bench' => Tab::make('Bench')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('is_bench', true)),

            'unconfirmed' => Tab::make('Unconfirmed')
                ->modifyQueryUsing(fn (Builder $query) => $query
                    ->where('is_playing_xi', false)
                    ->where('is_bench', false)
                ),
        ];
    }
}