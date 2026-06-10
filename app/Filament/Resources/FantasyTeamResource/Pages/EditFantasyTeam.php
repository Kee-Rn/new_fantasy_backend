<?php

namespace App\Filament\Resources\FantasyTeamResource\Pages;

use App\Filament\Resources\FantasyTeamResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditFantasyTeam extends EditRecord
{
    protected static string $resource = FantasyTeamResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()->requiresConfirmation(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}