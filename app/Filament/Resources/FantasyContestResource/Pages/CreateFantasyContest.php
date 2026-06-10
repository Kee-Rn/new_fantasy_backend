<?php

namespace App\Filament\Resources\FantasyContestResource\Pages;

use App\Filament\Resources\FantasyContestResource;
use Filament\Resources\Pages\CreateRecord;

class CreateFantasyContest extends CreateRecord
{
    protected static string $resource = FantasyContestResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}