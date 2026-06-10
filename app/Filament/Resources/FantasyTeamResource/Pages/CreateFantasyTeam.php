<?php

namespace App\Filament\Resources\FantasyTeamResource\Pages;

use App\Filament\Resources\FantasyTeamResource;
use Filament\Resources\Pages\CreateRecord;

class CreateFantasyTeam extends CreateRecord
{
    protected static string $resource = FantasyTeamResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}