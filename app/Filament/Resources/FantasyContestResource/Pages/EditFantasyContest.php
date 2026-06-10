<?php

namespace App\Filament\Resources\FantasyContestResource\Pages;

use App\Filament\Resources\FantasyContestResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditFantasyContest extends EditRecord
{
    protected static string $resource = FantasyContestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->requiresConfirmation()
                ->modalDescription('Deleting this contest will remove all fantasy teams and their points inside it.'),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}