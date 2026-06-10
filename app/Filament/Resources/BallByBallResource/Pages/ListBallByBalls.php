<?php

namespace App\Filament\Resources\BallByBallResource\Pages;

use App\Filament\Resources\BallByBallResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListBallByBall extends ListRecords
{
    protected static string $resource = BallByBallResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('live_score')
                ->label('Enter Scores')
                ->icon('heroicon-o-play-circle')
                ->color('success')
                ->url(BallByBallResource::getUrl('score')),
        ];
    }
}