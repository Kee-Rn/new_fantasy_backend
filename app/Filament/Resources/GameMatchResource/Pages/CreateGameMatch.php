<?php

namespace App\Filament\Resources\GameMatchResource\Pages;

use App\Filament\Resources\GameMatchResource;
use Filament\Resources\Pages\CreateRecord;

class CreateGameMatch extends CreateRecord
{
    protected static string $resource = GameMatchResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}