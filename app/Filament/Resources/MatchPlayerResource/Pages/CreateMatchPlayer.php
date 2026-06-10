<?php

namespace App\Filament\Resources\MatchPlayerResource\Pages;

use App\Filament\Resources\MatchPlayerResource;
use Filament\Resources\Pages\CreateRecord;

class CreateMatchPlayer extends CreateRecord
{
    protected static string $resource = MatchPlayerResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}